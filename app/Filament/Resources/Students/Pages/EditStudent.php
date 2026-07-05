<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use App\Support\DeletionGuard;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, Student $record): void {
                    if ($message = $record->deletionBlockedReason()) {
                        DeletionGuard::halt($action, $message);
                    }
                }),
            ForceDeleteAction::make()
                ->before(function (ForceDeleteAction $action, Student $record): void {
                    if ($message = $record->deletionBlockedReason()) {
                        DeletionGuard::halt($action, $message);
                    }
                }),
            RestoreAction::make(),
        ];
    }
}
