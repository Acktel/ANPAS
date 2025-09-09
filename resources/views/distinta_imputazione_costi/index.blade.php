{{-- resources/views/distinta_imputazione_costi/index.blade.php --}}
@extends('layouts.app')

@php
  $user = Auth::user();
@endphp

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Distinta Imputazione Costi — Anno {{ session('anno_riferimento', now()->year) }}
    </h1>

    {{-- Select associazione (visibile solo a ruoli amministrativi) --}}
    @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
<div style="max-width:380px; width:100%;">
  <form id="assocSelectForm" class="w-100" method="GET">
    <div class="position-relative"> {{-- contenitore relativo --}}
      <div class="input-group">
        <input
          id="assocSelect"
          name="assocLabel"
          class="form-control"
          autocomplete="off"
          placeholder="Seleziona associazione"
          value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
          aria-label="Seleziona associazione"
        />
        <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
          <i class="fas fa-chevron-down"></i>
        </button>
        <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
      </div>

      {{-- dropdown assoluto rispetto al contenitore --}}
      <ul id="assocSelectDropdown"
          class="list-group position-absolute w-100"
          style="z-index:2000; display:none; max-height:240px; overflow:auto; background:#fff; top:100%; left:0;">
        @foreach($associazioni as $assoc)
          <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">{{ $assoc->Associazione }}</li>
        @endforeach
      </ul>
    </div>
  </form>
</div>
    @endif
  </div>

  @php
    // Tipologie (sezioni) fisse
    $sezioni = [
      2  => 'Automezzi',
      3  => 'Attrezzatura Sanitaria',
      4  => 'Telecomunicazioni',
      5  => 'Costi gestione struttura',
      6  => 'Costo del personale',
      7  => 'Materiale sanitario di consumo',
      8  => 'Costi amministrativi',
      9  => 'Quote di ammortamento',
      10 => 'Beni Strumentali inferiori a 516,00 euro',
      11 => 'Altri costi',
    ];
  @endphp

  <div class="accordion" id="accordionDistinta">
    @foreach ($sezioni as $id => $titolo)
    <div class="accordion-item mb-2">
      <h2 class="accordion-header" id="heading-{{ $id }}">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapse-{{ $id }}" aria-expanded="false" aria-controls="collapse-{{ $id }}">
          <div class="row w-100 text-start gx-2">
            <div class="col-6 fw-bold">{{ $titolo }}</div>
            <div class="col-2" id="summary-bilancio-{{ $id }}">-</div>
            <div class="col-2" id="summary-diretta-{{ $id }}">-</div>
            <div class="col-2" id="summary-totale-{{ $id }}">-</div>
          </div>
        </button>
      </h2>
      <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionDistinta">
        <div class="accordion-body">
          <div class="mb-2 text-end">
            <a href="{{ route('distinta.imputazione.create', ['sezione' => $id]) }}" class="btn btn-sm btn-anpas-green">
              <i class="fas fa-plus me-1"></i> Aggiungi Costi diretti
            </a>
          </div>

          <div class="table-responsive">
            <table id="table-distinta-{{ $id }}"
                   class="common-css-dataTable table table-hover table-striped-anpas table-bordered w-100 mb-0">
              <thead class="thead-anpas">
                <tr id="header-main-{{ $id }}">
                  <th rowspan="2">Voce</th>
                  <th rowspan="2" class="text-end">Importo Totale da Bilancio Consuntivo</th>
                  <th rowspan="2" class="text-end">Costi di Diretta Imputazione</th>
                  <th rowspan="2" class="text-end">Totale Costi Ripartiti</th>
                </tr>
                <tr id="header-sub-{{ $id }}"></tr>
              </thead>
              <tbody class="sortable" data-sezione="{{ $id }}"></tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
    @endforeach

    <div class="accordion-item mt-4">
      <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
        <div class="row w-100 text-start gx-2">
          <div class="col-6">Totale generale</div>
          <div class="col-2" id="tot-bilancio">-</div>
          <div class="col-2" id="tot-diretta">-</div>
          <div class="col-2" id="tot-totale">-</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.distintaCosti = {
  sezioni: @json($sezioni),
  csrf: '{{ csrf_token() }}'
};

document.addEventListener('DOMContentLoaded', () => {
  // --- Config / helper ----------------------------------------------------
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || window.distintaCosti.csrf || '';
  const $assocInput = document.getElementById('assocSelect');
  const $assocHidden = document.getElementById('assocSelectHidden');
  const sezioniIds = Object.keys(window.distintaCosti.sezioni || {});
  const intestazioniAggiunte = new Set();

  const eur = v => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v || 0));
  const num = v => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  };

  // --- Helpers DOM -------------------------------------------------------
  function clearTables() {
    sezioniIds.forEach(id => {
      const $tbody = document.querySelector(`tbody[data-sezione="${id}"]`);
      if ($tbody) $tbody.innerHTML = '';
      const elBil = document.getElementById(`summary-bilancio-${id}`);
      const elDir = document.getElementById(`summary-diretta-${id}`);
      const elTot = document.getElementById(`summary-totale-${id}`);
      if (elBil) elBil.textContent = '€0,00';
      if (elDir) elDir.textContent = '€0,00';
      if (elTot) elTot.textContent = '€0,00';
    });

    document.getElementById('tot-bilancio').textContent = '€0,00';
    document.getElementById('tot-diretta').textContent = '€0,00';
    document.getElementById('tot-totale').textContent = '€0,00';
  }

  function buildHeadersIfNeeded(convenzioni) {
    if (!Array.isArray(convenzioni)) return;
    sezioniIds.forEach(idSezione => {
      if (intestazioniAggiunte.has(idSezione)) return;
      const $headerMain = document.getElementById(`header-main-${idSezione}`);
      const $headerSub  = document.getElementById(`header-sub-${idSezione}`);
      if (!$headerMain || !$headerSub) return;

      convenzioni.forEach(conv => {
        const th = document.createElement('th');
        th.setAttribute('colspan', '2');
        th.className = 'text-center';
        th.textContent = conv;
        $headerMain.appendChild(th);

        const thDir = document.createElement('th');
        thDir.className = 'text-center';
        thDir.textContent = 'Diretti';
        $headerSub.appendChild(thDir);

        const thInd = document.createElement('th');
        thInd.className = 'text-center';
        thInd.textContent = 'Indiretti';
        $headerSub.appendChild(thInd);
      });

      intestazioniAggiunte.add(idSezione);
    });
  }

  // --- Main loadData (fetch / rebuild) -----------------------------------
  async function loadData() {
    const idAssociazione = $assocHidden?.value || '';
    const params = idAssociazione ? `?idAssociazione=${encodeURIComponent(idAssociazione)}` : '';
    let response;

    try {
      response = await fetch('{{ route("distinta.imputazione.data") }}' + params, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) throw new Error('Network response not ok');
    } catch (e) {
      console.error('Errore fetch distinta.imputazione.data', e);
      clearTables();
      return;
    }

    let payload;
    try {
      payload = await response.json();
    } catch (e) {
      console.error('Errore parsing JSON', e);
      clearTables();
      return;
    }

    const convenzioni = Array.isArray(payload?.convenzioni) ? payload.convenzioni : [];
    const righe = Array.isArray(payload?.data) ? payload.data : [];

    // costruiamo le intestazioni se necessario (solo una volta)
    buildHeadersIfNeeded(convenzioni);

    // totali
    const totaliGenerali = { bilancio: 0, diretta: 0, totale: 0 };
    const totaliPerSezione = {};
    sezioniIds.forEach(id => totaliPerSezione[id] = { bilancio: 0, diretta: 0, totale: 0 });

    // svuota corpi tabelle
    sezioniIds.forEach(id => {
      const $tbody = document.querySelector(`tbody[data-sezione="${id}"]`);
      if ($tbody) $tbody.innerHTML = '';
    });

    // popola righe
    righe.forEach(riga => {
      const idSezione = String(riga.sezione_id ?? '');
      if (!idSezione) return;
      const $tbody = document.querySelector(`tbody[data-sezione="${idSezione}"]`);
      if (!$tbody) return;

      // costruzione riga
      const tr = document.createElement('tr');

      const tdVoce = document.createElement('td');
      tdVoce.innerHTML = riga.voce ?? '';
      tr.appendChild(tdVoce);

      const tdBil = document.createElement('td');
      tdBil.className = 'text-end';
      tdBil.textContent = eur(riga.bilancio);
      tr.appendChild(tdBil);

      const tdDir = document.createElement('td');
      tdDir.className = 'text-end';
      tdDir.textContent = eur(riga.diretta);
      tr.appendChild(tdDir);

      const tdTot = document.createElement('td');
      tdTot.className = 'text-end';
      tdTot.textContent = eur(riga.totale);
      tr.appendChild(tdTot);

      // colonne per convenzioni (ordine identico a convenzioni array)
      convenzioni.forEach(convName => {
        const cellObj = riga?.[convName] || {};
        const tdD = document.createElement('td');
        tdD.className = 'text-end';
        tdD.textContent = eur(num(cellObj.diretti));
        tr.appendChild(tdD);

        const tdI = document.createElement('td');
        tdI.className = 'text-end';
        tdI.textContent = eur(num(cellObj.indiretti));
        tr.appendChild(tdI);
      });

      $tbody.appendChild(tr);

      // accumula totali per sezione
      totaliPerSezione[idSezione].bilancio += num(riga.bilancio);
      totaliPerSezione[idSezione].diretta  += num(riga.diretta);
      totaliPerSezione[idSezione].totale   += num(riga.totale);

      // accumula totali generali
      totaliGenerali.bilancio += num(riga.bilancio);
      totaliGenerali.diretta  += num(riga.diretta);
      totaliGenerali.totale   += num(riga.totale);
    });

    // riempi summary per sezione
    sezioniIds.forEach(id => {
      const tot = totaliPerSezione[id] || { bilancio: 0, diretta: 0, totale: 0 };
      const elBil = document.getElementById(`summary-bilancio-${id}`);
      const elDir = document.getElementById(`summary-diretta-${id}`);
      const elTot = document.getElementById(`summary-totale-${id}`);
      if (elBil) elBil.textContent = eur(tot.bilancio);
      if (elDir) elDir.textContent = eur(tot.diretta);
      if (elTot) elTot.textContent = eur(tot.totale);
    });

    // totali generali
    document.getElementById('tot-bilancio').textContent = eur(totaliGenerali.bilancio);
    document.getElementById('tot-diretta').textContent = eur(totaliGenerali.diretta);
    document.getElementById('tot-totale').textContent = eur(totaliGenerali.totale);
  } // end loadData

  // --- Setup custom select (riuso funzione usata negli altri template) ----
  function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
    const form = document.getElementById(formId);
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const toggleBtn = document.getElementById(toggleBtnId);
    const hidden = document.getElementById(hiddenId);
    if (!form || !input || !dropdown || !hidden) return;

    function showDropdown() { dropdown.style.display = 'block'; toggleBtn.setAttribute('aria-expanded','true'); }
    function hideDropdown() { dropdown.style.display = 'none'; toggleBtn.setAttribute('aria-expanded','false'); }

    function filterDropdown(term) {
      term = (term || '').toLowerCase();
      dropdown.querySelectorAll('.assoc-item').forEach(li => {
        li.style.display = (li.textContent || '').toLowerCase().includes(term) ? '' : 'none';
      });
    }

    function setSelection(id, name) {
      hidden.value = id ?? '';
      input.value = name ?? '';
      // se esiste una route di sessione, la aggiorniamo in background e poi ricarichiamo
      @if (Route::has('sessione.setAssociazione'))
      fetch("{{ route('sessione.setAssociazione') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ idAssociazione: id })
      }).finally(() => loadData());
      @else
      loadData();
      @endif
      hideDropdown();
    }

    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      li.style.cursor = 'pointer';
      li.addEventListener('click', () => setSelection(li.dataset.id, li.textContent.trim()));
    });

    input.addEventListener('input', () => filterDropdown(input.value));
    toggleBtn.addEventListener('click', () => dropdown.style.display==='block' ? hideDropdown() : showDropdown());
    document.addEventListener('click', e => { if (!form.contains(e.target)) hideDropdown(); });
  }

  // --- Inizializzazione: installa select (se presente) e carica i dati ---
  setupCustomSelect('assocSelectForm', 'assocSelect', 'assocSelectDropdown', 'assocSelectToggleBtn', 'assocSelectHidden');

  // carica dati iniziali
  loadData();
});
</script>
@endpush
