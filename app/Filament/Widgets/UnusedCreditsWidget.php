<?php

namespace App\Filament\Widgets;

use App\Models\Enrollment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UnusedCreditsWidget extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Students with unused credits';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'coach', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        $consuming = "'".implode("','", Enrollment::CREDIT_CONSUMING_STATUSES)."'";

        return $table
            ->query(fn (): Builder => Enrollment::query()
                ->whereIn('status', ['active', 'pending', 'overdue'])
                ->with(['student', 'offering.program'])
                ->withCount(['attendances as used_credits' => fn (Builder $query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
                ->whereRaw("sessions_included > (select count(*) from attendances where attendances.enrollment_id = enrollments.id and attendances.status in ({$consuming}))")
                ->orderBy('used_credits'))
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('offering')
                    ->label('Timeslot')
                    ->state(fn (Enrollment $record): string => $record->offering
                        ? $record->offering->program?->name.' · '.$record->offering->scheduleLabel().' · '.Carbon::parse($record->offering->period.'-01')->format('M Y')
                        : '—'),
                TextColumn::make('credits')
                    ->label('Used')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Enrollment $record): string => ((int) $record->used_credits).' / '.$record->sessions_included),
                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Enrollment $record): int => max(0, $record->sessions_included - (int) $record->used_credits)),
            ])
            ->paginated([5, 10, 25]);
    }
}
