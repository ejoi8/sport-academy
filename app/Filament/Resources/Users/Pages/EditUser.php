<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\DeletionGuard;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Visibility follows UserResource::canDelete (no self-delete, super-admins protected);
            // this extra guard blocks accounts that still carry children or coaching history.
            DeleteAction::make()
                ->before(function (DeleteAction $action, User $record): void {
                    if ($message = $record->deletionBlockedReason()) {
                        DeletionGuard::halt($action, $message);
                    }
                }),
        ];
    }
}
