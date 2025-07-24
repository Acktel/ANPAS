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

$tipologie = [];
$preventivi = [];
$consuntivi = [];
$scostamenti = [];

foreach ($dati as $row) {
    $tipologie[] = $row->tipologia;
    $preventivi[] = $row->preventivo;
    $consuntivi[] = $row->consuntivo;

    if ($row->preventivo != 0) {
        $scostamento = (($row->consuntivo - $row->preventivo) / $row->preventivo) * 100;
    } else {
        $scostamento = 0;
    }
    $scostamenti[] = round($scostamento, 2);
}
    $anno = session('anno_riferimento');

@endphp

@section('content')
        <div class="page-header d-print-none">
              <div class="row g-2 align-items-center">
                    <div class="col">
                          <h2 class="page-title mb-2 mb-2">
                                {{ __('Dashboard') }}
                          </h2>
                <p>{{ __('Questa è la tua dashboard principale. Da qui puoi accedere a tutte le funzionalità del sistema.') }}
                </p>
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
        document.addEventListener('DOMContentLoaded', () => {
            // 1) Dati da Blade
            const labelsAll = {!! json_encode($tipologie) !!};
            const preventiviAll = {!! json_encode($preventivi) !!};
            const consuntiviAll = {!! json_encode($consuntivi) !!};
            const scostamentiAll = {!! json_encode($scostamenti) !!};

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
            const chunkSize = 2;
            const numCharts = Math.ceil(labelsAll.length / chunkSize);

            // 2) Funzione di suddivisione
            function chunkArray(arr, size) {
                const chunks = [];
                for (let i = 0; i < arr.length; i += size) {
                    chunks.push(arr.slice(i, i + size));
                }
                return chunks;
            }
        }
    }
});
</script>

            // 3) Creazione array di chunk
            const labelsChunks = chunkArray(labelsAll, chunkSize);
            const preventiviChunks = chunkArray(preventiviAll, chunkSize);
            const consuntiviChunks = chunkArray(consuntiviAll, chunkSize);
            const scostamentiChunks = chunkArray(scostamentiAll, chunkSize);

            // 4) Istanzio Chart per ogni blocco e imposto footer
            for (let i = 0; i < numCharts; i++) {
                const ctx = document.getElementById(`riepilogoChart-${i}`).getContext('2d');

                const titleText = labelsChunks[i].join(' e ');
                const descText = `Confronto tra ${titleText}: preventivo vs consuntivo e relative variazioni.`;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labelsChunks[i],
                        datasets: [{
                                label: 'Preventivo',
                                data: preventiviChunks[i],
                                backgroundColor: 'rgba(255, 181,  99, 1)',
                            },
                            {
                                label: 'Consuntivo',
                                data: consuntiviChunks[i],
                                backgroundColor: 'rgba(193, 176, 152, 1)',
                            },
                            {
                                label: 'Scostamento %',
                                data: scostamentiChunks[i],
                                backgroundColor: 'rgba(243, 222,  44, 1)',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: false // Disattivato: ora gestiamo noi il titolo nel DOM
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Importo €'
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Scostamento %'
                                },
                                ticks: {
                                    callback: v => v + '%'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });

                // Inietta titolo e descrizione nel card-header
                const header = document.getElementById(`chart-header-${i}`);
                header.innerHTML = `
    <div class="container-header">
        <h2>${titleText}</h2>
        <span class="text-muted d-block mt-1">${descText}</span>
    </div>
`;
            }

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const successAlert = document.querySelector('.card-body .alert-success');
            if (successAlert) {
                const card = successAlert.closest('.card');
                setTimeout(() => {
                    // card.fadeOut();
                    card.style.transition = 'opacity 0.5s';
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.remove();

                        // Rimuove la classe 'mt-4' dal blocco "Benvenuto nella dashboard"
                        const welcomeRow = document.querySelector('.row.row-deck.row-cards.mt-4');
                        if (welcomeRow) {
                            welcomeRow.classList.remove('mt-4');
                        }

                    }, 500);
                }, 3500);
            }
        });
    </script>
@endpush
