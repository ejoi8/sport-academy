<?php

namespace App\Filament\Resources\Programs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProgramForm
{
    /**
     * @param  bool  $includeSport  Show the sport picker. Omit it inside a sport's relation
     *                              manager, where the owning sport is already implied.
     */
    public static function configure(Schema $schema, bool $includeSport = true): Schema
    {
        return $schema
            ->components(array_values(array_filter([
                $includeSport ? Select::make('sport_id')
                    ->relationship('sport', 'name')
                    ->required()
                    ->preload()
                    ->native(false) : null,
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
                self::moneyField('base_price_sen', 'Monthly price (RM)'),
                self::moneyField('walk_in_fee_sen', 'Walk-in fee (RM)'),
                Toggle::make('is_active')
                    ->default(true),
            ])));
    }

    /**
     * A price field shown to the admin in Ringgit but stored as integer sen.
     */
    private static function moneyField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->prefix('RM')
            ->minValue(0)
            ->default(0)
            ->required()
            ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 100, 2, '.', ''))
            ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100));
    }
}
