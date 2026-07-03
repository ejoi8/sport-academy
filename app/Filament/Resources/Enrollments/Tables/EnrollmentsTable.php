<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('offering.program')
                ->withCount(['attendances as used_credits' => fn (Builder $q) => $q->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)]))
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('offering')
                    ->label('Timeslot')
                    ->state(fn (Enrollment $record): string => $record->offering
                        ? $record->offering->program?->name.' · '.$record->offering->scheduleLabel().' · '.Carbon::parse($record->offering->period.'-01')->format('M Y')
                        : '—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('price_sen')
                    ->label('Price')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('credits')
                    ->label('Credits used')
                    ->badge()
                    ->color(fn (Enrollment $record): string => (int) $record->used_credits >= $record->sessions_included ? 'danger' : 'gray')
                    ->state(fn (Enrollment $record): string => ((int) $record->used_credits).' / '.$record->sessions_included),
                TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(EnrollmentStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
