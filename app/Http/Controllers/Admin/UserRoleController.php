<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Show the form for editing the specified user's roles.
     */
    public function edit(User $user)
    {
        // Fetch only the predefined roles
        $roles = Role::whereIn('name', ['Admin', 'Supervisor', 'User'])->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user's roles in storage.
     */
    public function update(Request $request, User $user)
    {
        // Validate that selected roles are among allowed list
        $allowed = ['Admin', 'Supervisor', 'User'];
        $request->validate([
            'roles'   => ['array'],
            'roles.*' => ['in:' . implode(',', $allowed)],
        ]);

        // Sync roles
        $user->syncRoles($request->input('roles', []));

       return redirect()
        ->route('admin.users.roles.edit', $user->id)
        ->with('status', 'Ruoli aggiornati con successo.');
    }
}
