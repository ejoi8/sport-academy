<?php

namespace App\Filament\Resources\Sports\RelationManagers;

use App\Filament\Resources\Programs\Schemas\ProgramForm;
use App\Filament\Resources\Programs\Tables\ProgramsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'programs';

    protected static ?string $title = 'Programs';

    public function form(Schema $schema): Schema
    {
        return ProgramForm::configure($schema, includeSport: false);
    }

    public function table(Table $table): Table
    {
        return ProgramsTable::configure($table)
            ->recordTitleAttribute('name')
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
