<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Enrollment;
use App\Support\DeletionGuard;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEnrollment extends EditRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, Enrollment $record): void {
                    if ($message = $record->deletionBlockedReason()) {
                        DeletionGuard::halt($action, $message);
                    }
                }),
            ForceDeleteAction::make()
                ->before(function (ForceDeleteAction $action, Enrollment $record): void {
                    if ($message = $record->deletionBlockedReason()) {
                        DeletionGuard::halt($action, $message);
                    }
                }),
            RestoreAction::make(),
        ];
    }
}
