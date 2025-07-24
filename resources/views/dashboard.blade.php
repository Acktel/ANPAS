@extends('layouts.app')

@section('title', 'Dashboard')

@php
    use App\Http\Controllers\ConfigurazioneVeicoliController;
    use App\Http\Controllers\ConfigurazionePersonaleController;

    $configPersone = ConfigurazionePersonaleController::getConfigurazionePersonale();
    $configVeicoli = ConfigurazioneVeicoliController::getConfigurazioneVeicoli();
    $config = true;

    foreach ($configPersone as $key => $value) {
        // $value = [];
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
                <h2 class="page-title mb-2">
                    {{ __('Dashboard') }}
                </h2>
                <p>{{ __('Questa è la tua dashboard principale. Da qui puoi accedere a tutte le funzionalità del sistema.') }}
                </p>
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
                                <strong>Configurazioni complete!</strong> Tutte le configurazioni necessarie sono state
                                effettuate.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- <div class="row row-deck row-cards mt-4 classe-test">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2>{{ __('Benvenuto nella dashboard') }}</h2>
                        <p>{{ __('Questa è la tua dashboard principale. Da qui puoi accedere a tutte le funzionalità del sistema.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div> --}}

        {{-- Grafici divisi in blocchi --}}
        <div id="charts-container" class="row row-deck row-cards mt-4">
            @php
                $chunkSize = 2;
                $total = count($tipologie);
                $numCharts = ceil($total / $chunkSize);
            @endphp

            @for ($i = 0; $i < $numCharts; $i++)
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header text-center card-title" id="chart-header-{{ $i }}">
                            {{-- Il contenuto verrà iniettato da JS --}}
                        </div>
                        <div class="card-body">
                            <canvas id="riepilogoChart-{{ $i }}" height="400"></canvas>
                        </div>
                    </div>
                </div>
            @endfor
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
