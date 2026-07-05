<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return true;
    }

    public function delete(User $user, Enrollment $enrollment): Response
    {
        return $enrollment->deletionBlockedReason()
            ? Response::deny($enrollment->deletionBlockedReason())
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function restore(User $user, Enrollment $enrollment): bool
    {
        return true;
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user, Enrollment $enrollment): Response
    {
        return $this->delete($user, $enrollment);
    }

    public function forceDeleteAny(User $user): bool
    {
        return true;
    }
}
