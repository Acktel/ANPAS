<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Convenzione;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConvenzionePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any convenzione');
    }

    public function view(User $user, Convenzione $convenzione): bool
    {
        return $user->can('view convenzione');
    }

    public function create(User $user): bool
    {
        return $user->can('create convenzione');
    }

    public function update(User $user, Convenzione $convenzione): bool
    {
        return $user->can('update convenzione');
    }

    public function delete(User $user, Convenzione $convenzione): bool
    {
        return $user->can('delete convenzione');
    }

    public function restore(User $user, Convenzione $convenzione): bool
    {
        return $user->can('restore convenzione');
    }

    public function forceDelete(User $user, Convenzione $convenzione): bool
    {
        return $user->can('force_delete convenzione');
    }
}
