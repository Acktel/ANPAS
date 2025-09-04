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
<form method="POST" action="{{ route('sessione.setAssociazione') }}" id="assocForm" class="mb-3 d-flex align-items-center">
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
            <a href="{{ route('distinta.imputazione.create', ['sezione' => $id]) }}" class="btn btn-sm btn-anpas-green p-2">
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
(function(){
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
  const $assoc    = document.getElementById('assocSelect');

  const eur = v => new Intl.NumberFormat('it-IT', { style:'currency', currency:'EUR' }).format(Number(v||0));
  const num = v => Number(v || 0);

  $.ajax({
    url: '{{ route("distinta.imputazione.data") }}',
                    paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
    method: 'GET',
    success: function (response) {
      const convenzioni = response.convenzioni;
      const righe = response.data;

  function destroyIfDataTable(tableEl){
    if (!tableEl) return;
    // Se è stata inizializzata con DataTables in qualche punto globale…
    if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableEl)) {
      $(tableEl).DataTable().clear().destroy();
    }
  }

  function clearTables(){
    const sezioni = @json(array_keys($sezioni));
    sezioni.forEach(idSezione => {
      const main = document.getElementById(`header-main-${idSezione}`);
      const sub  = document.getElementById(`header-sub-${idSezione}`);
      const table= document.getElementById(`table-distinta-${idSezione}`);
      const tbody= document.querySelector(`tbody[data-sezione="${idSezione}"]`);

      // distruggi eventuale DataTable
      destroyIfDataTable(table);

      // conserva le prime 4 colonne fisse e rimuovi le colonne per-convenzione
      if (main) { while (main.children.length > 4) main.removeChild(main.lastElementChild); }
      if (sub)  { sub.innerHTML = ''; }
      if (tbody){ tbody.innerHTML = ''; }

      // reset summary
      const sb = document.getElementById(`summary-bilancio-${idSezione}`);
      const sd = document.getElementById(`summary-diretta-${idSezione}`);
      const st = document.getElementById(`summary-totale-${idSezione}`);
      if (sb) sb.textContent = '-';
      if (sd) sd.textContent = '-';
      if (st) st.textContent = '-';
    });

    // reset totali generali
    document.getElementById('tot-bilancio').textContent = '-';
    document.getElementById('tot-diretta').textContent  = '-';
    document.getElementById('tot-totale').textContent   = '-';
  }

  async function loadData(){
    const mySeq = ++reqSeq;       // snapshot della richiesta corrente
    const idAssociazione = ($assoc?.value || '').trim();

    clearTables();                // <— PULIZIA PRIMA di ogni nuovo fetch

    if (!idAssociazione) return;

    const params = new URLSearchParams({ idAssociazione });
    const res = await fetch(`{{ route('distinta.imputazione.data') }}?${params}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(() => null);

    // se è arrivata una risposta “vecchia”, ignora
    if (mySeq !== reqSeq || !res || !res.ok) return;

    const { convenzioni = [], data: righe = [] } = await res.json();

    // se è arrivata una risposta superata durante il parsing, ignora
    if (mySeq !== reqSeq) return;

    const sezioni = @json(array_keys($sezioni));

    // intestazioni per OGNI sezione: 2 th (Diretti/Indiretti) per ogni convenzione
    sezioni.forEach(idSezione => {
      const main = document.getElementById(`header-main-${idSezione}`);
      const sub  = document.getElementById(`header-sub-${idSezione}`);
      if (!main || !sub) return;

      // (già puliti da clearTables)

      convenzioni.forEach(conv => {
        const th = document.createElement('th');
        th.colSpan = 2;
        th.className = 'text-center';
        th.textContent = conv;
        main.appendChild(th);

        const thD = document.createElement('th');
        thD.className = 'text-center';
        thD.textContent = 'Diretti';
        const thI = document.createElement('th');
        thI.className = 'text-center';
        thI.textContent = 'Indiretti';
        sub.appendChild(thD);
        sub.appendChild(thI);
      });
    });

    const totaliGenerali = { bilancio: 0, diretta: 0, totale: 0 };
    const totaliPerSezione = {};
    sezioni.forEach(id => { totaliPerSezione[id] = { bilancio: 0, diretta: 0, totale: 0 }; });

    // riempi righe
    righe.forEach(riga => {
      const idSezione = String(riga.sezione_id || '');
      const tbody = document.querySelector(`tbody[data-sezione="${idSezione}"]`);
      if (!tbody) return;

      let html = `
        <tr>
          <td>${riga.voce ?? ''}</td>
          <td class="text-end">${eur(riga.bilancio)}</td>
          <td class="text-end">${eur(riga.diretta)}</td>
          <td class="text-end">${eur(riga.totale)}</td>
      `;

      convenzioni.forEach(convName => {
        const cell = riga[convName] || {};
        html += `<td class="text-end">${eur(num(cell.diretti))}</td>`;
        html += `<td class="text-end">${eur(num(cell.indiretti))}</td>`;
      });

      html += `</tr>`;
      tbody.insertAdjacentHTML('beforeend', html);

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
    sezioni.forEach(id => {
      document.getElementById(`summary-bilancio-${id}`).textContent = eur(totaliPerSezione[id].bilancio);
      document.getElementById(`summary-diretta-${id}`).textContent  = eur(totaliPerSezione[id].diretta);
      document.getElementById(`summary-totale-${id}`).textContent   = eur(totaliPerSezione[id].totale);
    });

    // totali generali
    document.getElementById('tot-bilancio').textContent = eur(totaliGenerali.bilancio);
    document.getElementById('tot-diretta').textContent  = eur(totaliGenerali.diretta);
    document.getElementById('tot-totale').textContent   = eur(totaliGenerali.totale);
  }

  // cambio associazione → salva in sessione (se rotta presente) e ricarica
  const assocForm = document.getElementById('assocForm');
  $assoc?.addEventListener('change', function(){
    const idAssociazione = this.value;
    if (!idAssociazione) { clearTables(); return; }
    @if (Route::has('sessione.setAssociazione'))
      fetch("{{ route('sessione.setAssociazione') }}", {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ idAssociazione })
      }).finally(loadData);
    @else
      loadData();
    @endif
  });

  // bootstrap
  loadData();
})();
</script>









<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('assocInput');
    const dropdown = document.getElementById('assocDropdown');
    const hidden = document.getElementById('assocHidden');
    const form = document.getElementById('assocForm');
    const btn = document.getElementById('assocDropdownBtn');
    const items = dropdown.querySelectorAll('.assoc-item');

    // Mostra/nascondi dropdown al click del bottone
    btn.addEventListener('click', function () {
        dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
    });

    // Filtra la lista mentre scrivi
    input.addEventListener('input', function () {
        const filter = input.value.toLowerCase();
        let visible = false;
        items.forEach(item => {
            if (item.textContent.toLowerCase().includes(filter)) {
                item.style.display = '';
                visible = true;
            } else {
                item.style.display = 'none';
            }
        });
        dropdown.style.display = visible ? 'block' : 'none';
    });

    // Selezione elemento dalla lista
    items.forEach(item => {
        item.addEventListener('click', function () {
            input.value = this.textContent.trim();
            hidden.value = this.getAttribute('data-id');
            dropdown.style.display = 'none';
            form.submit();
        });
    });

    // Chiudi la lista cliccando fuori
    document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});
</script>


@endpush
