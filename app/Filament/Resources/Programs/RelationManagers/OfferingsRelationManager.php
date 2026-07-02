<?php

namespace App\Filament\Resources\Programs\RelationManagers;

use App\Filament\Resources\Offerings\Schemas\OfferingForm;
use App\Filament\Resources\Offerings\Tables\OfferingsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OfferingsRelationManager extends RelationManager
{
    protected static string $relationship = 'offerings';

    protected static ?string $title = 'Timeslots';

    public function form(Schema $schema): Schema
    {
        return OfferingForm::configure($schema, includeProgram: false);
    }

    public function table(Table $table): Table
    {
        return OfferingsTable::configure($table)
            ->recordTitleAttribute('period')
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
