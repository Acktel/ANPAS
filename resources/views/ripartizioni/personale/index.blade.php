@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi personale dipendente (Autisti e Barellieri) − Anno {{ $anno }}
    </h1>
  </div>

  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="d-flex mb-3">
    <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="me-3">
      @csrf
      <select id="assocSelect" name="idAssociazione" class="form-select" onchange="this.form.submit()">
        @foreach($associazioni as $assoc)
          <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $selectedAssoc ? 'selected' : '' }}>
            {{ $assoc->Associazione }}
          </option>
        @endforeach
      </select>
    </form>
    </div>
  @endif

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
  const selectedAssoc = document.getElementById('assocSelect')?.value || null;
  const res = await fetch("{{ route('ripartizioni.personale.data') }}" + (selectedAssoc ? `?idAssociazione=${selectedAssoc}` : ''));
  let { data, labels } = await res.json();
  if (!data.length) return;


  // Sposta la riga totale in fondo
  const totaleRow = data.find(r => r.is_totale === -1);
  data = data.filter(r => r.is_totale !== -1);
  if (totaleRow) data.push(totaleRow);


  const table = $('#table-ripartizione');


  const staticCols = [   
    { key: 'idDipendente', label: '',               hidden: true  },
    { key: 'Associazione', label: 'Associazione',   hidden: false },
    { key: 'FullName',     label: 'Dipendente',     hidden: false },
    { key: 'OreTotali',    label: 'Ore Totali',     hidden: false },
    { key: 'is_totale',    label: '',               hidden: true  },
  ];


  const convenzioni = Object.keys(labels).sort((a,b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));


  let hMain = '', hSub = '', cols = [];


  staticCols.forEach(col => {
    hMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    cols.push({ data: col.key, visible: !col.hidden });
  });


  convenzioni.forEach(key => {
    hMain += `<th colspan="2">${labels[key]}</th>`;
    hSub   += `<th>Ore Servizio</th><th>%</th>`;
    cols.push({ data: `${key}_ore`, defaultContent: 0 });
    cols.push({ data: `${key}_percent`, defaultContent: 0 });
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
        <a href="/ripartizioni/personale/${row.idDipendente}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/ripartizioni/personale/${row.idDipendente}/edit" class="btn btn-warning me-1 btn-icon" title="Modifica">
          <i class="fas fa-edit"></i>
        </a>`;
    }
  });


  $('#header-main').html(hMain);
  $('#header-main th').each(function() {
      if ($(this).attr('colspan')) {
       $(this).addClass('border-bottom-special');
      }
    });
  $('#header-sub').html(hSub);


  table.DataTable({
    data,
    columns: cols,
    order: [],
    responsive: true,
    language: {
      url: '/js/i18n/Italian.json'
    },
    rowCallback: (rowEl, rowData, index) => {
      if (rowData.is_totale === -1) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },
    stripeClass: ['table-striped-anpas'] //tolto table-white, e da Classes a Class
  });
});
</script>
@endpush
