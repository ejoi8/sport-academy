<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        // The panel is staff-only. Parents live on the public site, never here.
        return $this->hasAnyRole(['admin', 'coach', 'super_admin']);
    }

    /**
     * Children of this user when they are a parent.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    /**
     * Why this account can't be deleted, or null if it can. History we don't destroy — the panel
     * (and DeletionGuard) surface this instead of orphaning children or coaching records; the fix
     * is to remove the person's role, not delete the login.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->students()->exists()) {
            return 'This account is a parent with children on file — reassign or remove the children first, or just take away their role.';
        }

        $hasCoached = TrainingSession::where('coach_id', $this->id)->orWhere('created_by', $this->id)->exists()
            || Attendance::where('coach_id', $this->id)->orWhere('marked_by', $this->id)->exists();

        if ($hasCoached) {
            return 'This account has coached or recorded training sessions — remove its coach role instead of deleting the history.';
        }

        return null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarded_run_training_at' => 'datetime',
        ];
    }
}
