<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    // Ora description è mass-assignable assieme a name e guard_name
    protected $fillable = ['name', 'guard_name', 'description'];
}
