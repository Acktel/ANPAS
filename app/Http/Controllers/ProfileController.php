<?php
// app/Http/Controllers/ProfileController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller {
    public function edit(Request $request) {
        $user = $request->user();
        $firstRole = $user->roles()->first();
        $roleName = $firstRole?->name ?? 'N/A';
        
        return view('profilo.edit', [
            'user' => $user,
            'roleName' => $roleName,
        ]);
    }

    public function update(Request $request) {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $request->user()->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('status', 'Profilo aggiornato.');
    }

    public function destroy(Request $request) {
        $user = $request->user();

        Auth::logout(); // Logout prima dell'eliminazione
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Account eliminato.');
    }
}
