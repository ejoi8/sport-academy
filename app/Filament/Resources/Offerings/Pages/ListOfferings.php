<?php

namespace App\Filament\Resources\Offerings\Pages;

use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\Offering;
use App\Models\Program;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListOfferings extends ListRecords
{
    protected static string $resource = OfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->registrationWindowAction(false), // Close a month
            $this->registrationWindowAction(true),  // Open a month
            CreateAction::make(),
        ];
    }

    /**
     * Close or open registration for a whole month in one click — flips `is_open` on every offering
     * in the chosen period (optionally scoped to one programme), so no timeslot is ever missed.
     * `is_open` gates NEW bookings only; existing enrolments, sessions and attendance are untouched,
     * and the opposite action reverses it.
     */
    private function registrationWindowAction(bool $open): Action
    {
        return Action::make($open ? 'openMonth' : 'closeMonth')
            ->label($open ? 'Open a month' : 'Close a month')
            ->icon($open ? Heroicon::OutlinedLockOpen : Heroicon::OutlinedLockClosed)
            ->color($open ? 'success' : 'danger')
            ->modalHeading($open ? 'Open registration for a month' : 'Close registration for a month')
            ->modalDescription('This flips every timeslot in the chosen month at once. It only '
                .($open ? 'reopens' : 'stops').' NEW bookings — existing enrolments, sessions and attendance are not affected, and it can be undone with the opposite action.')
            ->modalSubmitActionLabel($open ? 'Open the month' : 'Close the month')
            ->schema([
                Select::make('period')
                    ->label('Month')
                    ->options(fn (): array => static::monthOptions())
                    ->default(fn (): ?string => static::defaultPeriod())
                    ->native(false)
                    ->required(),
                Select::make('program_id')
                    ->label('Programme')
                    ->options(fn (): array => ['' => 'All programmes'] + Program::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default('')
                    ->native(false),
            ])
            ->action(function (array $data) use ($open): void {
                $query = Offering::query()->where('period', $data['period']);

                if (filled($data['program_id'] ?? null)) {
                    $query->where('program_id', $data['program_id']);
                }

                // Report only the timeslots that actually flip, not every one in the month.
                $changed = (clone $query)->where('is_open', ! $open)->count();

                $query->update(['is_open' => $open, 'updated_at' => now()]);

                $month = Carbon::parse($data['period'].'-01')->format('F Y');
                $scope = filled($data['program_id'] ?? null)
                    ? Program::whereKey($data['program_id'])->value('name').' · '.$month
                    : $month;

                Notification::make()
                    ->{$changed > 0 ? 'success' : 'info'}()
                    ->title($changed > 0
                        ? $changed.' timeslot(s) '.($open ? 'opened' : 'closed').' for '.$scope.'.'
                        : $scope.' was already '.($open ? 'open' : 'closed').' — nothing to change.')
                    ->send();
            });
    }

    /**
     * The months that actually have offerings, newest first.
     *
     * @return array<string, string> period (Y-m) => "F Y"
     */
    private static function monthOptions(): array
    {
        return Offering::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->mapWithKeys(fn (string $period): array => [$period => Carbon::parse($period.'-01')->format('F Y')])
            ->all();
    }

    /** Prefer next month, then this month, then the latest month that has offerings. */
    private static function defaultPeriod(): ?string
    {
        $options = static::monthOptions();

        foreach ([now()->addMonthNoOverflow()->format('Y-m'), now()->format('Y-m')] as $preferred) {
            if (isset($options[$preferred])) {
                return $preferred;
            }
        }

        return array_key_first($options);
    }
}
