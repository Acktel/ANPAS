<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleType;
use App\Models\FuelType;

class ConfigurazioneVeicoliController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index() {
        $vehicleTypes = VehicleType::getAll();
        $fuelTypes = FuelType::getAll();

        return view('configurazioni.veicoli', compact('vehicleTypes', 'fuelTypes'));
    }

    public function storeVehicleType(Request $request) {
        $data = $request->validate([
            'nome' => 'required|string|max:255|unique:vehicle_types,nome',
        ]);

        VehicleType::createTipo($data);

        return back()->with('success', 'Tipologia veicolo aggiunta.');
    }

    public function destroyVehicleType(int $id)
    {
        // 🔐 Blocca eliminazione se il tipo è usato da automezzi
        $used = DB::table('automezzi')->where('idTipoVeicolo', $id)->exists();
        if ($used) {
            return back()->withErrors([
                'error' => 'Impossibile eliminare: tipologia veicolo già utilizzata in uno o più automezzi.'
            ]);
        }

        // ❌ Se non trovato o delete fallisce
        if (!VehicleType::deleteTipo($id)) {
            return back()->withErrors([
                'error' => 'Tipologia veicolo non trovata o già eliminata.'
            ]);
        }

        return back()->with('success', 'Tipologia veicolo rimossa correttamente.');
    }


    public function storeFuelType(Request $request) {
        $data = $request->validate([
            'nome' => 'required|string|max:255|unique:fuel_types,nome',
        ]);

        FuelType::createTipo($data);

        return back()->with('success', 'Carburante aggiunto.');
    }

    public function destroyFuelType(int $id) {
        // Optional: blocco su FK
        $used = DB::table('automezzi')->where('idTipoCarburante', $id)->exists();
        if ($used) {
            return back()->withErrors(['error' => 'Tipo di carburante in uso da uno o più automezzi.']);
        }

        if (!FuelType::deleteTipo($id)) {
            return back()->withErrors(['error' => 'Carburante non trovato.']);
        }

        return back()->with('success', 'Carburante rimosso.');
    }
}
