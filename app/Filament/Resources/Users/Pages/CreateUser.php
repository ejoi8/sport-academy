<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Accounts created by staff are trusted, so mark them verified — otherwise the new user would
     * be bounced to email verification before they could sign in. (email_verified_at isn't
     * fillable, so set it explicitly after the record is created.)
     */
    protected function afterCreate(): void
    {
        if (! $this->record->email_verified_at) {
            $this->record->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
