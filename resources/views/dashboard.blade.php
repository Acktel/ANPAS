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

    $anno = session('anno_riferimento');

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

        {{-- Grafici divisi in blocchi --}}
        <div id="charts-container" class="row row-deck row-cards mt-4">

        @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
        <div class="d-flex mb-3">
            <form method="GET" action="{{ route('dashboard') }}">
            <select id="assocSelect" name="idAssociazione" class="form-select" onchange="this.form.submit()">
                @foreach($associazioni as $assoc)
                <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $selectedAssoc ? 'selected' : '' }}>
                    {{ $assoc->Associazione }}
                </option>
                @endforeach
            </select>
            </form>
        </div>
        @endif


            @php
                $chunkSize = 2;
                $total = count($tipologie);
                $numCharts = ceil($total / $chunkSize);
            @endphp

            @for ($i = 0; $i < $total; $i++)
                <div class="col-md-4 my-5 px-5 g-5">
                    <div class="card h-100">
                        <div class="card-header bg-light text-center card-title" id="chart-header-{{ $i }}">
                        </div>
                        <div class="card-body">
                            <canvas id="riepilogoChart-{{ $i }}" height="300"></canvas>
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
            const labelsAll = {!! json_encode($tipologie) !!};
            const preventiviAll = {!! json_encode($preventivi) !!};
            const consuntiviAll = {!! json_encode($consuntivi) !!};
            const scostamentiAll = {!! json_encode($scostamenti) !!};

            for (let i = 0; i < labelsAll.length; i++) {
                const ctx = document.getElementById(`riepilogoChart-${i}`).getContext('2d');

                const label = labelsAll[i];
                const preventivo = preventiviAll[i];
                const consuntivo = consuntiviAll[i];
                const scostamento = scostamentiAll[i];

                const titleText = label;
                const descText = `Grafico con scostamento di ${label} `;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: [label],
                        datasets: [{
                                label: 'Preventivo',
                                data: [preventivo],
                                backgroundColor: 'rgba(255, 181, 99, 1)',
                            },
                            {
                                label: 'Consuntivo',
                                data: [consuntivo],
                                backgroundColor: 'rgba(193, 176, 152, 1)',
                            },
                            {
                                label: 'Scostamento %',
                                data: [scostamento],
                                backgroundColor: 'rgba(243, 222, 44, 1)',
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: false
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

