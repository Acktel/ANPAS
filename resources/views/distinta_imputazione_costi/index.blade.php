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
  </div>

  @if($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating)
  <form method="POST" action="{{ route('sessione.setAssociazione') }}" class="mb-3 d-flex align-items-center gap-2">
    @csrf
    <label for="assocInput" class="mb-0 fw-bold me-2">Associazione:</label>

    <div class="input-group" style="width: 350px; position: relative;">
        <!-- Campo visibile -->
        <input type="text"
               id="assocInput"
               name="assocLabel"
               class="form-control"
               placeholder="Seleziona associazione"
               value="{{ optional($associazioni->firstWhere('idAssociazione', session('associazione_selezionata')))->Associazione ?? '' }}"
               autocomplete="off"
               aria-label="Seleziona associazione">

        <!-- Bottone per aprire/chiudere -->
        <button type="button" class="btn btn-outline-secondary" id="assocDropdownBtn" title="Mostra elenco">
            <i class="fas fa-chevron-down"></i>
        </button>

        <!-- Campo nascosto con l'id -->
        <input type="hidden" id="assocHidden" name="idAssociazione" value="{{ session('associazione_selezionata') ?? '' }}">

        <!-- Dropdown -->
        <ul id="assocDropdown" class="list-group"
            style="position: absolute; top:100%; left:0; width:100%; z-index:2000; display:none; max-height:240px; overflow:auto; background-color:#fff;"">
            @foreach($associazioni as $assoc)
            <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
                {{ $assoc->Associazione }}
            </li>
            @endforeach
        </ul>
    </div>
</form>


  @endif

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

$(function () {
  // --- Helpers --------------------------------------------------------------
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || window.distintaCosti.csrf || '';
  const $assoc   = document.getElementById('assocSelect');

  const eur = v => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v || 0));
  const num = v => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  };

  const sezioniIds = Object.keys(window.distintaCosti.sezioni || {});
  const intestazioniAggiunte = new Set();

  function clearTables() {
    // Svuota tbody e resetta i summary
    sezioniIds.forEach(id => {
      const $tbody = $(`tbody[data-sezione="${id}"]`);
      $tbody.empty();
      $(`#summary-bilancio-${id}`).text('€0,00');
      $(`#summary-diretta-${id}`).text('€0,00');
      $(`#summary-totale-${id}`).text('€0,00');
    });
    $('#tot-bilancio').text('€0,00');
    $('#tot-diretta').text('€0,00');
    $('#tot-totale').text('€0,00');
  }

  function buildHeadersIfNeeded(convenzioni) {
    sezioniIds.forEach(idSezione => {
      if (intestazioniAggiunte.has(idSezione)) return;

      const $headerMain = $(`#header-main-${idSezione}`);
      const $headerSub  = $(`#header-sub-${idSezione}`);

      convenzioni.forEach(conv => {
        $headerMain.append(`<th colspan="2" class="text-center">${conv}</th>`);
        $headerSub.append('<th class="text-center">Diretti</th><th class="text-center">Indiretti</th>');
      });

      intestazioniAggiunte.add(idSezione);
    });
  }

  function loadData() {
    // opzionale: passare idAssociazione al backend
    const idAssociazione = $assoc?.value || '';
    $.ajax({
      url: '{{ route("distinta.imputazione.data") }}',
      method: 'GET',
      data: idAssociazione ? { idAssociazione } : {},
      success: function (response) {
        const convenzioni = Array.isArray(response?.convenzioni) ? response.convenzioni : [];
        const righe       = Array.isArray(response?.data) ? response.data : [];

        // intestazioni dinamiche (solo una volta per sezione)
        buildHeadersIfNeeded(convenzioni);

        // totali
        const totaliGenerali   = { bilancio: 0, diretta: 0, totale: 0 };
        const totaliPerSezione = {};
        sezioniIds.forEach(id => totaliPerSezione[id] = { bilancio: 0, diretta: 0, totale: 0 });

        // pulizia corpi tabella prima del refill
        sezioniIds.forEach(id => $(`tbody[data-sezione="${id}"]`).empty());

        // righe
        righe.forEach(riga => {
          const idSezione = String(riga.sezione_id || '');
          if (!idSezione) return;

          const $tbody = $(`tbody[data-sezione="${idSezione}"]`);
          if ($tbody.length === 0) return;

          let html = `
            <tr>
              <td>${riga.voce ?? ''}</td>
              <td class="text-end">${eur(riga.bilancio)}</td>
              <td class="text-end">${eur(riga.diretta)}</td>
              <td class="text-end">${eur(riga.totale)}</td>
          `;

          convenzioni.forEach(convName => {
            const cell = riga?.[convName] || {};
            html += `<td class="text-end">${eur(num(cell.diretti))}</td>`;
            html += `<td class="text-end">${eur(num(cell.indiretti))}</td>`;
          });

          html += `</tr>`;
          $tbody.append(html);

          // totali sezione
          totaliPerSezione[idSezione].bilancio += num(riga.bilancio);
          totaliPerSezione[idSezione].diretta  += num(riga.diretta);
          totaliPerSezione[idSezione].totale   += num(riga.totale);

          // totali generali
          totaliGenerali.bilancio += num(riga.bilancio);
          totaliGenerali.diretta  += num(riga.diretta);
          totaliGenerali.totale   += num(riga.totale);
        });

        // summary per sezione
        sezioniIds.forEach(id => {
          const tot = totaliPerSezione[id] || { bilancio: 0, diretta: 0, totale: 0 };
          document.getElementById(`summary-bilancio-${id}`).textContent = eur(tot.bilancio);
          document.getElementById(`summary-diretta-${id}`).textContent  = eur(tot.diretta);
          document.getElementById(`summary-totale-${id}`).textContent   = eur(tot.totale);
        });

        // totali generali
        document.getElementById('tot-bilancio').textContent = eur(totaliGenerali.bilancio);
        document.getElementById('tot-diretta').textContent  = eur(totaliGenerali.diretta);
        document.getElementById('tot-totale').textContent   = eur(totaliGenerali.totale);
      },
      error: function () {
        // fallback: pulisci e mostra 0
        clearTables();
      }
    });
  }

  // cambio associazione → salva in sessione (se rotta presente) e ricarica
  $assoc?.addEventListener('change', function () {
    const idAssociazione = this.value;
    if (!idAssociazione) { clearTables(); return; }

    @if (Route::has('sessione.setAssociazione'))
    fetch("{{ route('sessione.setAssociazione') }}", {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ idAssociazione })
    }).finally(loadData);
    @else
    loadData();
    @endif
  });

  // bootstrap
  loadData();
});
</script>
@endpush
