<?php

namespace App\Policies;

use App\Models\Offering;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Offering $offering): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Offering $offering): bool
    {
        return true;
    }

    public function delete(User $user, Offering $offering): Response
    {
        return $offering->deletionBlockedReason()
            ? Response::deny($offering->deletionBlockedReason())
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
