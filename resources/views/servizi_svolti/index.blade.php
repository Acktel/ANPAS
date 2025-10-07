@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">Distinta Servizi Svolti per Convenzione</h1>
    <a href="{{ route('servizi-svolti.create') }}" class="btn btn-anpas-green">
      <i class="fas fa-plus me-1"></i> Aggiungi Servizi Svolti
    </a>
  </div>

@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
<div class="d-flex mb-3">
  <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST"
        class="me-3 w-100" style="max-width:400px; position:relative;">
    @csrf

    <div class="input-group">
      <!-- Campo visibile -->
      <input
        id="assocInput"
        name="assocLabel"
        class="form-control"
        autocomplete="off"
        placeholder="Seleziona associazione"
        value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
        aria-label="Seleziona associazione"
      >

      <!-- Bottone per aprire/chiudere -->
      <button type="button" id="assocToggleBtn" class="btn btn-outline-secondary"
              aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
        <i class="fas fa-chevron-down"></i>
      </button>

      <!-- Campo nascosto con l'id reale -->
      <input type="hidden" id="assocHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
    </div>

    <!-- Dropdown custom -->
    <ul id="assocDropdown"
        class="list-group position-absolute w-100 shadow"
        style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0; background:#fff;">
      @foreach($associazioni as $assoc)
        <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
          {{ $assoc->Associazione }}
        </li>
      @endforeach
    </ul>
  </form>
</div>
@endif


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
    const selectedId = $('#assocHidden').val(); // legge dal <select>
    const url = new URL("{{ route('servizi-svolti.datatable') }}", window.location.origin);
    if (selectedId) url.searchParams.append('idAssociazione', selectedId);
    const res = await fetch(url);
  let { data, labels } = await res.json();
  if (!data.length) return;


  // Sposta la riga totale in fondo
  const totaleRow = data.find(r => r.is_totale === -1);
  data = data.filter(r => r.is_totale !== -1);
  // if (totaleRow) data.push(totaleRow);

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
        if (row.Targa === 'TOTALE') return '';
        return `
        <a href="/servizi-svolti/${row.idAutomezzo}" class="btn  btn-anpas-green me-1 btn-icon" title="Visualizza">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/servizi-svolti/${row.idAutomezzo}/edit" class="btn  btn-warning me-1 btn-icon" title="Modifica">
          <i class="fas fa-edit"></i>
        </a>
        <form method="POST" action="/servizi-svolti/${row.idAutomezzo}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn  btn-danger btn-icon" title="Elimina">
            <i class="fas fa-trash-alt"></i>
          </button>
        </form>
      `;
      }
    });

    $('#header-main').html(headerMain);
    $('#header-sub').html(headerSub);

    table.DataTable({
      stateDuration: -1,
      stateSave: true, 
      data,
      columns,
      columnDefs: [{
          targets: 0,
          visible: false,
          searchable: false
        }, // colonna is_totale
        // {
        //   targets: nServiziColumnIndexes,
        //   className: 'kmTh'
        // }
      ],
      createdCell: function(td) {
        $(td).addClass('col-azioni');
      },
      order: [],
      responsive: true,
      language: {
        url: '/js/i18n/Italian.json',
                        paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
      },
    rowCallback: (rowEl, rowData, index) => {
      if (rowData.is_totale === -1) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },
      stripeClass: ['table-striped-anpas'],


                  drawCallback: function(settings) {
    const api = this.api();
    const pageRows = api.rows({ page: 'current' }).nodes();

    // Rimuovi eventuali righe "TOTALE" precedenti (evita duplicazioni)
    $(pageRows).filter('.totale-row').remove();

    // Aggiungi la riga TOTALE alla fine della pagina
    if (totaleRow) {
        const columnCount = api.columns().visible().reduce((acc, isVisible) => acc + (isVisible ? 1 : 0), 0);
        const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

        api.columns().every(function(index) {
            const col = columns[index];
            if (col.visible === false) return;

            let cellValue = '';
            if (typeof col.render === 'function') {
                cellValue = col.render(totaleRow, 'display', null, { row: -1, col: index, settings });
            } else if (col.data) {
                cellValue = totaleRow[col.data] ?? '';
            }

            $lastRow.append(`<td>${cellValue}</td>`);
        });

        $(api.table().body()).append($lastRow);
    }
},

    });
  });
</script>


<script>
$(function () {
  const $form = $('#assocFilterForm');
  const $input = $('#assocInput');
  const $hidden = $('#assocHidden');
  const $dropdown = $('#assocDropdown');
  const $toggle = $('#assocToggleBtn');
  

  function openDrop()  { $dropdown.show();  $toggle.attr('aria-expanded','true'); }
  function closeDrop() { $dropdown.hide();  $toggle.attr('aria-expanded','false'); }

  // Apri/chiudi con bottone
  $toggle.on('click', function () {
    $dropdown.is(':visible') ? closeDrop() : openDrop();
    $input.trigger('focus');
  });

  // Filtro live
  $input.on('input', function () {
    const q = $(this).val().toLowerCase();
    $dropdown.children('li.assoc-item').each(function () {
      $(this).toggle($(this).text().toLowerCase().includes(q));
    });
    openDrop();
  });

  // Selezione
  $dropdown.on('click', 'li.assoc-item', function () {
    const label = $(this).text().trim();
    const id = $(this).data('id');
    $input.val(label);
    $hidden.val(id);
    closeDrop();
    $form.trigger('submit');
  });

  // Chiudi cliccando fuori
  $(document).on('click', function (e) {
    if (!$form.is(e.target) && $form.has(e.target).length === 0) closeDrop();
  });
});
</script>


@endpush