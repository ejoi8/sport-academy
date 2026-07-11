<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->required() // a contact number is needed for the payment gateway
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    // Required on create; on edit, blank means "keep the current password".
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password.')
                    ->maxLength(255),
                Select::make('roles')
                    // Non-super-admins can't offer the super_admin role — so they can't grant it.
                    ->relationship('roles', 'name', fn (Builder $query): Builder => $query
                        ->when(! Auth::user()?->hasRole('super_admin'), fn (Builder $q) => $q->where('name', '!=', 'super_admin')))
                    ->multiple()
                    ->preload()
                    ->required()
                    ->helperText('admin / super_admin → full panel · coach → coach console · parent → public site only.'),
            ]);
    }
}
