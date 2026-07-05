<?php

namespace App\Filament\Resources\Offerings\Pages;

use App\Filament\Resources\Offerings\OfferingResource;
use App\Models\Offering;
use App\Support\DeletionGuard;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOffering extends EditRecord
{
    protected static string $resource = OfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, Offering $record): void {
                    if ($message = $record->deletionBlockedReason()) {
                        DeletionGuard::halt($action, $message);
                    }
                }),
        ];
    }
}
