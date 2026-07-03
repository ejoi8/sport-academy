<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Resources\Enrollments\Schemas\EnrollmentForm;
use App\Filament\Resources\Enrollments\Tables\EnrollmentsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Enrolments';

    public function form(Schema $schema): Schema
    {
        return EnrollmentForm::configure($schema, includeStudent: false);
    }

    public function table(Table $table): Table
    {
        return EnrollmentsTable::configure($table)
            ->recordTitleAttribute('id')
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
