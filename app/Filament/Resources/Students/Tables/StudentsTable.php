<?php

namespace App\Filament\Resources\Students\Tables;

use App\Enums\Gender;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ic_number')
                    ->label('IC / passport')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('age')
                    ->label('Age')
                    ->state(fn (Student $record): ?int => $record->age)
                    ->placeholder('—'),
                TextColumn::make('gender')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('guardian_phone')
                    ->label('Guardian')
                    ->placeholder('—'),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—'),
                TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Enrolments')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('gender')
                    ->options(Gender::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('report')
                    ->label('Report')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (Student $record): string => route('students.report', $record))
                    ->openUrlInNewTab(),
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
