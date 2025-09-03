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
    <label for="assocSelect" class="mb-0 fw-bold">Associazione:</label>
    <select id="assocSelect" name="idAssociazione" class="form-select w-auto" onchange="this.form.submit()">
      @foreach($associazioni as $assoc)
      <option value="{{ $assoc->IdAssociazione }}" {{ session('associazione_selezionata') == $assoc->IdAssociazione ? 'selected' : '' }}>
        {{ $assoc->Associazione }}
      </option>
      @endforeach
    </select>
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
  const intestazioniAggiunte = new Set();

  $.ajax({
    url: '{{ route("distinta.imputazione.data") }}',
    method: 'GET',
    success: function (response) {
      const convenzioni = response.convenzioni;
      const righe = response.data;

      const totaliGenerali = { bilancio: 0, diretta: 0, totale: 0 };
      const totaliPerSezione = {};

      Object.keys(window.distintaCosti.sezioni).forEach(idSezione => {
        const headerMain = $(`#header-main-${idSezione}`);
        const headerSub = $(`#header-sub-${idSezione}`);

        if (!intestazioniAggiunte.has(idSezione)) {
          convenzioni.forEach(conv => {
            headerMain.append(`<th colspan="2" class="text-center">${conv}</th>`);
            headerSub.append(`<th class="text-center">Diretti</th><th class="text-center">Indiretti</th>`);
          });
          intestazioniAggiunte.add(idSezione);
        }

        totaliPerSezione[idSezione] = { bilancio: 0, diretta: 0, totale: 0 };
      });

      righe.forEach(riga => {
        const idSezione = riga.sezione_id;
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
