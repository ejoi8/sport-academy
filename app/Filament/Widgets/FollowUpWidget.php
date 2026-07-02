<?php

namespace App\Filament\Widgets;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FollowUpWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Payment follow-up';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Enrollment::query()
                ->whereIn('status', [EnrollmentStatus::Overdue, EnrollmentStatus::Pending])
                ->with(['student', 'offering.program'])
                ->orderBy('status'))
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('offering.program.name')
                    ->label('Program'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('price_sen')
                    ->label('Amount')
                    ->money('MYR', divideBy: 100),
            ])
            ->paginated([5, 10]);
    }
}
