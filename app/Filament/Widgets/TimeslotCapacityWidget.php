<?php

namespace App\Filament\Widgets;

use App\Enums\EnrollmentStatus;
use App\Models\Offering;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TimeslotCapacityWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected static ?string $heading = 'Timeslot capacity — this month';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Offering::query()
                ->where('period', now()->format('Y-m'))
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
                TextColumn::make('fill')
                    ->label('Fill')
                    ->badge()
                    ->state(fn (Offering $record) => $record->capacity > 0 ? round($record->active_count / $record->capacity * 100).'%' : '—')
                    ->color(fn (Offering $record) => $record->capacity < 1 ? 'gray' : ($record->active_count >= $record->capacity ? 'danger' : ($record->active_count / $record->capacity >= 0.8 ? 'warning' : 'success'))),
            ])
            ->paginated([5, 10]);
    }
}
