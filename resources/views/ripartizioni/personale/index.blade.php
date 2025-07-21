@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi personale dipendente (Autisti e Barellieri) − Anno {{ $anno }}
    </h1>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table id="table-ripartizione" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
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
  const res = await fetch("{{ route('ripartizioni.personale.data') }}");
  const { data, labels } = await res.json();
  if (!data.length) return;

  const table = $('#table-ripartizione');

  const staticCols = [
    { key: 'is_totale',    label: '',               hidden: true  },
    { key: 'idDipendente', label: '',               hidden: true  },
    { key: 'Associazione', label: 'Associazione',   hidden: false },
    { key: 'FullName',     label: 'Dipendente',     hidden: false },
    { key: 'OreTotali',    label: 'Ore Totali',     hidden: false },
  ];

  const convenzioni = Object.keys(labels).sort((a,b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

  let hMain = '', hSub = '', cols = [], visibleIndex = 0;

  staticCols.forEach(col => {
    hMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    cols.push({ data: col.key, visible: !col.hidden });
    if (!col.hidden) visibleIndex++;
  });

  convenzioni.forEach(key => {
    hMain += `<th colspan="2">${labels[key]}</th>`;
    hSub   += `<th>Ore Servizio</th><th>%</th>`;
    cols.push({ data: `${key}_ore`, defaultContent: 0 });
    cols.push({ data: `${key}_percent`, defaultContent: 0 });
    visibleIndex += 2;
  });

  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    orderable: false,
    searchable: false,
    className: 'col-azioni',
    render: row => {
      if (row.is_totale === -1) return '';
      return `
        <a href="/ripartizioni/personale/${row.idDipendente}" class="btn btn-sm btn-info me-1 btn-icon" title="Visualizza">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/ripartizioni/personale/${row.idDipendente}/edit" class="btn btn-sm btn-warning me-1 btn-icon" title="Modifica">
          <i class="fas fa-edit"></i>
        </a>
        <form method="POST" action="/ripartizioni/personale/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Elimina">
            <i class="fas fa-trash-alt"></i>
          </button>
        </form>
      `;
    }
  });

  $('#header-main').html(hMain);
  $('#header-sub').html(hSub);

  table.DataTable({
    data,
    columns: cols,
    order: [[0, 'asc']],
    orderFixed: [[0, 'asc']],
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
    },
    rowCallback: (rowEl, rowData, index) => {
      if (rowData.is_totale === -1) {
        $(rowEl).addClass('fw-bold table-totalRow');
      }
      if (index % 2 === 0) {
        $(rowEl).removeClass('even').removeClass('odd').addClass('even');
      } else {
        $(rowEl).removeClass('even').removeClass('odd').addClass('odd');
      }
    },
    stripeClasses: ['table-white', 'table-striped-anpas']
  });
});
</script>
@endpush
