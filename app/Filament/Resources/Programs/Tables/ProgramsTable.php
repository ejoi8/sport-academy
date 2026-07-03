<?php

namespace App\Filament\Resources\Programs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sport.name')
                    ->label('Sport')
                    ->sortable(),
                TextColumn::make('base_price_sen')
                    ->label('Monthly')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('walk_in_fee_sen')
                    ->label('Walk-in')
                    ->money('MYR', divideBy: 100),
                TextColumn::make('default_sessions')
                    ->label('Sessions')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('offerings_count')
                    ->counts('offerings')
                    ->label('Timeslots')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
