<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;

class VerificationJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ! $this->supportsRoles($user) || $this->isAdmin($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VerificationJob $verificationJob): bool
    {
        return $this->isAdmin($user) || $verificationJob->user_id === $user->id;
    }

    /**
     * Determine whether the user can download the model output.
     */
    public function download(User $user, VerificationJob $verificationJob): bool
    {
        return $this->isAdmin($user) || $verificationJob->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (! $this->supportsRoles($user)) {
            return true;
        }

        if ($user->hasRole(Roles::ADMIN)) {
            return true;
        }

        if (! $user->hasRole(Roles::CUSTOMER)) {
            return false;
        }

        if (config('verifier.require_active_subscription') && method_exists($user, 'subscribed')) {
            return $user->subscribed('default');
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VerificationJob $verificationJob): bool
    {
        return $this->isAdmin($user) || ! $this->supportsRoles($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VerificationJob $verificationJob): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VerificationJob $verificationJob): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VerificationJob $verificationJob): bool
    {
        return false;
    }

    private function supportsRoles(User $user): bool
    {
        return method_exists($user, 'hasRole');
    }

    private function isAdmin(User $user): bool
    {
        return $this->supportsRoles($user) && $user->hasRole(Roles::ADMIN);
    }
}
