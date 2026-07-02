<?php

namespace App\Filament\Resources\SkillCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SkillCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sport.name')
                    ->label('Sport')
                    ->sortable(),
                TextColumn::make('skills_count')
                    ->counts('skills')
                    ->label('Skills')
                    ->badge(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('sport')
                    ->relationship('sport', 'name'),
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
