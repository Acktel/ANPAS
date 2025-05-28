<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Associazione;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssociationRegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.assoc-register');
    }

    public function register(Request $request)
    {
        $v = $request->validate([
            'Associazione' => 'required|string|max:100',
            'email'       => 'required|email|unique:associazioni,email',
            'password'    => 'required|string|min:8|confirmed',
            'provincia'   => 'required|string|max:100',
            'città'       => 'required|string|max:100',
        ]);

        $id = Associazione::create($v);

        Auth::guard('associazione')->loginUsingId($id);
        return redirect()->route('dashboard');
    }
}
