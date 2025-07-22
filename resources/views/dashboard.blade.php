@extends('layouts.app')

@section('title', 'Dashboard')

@php
use App\Http\Controllers\ConfigurazioneVeicoliController;
use App\Http\Controllers\ConfigurazionePersonaleController;

$configPersone = ConfigurazionePersonaleController::getConfigurazionePersonale();
$configVeicoli = ConfigurazioneVeicoliController::getConfigurazioneVeicoli();
$config = true;

foreach ($configPersone as $key => $value) {
    if ($key == 'qualifiche' && sizeof($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti le qualifiche del personale.';
        $link = route('configurazioni.personale');
        break;
    }
    if ($key == 'contratti' && sizeof($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti i contratti del personale.';
        $link = route('configurazioni.personale');
        break;
    }
    if ($key == 'livelli' && sizeof($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti i livelli di mansione del personale.';
        $link = route('configurazioni.personale');
        break;
    }
}

foreach ($configVeicoli as $key => $value) {
    if ($key == 'vehicleTypes' && sizeof($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti i tipi di veicolo.';
        $link = route('configurazioni.veicoli');
        break;
    }
    if ($key == 'fuelTypes' && sizeof($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti i tipi di carburante.';
        $link = route('configurazioni.veicoli');
        break;
    }
}

$anno= session('anno_riferimento');
@endphp

@section('content')
<div class="page-header d-print-none">
  <div class="row g-2 align-items-center">
    <div class="col">
      <h2 class="page-title">
        {{ __('Dashboard') }}
      </h2>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="row row-deck row-cards">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          @if (!$config)
            <div class="alert alert-warning">
              <strong>Attenzione!</strong> {{ $testo }}<br><br>
              <a href="{{ $link }}" class="btn btn-anpas-green"> Configura ora</a>
            </div>
          @else
            <div class="alert alert-success">
              <strong>Configurazioni complete!</strong> Tutte le configurazioni necessarie sono state effettuate.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row row-deck row-cards mt-4">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <h3>{{ __('Benvenuto nella dashboard') }}</h3>
          <p>{{ __('Questa è la tua dashboard principale. Da qui puoi accedere a tutte le funzionalità del sistema.') }}</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row row-deck row-cards mt-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header">Riepilogo Costi per Tipologia (Anno {{ $anno }})</div>
        <div class="card-body">
          <canvas id="riepilogoChart" height="100"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')

<script>
const ctx = document.getElementById('riepilogoChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($dati->pluck('tipologia')) !!},
        datasets: [
            {
                label: 'Preventivo',
                data: {!! json_encode($dati->pluck('preventivo')) !!},
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
            },
            {
                label: 'Consuntivo',
                data: {!! json_encode($dati->pluck('consuntivo')) !!},
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Riepilogo Costi per Tipologia'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 500
                }
            }
        }
    }
});
</script>
@endpush
