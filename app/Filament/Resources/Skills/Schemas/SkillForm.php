<?php

namespace App\Filament\Resources\Skills\Schemas;

use App\Models\SkillCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The category determines the sport, so sport is never picked directly.
                Select::make('skill_category_id')
                    ->label('Category')
                    ->options(fn (): array => SkillCategory::query()
                        ->with('sport')
                        ->orderBy('sport_id')
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn (SkillCategory $category): array => [
                            $category->id => $category->sport?->name.' · '.$category->name,
                        ])
                        ->all())
                    ->required()
                    ->searchable()
                    ->native(false),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
