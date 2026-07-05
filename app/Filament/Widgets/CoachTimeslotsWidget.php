<?php

namespace App\Filament\Widgets;

use App\Enums\EnrollmentStatus;
use App\Filament\Pages\RunTraining;
use App\Models\Offering;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class CoachTimeslotsWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected static ?string $heading = 'My timeslots — this month';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['coach', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Offering::query()
                ->where('default_coach_id', auth()->id())
                ->where('period', now()->format('Y-m'))
                ->where('is_open', true)
                ->with('program')
                ->withCount(['enrollments as active_count' => fn (Builder $q) => $q->where('status', EnrollmentStatus::Active)])
                ->orderBy('weekday'))
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program'),
                TextColumn::make('timeslot')
                    ->label('Timeslot')
                    ->state(fn (Offering $record) => $record->scheduleLabel()),
                TextColumn::make('active_count')
                    ->label('Enrolled')
                    ->badge()
                    ->formatStateUsing(fn ($state, Offering $record) => $state.' / '.$record->capacity),
            ])
            // Deep-link straight into the timeslot's nearest session, so the coach lands on an
            // already-open card instead of the bare "pick a date" screen. No URL when the
            // timeslot's next occurrence can't be determined (e.g. no weekday set).
            ->recordUrl(fn (Offering $record) => static::runTrainingUrl($record))
            ->recordActions([
                Action::make('run')
                    ->label('Run Training')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->url(fn (Offering $record) => static::runTrainingUrl($record) ?? RunTraining::getUrl()),
            ]);
    }

    protected static function runTrainingUrl(Offering $record): ?string
    {
        $date = $record->nearestOccurrence();

        return $date ? RunTraining::getUrl(['date' => $date->toDateString(), 'session' => $record->id]) : null;
    }
}
