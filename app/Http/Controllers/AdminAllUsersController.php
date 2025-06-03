<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminAllUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:manage-all-associations');
    }

    /** GET /all-users */
    public function index()
    {
        // Restituiamo semplicemente la view con la tabella vuota:
        return view('admin.all_users_index');
    }

    /** GET /all-users/data */
    public function getData(Request $request)
    {
        // Chiamiamo il metodo statico di User che restituisce l'array formattato per DataTables
        $response = User::getDataTableForAdmin($request);
        return response()->json($response);
    }
}
