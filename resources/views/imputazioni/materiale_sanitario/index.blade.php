@extends('layouts.app')

@section('title', 'Imputazione Materiale Sanitario di Consumo')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Imputazione Costi Materiale Sanitario di Consumo</h1>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card-anpas mb-3">
    <div class="card-body d-flex flex-column align-items-start">
      <h4 class="mb-2">
        NUMERO TOTALE SERVIZI EFFETTUATI NELL'ESERCIZIO<br>
        <span class="text-danger fw-bold">
          (al netto dei servizi effettuati per convenzioni MSA, MSAB e ASA <u>SE PRESENTI</u>):
        </span>
        <span id="totaleServizi" class="fw-bold text-anpas-green align-items-center">
          {{ isset($totale_inclusi) ? number_format($totale_inclusi, 0, ',', '.') : 'N/A' }}
        </span>
      </h4>
    </div>      
  </div>
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="d-flex mb-3">
      <form id="assocFilterForm" method="POST" class="me-3">
        @csrf
        <select id="assocSelect" name="idAssociazione" class="form-select">
          @foreach($associazioni as $assoc)
            <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $selectedAssoc ? 'selected' : '' }}>
              {{ $assoc->Associazione }}
            </option>
          @endforeach
        </select>
      </form>
    </div>
  @endif
  

  <div class="mb-3 d-flex justify-content-end">
    <a href="{{ route('imputazioni.materiale_sanitario.editTotale') }}" class="btn btn-anpas-edit">
      <i class="fas fa-edit me-1"></i> Modifica Totale a Bilancio
    </a>
  </div>

  <div class="card-anpas">
    <div class="card-body">
      <table id="materialeSanitarioTable" class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap w-100 mb-0 text-center align-middle">
        <thead class="thead-anpas text-center">
          <tr>
            <th>Targa</th>
            <th>N. SERVIZI SINGOLO AUTOMEZZO</th>
            <th>PERCENTUALE DI RIPARTO</th>
            <th>IMPORTO</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
$(function () {
    let totaleRow = null; // definito qui fuori, visibile ovunque nel DataTable

    let storedTotaleRow = null;


    $('#materialeSanitarioTable').DataTable({
        ajax: {
            url: '{{ route("imputazioni.materiale_sanitario.getData") }}',
dataSrc: function(res) {
    let data = res.data || [];

    // Estrai e salva la riga 'TOTALE'
    storedTotaleRow = data.find(r => r.is_totale === -1);
    data = data.filter(r => r.is_totale !== -1); // rimuovi la riga totale dai dati normali

    return data;
}
        },
        processing: true,
        serverSide: false,
        paging: true,
        searching: false,
        ordering: true,
        stripeClasses: ['odd', 'even'],
        order: [],
        info: false,
        columns: [
            { data: 'Targa' },
            { data: 'n_servizi', className: 'text-end' },
            {
                data: 'percentuale',
                className: 'text-end',
                render: d => d + '%'
            },
            {
                data: 'importo',
                className: 'text-end',
                render: d => parseFloat(d).toFixed(2).replace('.', ',')
            },
            {
                data: 'is_totale',
                visible: false,
                searchable: false
            }
        ],
        rowCallback: function (row, data, index) {
            if (data.is_totale === -1) {
                $(row).addClass('table-warning fw-bold');
            }
            $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
        },
drawCallback: function(settings) {
    const api = this.api();
    const pageRows = api.rows({ page: 'current' }).nodes();

    // Rimuove eventuali duplicati
    $(pageRows).filter('.totale-row').remove();

    // Inserisce la riga TOTALE salvata solo se esiste
    if (storedTotaleRow) {
        const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

        api.columns().every(function(index) {
            const col = api.settings()[0].aoColumns[index];
            if (!col.bVisible) return;

            let cellValue = '';
            const key = col.data;
            if (typeof col.render === 'function') {
                cellValue = col.render(storedTotaleRow[key], 'display', storedTotaleRow, { row: -1, col: index, settings });
            } else if (key) {
                cellValue = storedTotaleRow[key] ?? '';
            }

            const alignmentClass = col.sClass || '';
            $lastRow.append(`<td class="${alignmentClass}">${cellValue}</td>`);
        });

        $(api.table().body()).append($lastRow);
    }
},

        language: {
            url: '/js/i18n/Italian.json'
        }
    });
});
</script>

@endpush
