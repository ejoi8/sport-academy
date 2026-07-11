<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Manage the people behind the logins — admins, coaches and parents. Admin/super-admin only.
 * Guardrails: only super-admins may touch super-admin accounts or grant that role; you can't
 * delete your own account; deleting an account with linked history is blocked (remove the role).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'super_admin']);
    }

    // A super-admin account (and the super_admin role) is only manageable by another super-admin.
    public static function canEdit(Model $record): bool
    {
        return (bool) Auth::user()?->hasRole('super_admin') || ! $record->hasRole('super_admin');
    }

    public static function canDelete(Model $record): bool
    {
        return $record->getKey() !== Auth::id()
            && ((bool) Auth::user()?->hasRole('super_admin') || ! $record->hasRole('super_admin'));
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
