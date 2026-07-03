<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ic_number')
                    ->label('IC / passport no.')
                    ->maxLength(255),
                DatePicker::make('dob')
                    ->label('Date of birth')
                    ->native(false)
                    ->maxDate(now()),
                Select::make('gender')
                    ->options(Gender::class)
                    ->native(false),
                TextInput::make('guardian_name')
                    ->maxLength(255),
                TextInput::make('guardian_phone')
                    ->tel()
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('Parent account')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
