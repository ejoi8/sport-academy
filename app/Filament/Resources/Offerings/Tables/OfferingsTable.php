<?php

namespace App\Filament\Resources\Offerings\Tables;

use App\Enums\ScheduleType;
use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\Offering;
use App\Support\DeletionGuard;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OfferingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('timeslot')
                    ->label('Timeslot')
                    ->state(fn (Offering $record): string => $record->scheduleLabel()),
                TextColumn::make('period')
                    ->label('Month')
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state.'-01')->format('M Y'))
                    ->sortable(),
                TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Enrolled')
                    ->badge(),
                TextColumn::make('capacity')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('session_count')
                    ->label('Sessions')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('price_sen')
                    ->label('Price')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('defaultCoach.name')
                    ->label('Head coach')
                    ->placeholder('—'),
                IconColumn::make('is_open')
                    ->label('Open')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('program')
                    ->relationship('program', 'name'),
                SelectFilter::make('period')
                    ->options(OfferingResource::monthOptions())
                    ->default(now()->format('Y-m')),
                TernaryFilter::make('is_open'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::cloneToMonthAction(),
                    self::setOpenAction(true),
                    self::setOpenAction(false),
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            if ($message = DeletionGuard::firstBlockedMessage($records)) {
                                DeletionGuard::halt($action, $message);
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Open or close registration on the selected timeslots in one go. `is_open` gates new bookings
     * only — it never affects delivery or existing enrolments — so this is safe and fully
     * reversible (the opposite action flips it back).
     */
    private static function setOpenAction(bool $open): BulkAction
    {
        return BulkAction::make($open ? 'openRegistration' : 'closeRegistration')
            ->label($open ? 'Open registration' : 'Close registration')
            ->icon($open ? Heroicon::OutlinedLockOpen : Heroicon::OutlinedLockClosed)
            ->color($open ? 'success' : 'danger')
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($open): void {
                // Report only the ones that actually flip, not everything that was selected.
                $changed = $records->filter(fn (Offering $offering): bool => (bool) $offering->is_open !== $open)->count();

                Offering::whereKey($records->modelKeys())->update([
                    'is_open' => $open,
                    'updated_at' => now(),
                ]);

                Notification::make()
                    ->success()
                    ->title($changed.' timeslot(s) '.($open ? 'opened for registration' : 'closed to registration').'.')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Copy the selected timeslots into another month (skipping any that already exist there),
     * so an admin can roll a month's schedule forward without recreating every timeslot by hand.
     * Defaults to next month — the common "roll this month forward" case needs no changes, just
     * select-all + confirm. One-off sessions and retired (inactive) programs are never cloned:
     * a one-off's specific_date wouldn't make sense in another month, and a retired program's
     * class should not be silently resurrected. This never touches enrolments — only the
     * schedule shell (day/time/capacity/price/coach) is copied.
     */
    private static function cloneToMonthAction(): BulkAction
    {
        return BulkAction::make('cloneToMonth')
            ->label('Clone to month')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->schema([
                Select::make('period')
                    ->label('Target month')
                    ->options(OfferingResource::monthOptions())
                    ->default(now()->addMonthNoOverflow()->format('Y-m'))
                    ->required()
                    ->native(false),
            ])
            ->action(function (Collection $records, array $data): void {
                $created = 0;
                $skippedExisting = 0;
                $skippedOneOff = 0;
                $skippedInactive = 0;

                foreach ($records as $offering) {
                    if ($offering->schedule_type === ScheduleType::OneOff) {
                        $skippedOneOff++;

                        continue;
                    }

                    if (! $offering->program?->is_active) {
                        $skippedInactive++;

                        continue;
                    }

                    $alreadyThere = Offering::query()
                        ->where('program_id', $offering->program_id)
                        ->where('period', $data['period'])
                        ->where('schedule_type', $offering->schedule_type->value)
                        ->where('weekday', $offering->weekday)
                        ->where('start_time', $offering->start_time)
                        ->exists();

                    if ($alreadyThere) {
                        $skippedExisting++;

                        continue;
                    }

                    // Exclude the enrollments_count aggregate this table's query attaches via
                    // ->counts('enrollments') — replicate() copies raw attributes verbatim, and
                    // that column doesn't exist on the offerings table.
                    $copy = $offering->replicate(['enrollments_count']);
                    $copy->period = $data['period'];
                    $copy->save();
                    $created++;
                }

                $message = $created.' timeslot(s) cloned to '.Carbon::parse($data['period'].'-01')->format('F Y').'.';

                $skips = array_filter([
                    $skippedExisting > 0 ? $skippedExisting.' already existed' : null,
                    $skippedOneOff > 0 ? $skippedOneOff.' one-off (never cloned)' : null,
                    $skippedInactive > 0 ? $skippedInactive.' inactive program' : null,
                ]);

                if ($skips !== []) {
                    $message .= ' Skipped: '.implode(', ', $skips).'.';
                }

                Notification::make()
                    ->success()
                    ->title($message)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
