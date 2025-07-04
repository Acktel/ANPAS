@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">Rapporto tra Ricavi per Convenzione e Totale Esercizio</h1>
    <a href="{{ route('rapporti-ricavi.create') }}" class="btn btn-anpas-green">
      <i class="fas fa-plus me-1"></i> Aggiungi Ricavi Convenzioni
    </a>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table id="ricaviTable" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
          <thead class="thead-anpas">
            <tr id="header-main"></tr>
            <tr id="header-sub"></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(async function () {
  const res = await fetch("{{ route('rapporti-ricavi.datatable') }}");
  const { data, labels } = await res.json();
  if (!data.length) return;

  const table = $('#ricaviTable');
  const staticCols = [
    { key: 'is_totale',        label: '',                    hidden: true  },
    { key: 'Associazione',     label: 'Associazione',        hidden: false },
    { key: 'TotaleEsercizio',  label: 'Totale Ricavi Esercizio', hidden: false },
  ];

  const convenzioni = Object.keys(labels)
    .sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

  let headerMain = '', headerSub = '', columns = [];

  // static headers
  staticCols.forEach(col => {
    headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    columns.push({ data: col.key, visible: !col.hidden });
  });

  // dynamic convenzioni headers
  convenzioni.forEach(key => {
    headerMain += `<th colspan="2">${labels[key]}</th>`;
    headerSub  += `<th>Rimborso</th><th>%</th>`;
    columns.push({ data: `${key}_rimborso`, defaultContent: '0,00' });
    columns.push({ data: `${key}_percent`,  defaultContent: 0 });
  });

  // Azioni
  headerMain += `<th rowspan="2">Azioni</th>`;
  columns.push({
    data: null,
    orderable: false,
    searchable: false,
    render: row => {
      // non mostro i bottoni sulla riga totale
      if (row.is_totale === -1) return '';
      // URL relative al prefix /rapporti-ricavi
      return `
        <a href="/rapporti-ricavi/${row.idAssociazione}" class="btn btn-sm btn-info me-1">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/rapporti-ricavi/${row.idAssociazione}/edit" class="btn btn-sm btn-warning me-1">
          <i class="fas fa-edit"></i>
        </a>
        <form method="POST" action="/rapporti-ricavi/${row.idAssociazione}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-sm btn-danger">
            <i class="fas fa-trash-alt"></i>
          </button>
        </form>
      `;
    }
  });

  $('#header-main').html(headerMain);
  $('#header-sub').html(headerSub);

  table.DataTable({
    data,
    columns,
    order:      [[0, 'asc']],      // fissa la riga totale in cima
    orderFixed: [[0, 'asc']],
    paging:     false,
    searching:  false,
    info:       false,
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
    },
    rowCallback: (row, rowData) => {
      if (rowData.is_totale === -1) {
        $(row).addClass('fw-bold table-totalRow');
      }
    }
  });
});
</script>
@endpush
