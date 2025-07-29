@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">Distinta Servizi Svolti per Convenzione</h1>
    <a href="{{ route('servizi-svolti.create') }}" class="btn btn-anpas-green">
      <i class="fas fa-plus me-1"></i> Aggiungi Servizi Svolti
    </a>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table id="serviziTable" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
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
  $(async function() {
    const res = await fetch("{{ route('servizi-svolti.datatable') }}");
    const {
      data,
      labels
    } = await res.json();
    if (!data.length) return;

    const table = $('#serviziTable');

    const staticColumns = [{
        key: 'is_totale',
        label: '',
        hidden: true
      }, // per forzare riga TOTALE in cima
      {
        key: 'idAutomezzo',
        label: 'ID',
        hidden: true
      },
      {
        key: 'Automezzo',
        label: 'Automezzo'
      },
      {
        key: 'Targa',
        label: 'Targa'
      },
      {
        key: 'CodiceIdentificativo',
        label: 'Codice ID'
      },
      {
        key: 'Totale',
        label: 'Totale'
      },
    ];

    const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

    let headerMain = '';
    let headerSub = '';
    const columns = [];
    let visibleIndex = 0;
    const nServiziColumnIndexes = [];

    staticColumns.forEach(col => {
      headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
      columns.push({
        data: col.key,
        visible: !col.hidden
      });
      if (!col.hidden) visibleIndex++;
    });

    convenzioni.forEach(conv => {
      headerMain += `<th colspan="2">${labels[conv]}</th>`;
      headerSub += `<th class="kmTh">N. SERVIZI SVOLTI</th><th class="kmTh">%</th>`;
      columns.push({
        data: `${conv}_n`,
        defaultContent: 0
      });
      nServiziColumnIndexes.push(visibleIndex);
      visibleIndex++;
      columns.push({
        data: `${conv}_percent`,
        defaultContent: 0
      });
      visibleIndex++;
    });

    headerMain += `<th rowspan="2">Azioni</th>`;
    columns.push({
      data: null,
      orderable: false,
      searchable: false,
      createdCell: function(td) {
        $(td).addClass('col-azioni');
      },
      render: function(row) {
        if (row.Automezzo === 'TOTALE') return '';
        return `
        <a href="/servizi-svolti/${row.idAutomezzo}" class="btn btn-sm btn-info me-1 btn-icon" title="Visualizza">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/servizi-svolti/${row.idAutomezzo}/edit" class="btn btn-sm btn-warning me-1 btn-icon" title="Modifica">
          <i class="fas fa-edit"></i>
        </a>
        <form method="POST" action="/servizi-svolti/${row.idAutomezzo}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Elimina">
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
      columnDefs: [{
          targets: 0,
          visible: false,
          searchable: false
        }, // colonna is_totale
        {
          targets: nServiziColumnIndexes,
          className: 'kmTh'
        }
      ],
      createdCell: function(td) {
        $(td).addClass('col-azioni');
      },
      order: [
        [0, 'asc']
      ], // riga TOTALE (is_totale)
      orderFixed: [
        [0, 'asc']
      ], // forzata fissa
      responsive: true,
      language: {
        url: '/js/i18n/Italian.json'
      },
      rowCallback: function(row, data,index) {
        if (data.Automezzo === 'TOTALE') {
          $(row).addClass('fw-bold table-totalRow');
        }
        if (index % 2 === 0) {
          $(row).removeClass('even').removeClass('odd').addClass('even');
        } else {
          $(row).removeClass('even').removeClass('odd').addClass('odd');
        }
      },
      stripeClasses: ['table-white', 'table-striped-anpas'],
    });
  });
</script>
@endpush