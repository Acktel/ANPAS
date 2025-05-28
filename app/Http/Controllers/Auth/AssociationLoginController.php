<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssociationLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.assoc-login');
    }

    public function login(Request $request)
    {
        $v = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('associazione')->attempt($v, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email'=>'Credenziali non valide']);
    }

    public function logout(Request $request)
    {
        Auth::guard('associazione')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('assoc.login');
    }
}
