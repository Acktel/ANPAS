<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Associazione;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssociazionePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any associazione');
    }

    public function view(User $user, Associazione $associazione): bool
    {
        return $user->can('view associazione');
    }

    public function create(User $user): bool
    {
        return $user->can('create associazione');
    }

    public function update(User $user, Associazione $associazione): bool
    {
        return $user->can('update associazione');
    }

    public function delete(User $user, Associazione $associazione): bool
    {
        return $user->can('delete associazione');
    }

    public function restore(User $user, Associazione $associazione): bool
    {
        return $user->can('restore associazione');
    }

    public function forceDelete(User $user, Associazione $associazione): bool
    {
        return $user->can('force_delete associazione');
    }
}
