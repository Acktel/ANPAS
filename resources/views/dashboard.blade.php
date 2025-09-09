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

       @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" action="{{ route('dashboard') }}" id="assocSelectForm" class="w-100" style="max-width:400px">
        <div class="position-relative">
        <div class="input-group">
          <!-- Campo visibile -->
          <input
            id="assocSelect"
            name="assocLabel"
            class="form-control"
            autocomplete="off"
            placeholder="Seleziona associazione"
            value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
            aria-label="Seleziona associazione"
          >

          <!-- Bottone per aprire/chiudere -->
          <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
            <i class="fas fa-chevron-down"></i>
          </button>

          <!-- Campo nascosto con l'id reale -->
          <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
        </div>

        <!-- Dropdown custom -->
            <ul id="assocSelectDropdown" class="list-group position-absolute w-100" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
                   background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
              @foreach($associazioni as $assoc)
                <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
                  {{ $assoc->Associazione }}
                </li>
              @endforeach
            </ul>
            </div>
      </form>
    </div>
  @endif

        {{-- Grafici divisi in blocchi --}}
        <div id="charts-container" class="row row-deck row-cards mt-4">

        {{-- @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
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
        @endif --}}


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

    <script>
        function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
  const form = document.getElementById(formId);
  const input = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  const toggleBtn = document.getElementById(toggleBtnId);
  const hidden = document.getElementById(hiddenId);

  if (!form || !input || !dropdown || !hidden) return;

  // costruisco array di items
  const items = Array.from(dropdown.querySelectorAll('.assoc-item'))
    .map(li => ({ id: String(li.dataset.id), name: (li.textContent || '').trim(), node: li }));

  function showDropdown() { dropdown.style.display = 'block'; toggleBtn.setAttribute('aria-expanded', 'true'); }
  function hideDropdown() { dropdown.style.display = 'none'; toggleBtn.setAttribute('aria-expanded', 'false'); }

  function filterDropdown(term) {
    term = (term || '').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      const txt = (li.textContent || '').toLowerCase();
      li.style.display = txt.includes(term) ? '' : 'none';
    });
  }

  function setSelection(id, name, submit = true) {
    hidden.value = id ?? '';
    input.value = name ?? '';

    // salva in localStorage per fallback client-side
    try {
      localStorage.setItem('associazione_selezionata', id ?? '');
      localStorage.setItem('selectedAssocLabel', name ?? '');
    } catch (e) {}

    // aggiorna l'url (querystring) prima della submit così il back button è coerente
    try {
      const url = new URL(window.location);
      if (id) url.searchParams.set('idAssociazione', id);
      else url.searchParams.delete('idAssociazione');
      history.replaceState(null, '', url);
    } catch (e) {}

    if (submit) form.submit();
  }

  // click sugli item
  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', function () {
      setSelection(this.dataset.id, this.textContent.trim());
    });
  });

  input.addEventListener('input', () => {
    filterDropdown(input.value);
  });

  toggleBtn.addEventListener('click', (ev) => {
    ev.stopPropagation();
    dropdown.style.display === 'block' ? hideDropdown() : showDropdown();
  });

  document.addEventListener('click', e => {
    if (!form.contains(e.target)) hideDropdown();
  });

  // --- restore al caricamento:
  // 1) se server ha impostato hidden.value, cerco l'item corrispondente e imposto la label
  // 2) altrimenti uso localStorage come fallback
  (function restoreSelection() {
    try {
      const hid = (hidden.value || '').toString();
      if (hid) {
        // cerco l'item con lo stesso data-id
        const found = items.find(it => it.id === hid);
        if (found) {
          input.value = found.name;
          return;
        }
        // se non trovo, magari il markup è cambiato: provo a usare localStorage
      }

      // fallback localStorage
      const storedId = localStorage.getItem('associazione_selezionata');
      const storedLabel = localStorage.getItem('selectedAssocLabel');
      if (!hidden.value && storedId) {
        hidden.value = storedId;
        input.value = storedLabel || input.value;
      } else if (hidden.value && !input.value) {
        // se hidden è presente ma input vuoto e non ho trovato l'item, uso localStorage label
        if (storedLabel) input.value = storedLabel;
      }
    } catch (e) { /* ignore */ }
  })();
}


setupCustomSelect(
  "assocSelectForm",
  "assocSelect",
  "assocSelectDropdown",
  "assocSelectToggleBtn",
  "assocSelectHidden"
);

    </script>
@endpush

