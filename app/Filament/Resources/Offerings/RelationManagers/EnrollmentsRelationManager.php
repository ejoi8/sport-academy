<?php

namespace App\Filament\Resources\Offerings\RelationManagers;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Support\DeletionGuard;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Enrolments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->relationship('student', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->options(EnrollmentStatus::class)
                    ->default(EnrollmentStatus::Active->value)
                    ->required()
                    ->native(false),
                TextInput::make('price_sen')
                    ->label('Price (RM)')
                    ->numeric()
                    ->prefix('RM')
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                TextInput::make('sessions_included')
                    ->label('Sessions')
                    ->helperText('Snapshotted from the timeslot; adjust for a pro-rated enrolment.')
                    ->numeric()
                    ->minValue(0)
                    ->default(fn (): int => $this->getOwnerRecord()->session_count ?? 4)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('price_sen')
                    ->label('Price')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('sessions_included')
                    ->label('Sessions')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->date(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Enrollment $record): void {
                        if ($message = $record->deletionBlockedReason()) {
                            DeletionGuard::halt($action, $message);
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records): void {
                            if ($message = DeletionGuard::firstBlockedMessage($records)) {
                                DeletionGuard::halt($action, $message);
                            }
                        }),
                ]),
            ]);
    }
}
