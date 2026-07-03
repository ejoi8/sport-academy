<?php

namespace App\Filament\Resources\Offerings\Tables;

use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\Offering;
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
                    ->options(OfferingResource::monthOptions()),
                TernaryFilter::make('is_open'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::cloneToMonthAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Copy the selected timeslots into another month (skipping any that already exist there),
     * so an admin can roll last month's schedule forward in one click.
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
                    ->required()
                    ->native(false),
            ])
            ->action(function (Collection $records, array $data): void {
                $created = 0;

                foreach ($records as $offering) {
                    $alreadyThere = Offering::query()
                        ->where('program_id', $offering->program_id)
                        ->where('period', $data['period'])
                        ->where('schedule_type', $offering->schedule_type->value)
                        ->where('weekday', $offering->weekday)
                        ->where('start_time', $offering->start_time)
                        ->exists();

                    if ($alreadyThere) {
                        continue;
                    }

                    $copy = $offering->replicate();
                    $copy->period = $data['period'];
                    $copy->save();
                    $created++;
                }

                Notification::make()
                    ->success()
                    ->title($created.' timeslot(s) cloned to '.Carbon::parse($data['period'].'-01')->format('F Y'))
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
