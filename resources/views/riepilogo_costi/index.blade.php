@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Riepilogo Costi − Anno {{ $anno }}
  </h1>

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna voce presente per l’anno {{ $anno }}.<br>
    Vuoi importare le voci dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  @php
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
      11 => 'Altri costi'
    ];
  @endphp

  <div class="accordion" id="accordionRiep">
    @foreach ($sezioni as $id => $titolo)
      <div class="accordion-item mb-2">
        <h2 class="accordion-header" id="heading-{{ $id }}">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapse-{{ $id }}"
                  aria-expanded="false"
                  aria-controls="collapse-{{ $id }}">
            <div class="row w-100 text-start gx-2">
              <div class="col-5 fw-bold">{{ $titolo }}</div>
              <div class="col-2" id="summary-prev-{{ $id }}">-</div>
              <div class="col-2" id="summary-cons-{{ $id }}">-</div>
              <div class="col-2" id="summary-scos-{{ $id }}">-</div>
            </div>
          </button>
        </h2>
        <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionRiep">
          <div class="accordion-body">
            <div class="mb-2 text-end">
              <a href="{{ route('riepilogo.costi.create', $id) }}"
                 class="btn btn-sm btn-anpas-green">
                <i class="fas fa-plus me-1"></i> Aggiungi Voce
              </a>
            </div>
            <table id="table-sezione-{{ $id }}"
                   class="common-css-dataTable table table-hover table-striped-anpas table-bordered w-100 mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th>Voce</th>
                  <th>Preventivo</th>
                  <th>Consuntivo</th>
                  <th>% Scostamento</th>
                  <th class="col-actions">Azioni</th>
                </tr>
              </thead>
              <tbody class="sortable" data-sezione="{{ $id }}"></tbody>
            </table>
          </div>
        </div>
      </div>
    @endforeach

    <div class="accordion-item mt-4">
      <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
        <div class="row w-100 text-start gx-2">
          <div class="col-5">Totale generale</div>
          <div class="col-2" id="tot-prev">€0.00</div>
          <div class="col-2" id="tot-cons">€0.00</div>
          <div class="col-2" id="tot-scos">0%</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.riepilogoCosti = {
  sezioni: @json($sezioni),
  csrf: '{{ csrf_token() }}'
};

// Funzione per applicare zebra manualmente
function applicaZebra($table) {
  const $rows = $table.find('tbody tr');
  $rows.removeClass('even odd').each(function (i) {
    $(this).addClass(i % 2 === 0 ? 'even' : 'odd');
  });
}

$(function () {
  $('table.common-css-dataTable').each(function () {
    const $table = $(this);
    const tableId = $table.attr('id');

    if (!$.fn.DataTable.isDataTable($table)) {
      console.log('Initializing DataTable for:', tableId);

      $table.DataTable({
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
        }
      });
    }

    // Observer per riapplicare zebra quando il contenuto cambia
    const observer = new MutationObserver(() => {
      applicaZebra($table);
    });

    const tbody = $table.find('tbody')[0];
    if (tbody) {
      observer.observe(tbody, { childList: true, subtree: false });
    }
  });
});
</script>
@endpush

