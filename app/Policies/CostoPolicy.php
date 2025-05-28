<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Costo;
use Illuminate\Auth\Access\HandlesAuthorization;

class CostoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any costo');
    }

    public function view(User $user, Costo $costo): bool
    {
        return $user->can('view costo');
    }

    public function create(User $user): bool
    {
        return $user->can('create costo');
    }

    public function update(User $user, Costo $costo): bool
    {
        return $user->can('update costo');
    }

    public function delete(User $user, Costo $costo): bool
    {
        return $user->can('delete costo');
    }

    public function restore(User $user, Costo $costo): bool
    {
        return $user->can('restore costo');
    }

    public function forceDelete(User $user, Costo $costo): bool
    {
        return $user->can('force_delete costo');
    }
}
