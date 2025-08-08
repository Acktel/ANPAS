@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi personale <strong>volontario</strong> − Anno {{ $anno }}
    </h1>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table id="table-rip-volontari" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
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
$(async function(){
  const res = await fetch("{{ route('ripartizioni.volontari.data') }}");
  const { data, labels } = await res.json();
  if (!data.length) return;

  const table = $('#table-rip-volontari');

  const staticCols = [
    { key:'Associazione', label:'Associazione' },
    { key:'FullName',     label:'Descrizione' },
    { key:'OreTotali',    label:'Ore Totali di Servizio Volontario' }
  ];

  const convenzioni = Object.keys(labels).sort((a,b)=>parseInt(a.slice(1))-parseInt(b.slice(1)));

  let hMain = '', hSub = '', cols = [];

  staticCols.forEach(c => {
    hMain += `<th rowspan="2">${c.label}</th>`;
    cols.push({ data: c.key });
  });

  convenzioni.forEach(key => {
    hMain += `<th colspan="2">${labels[key]}</th>`;
    hSub  += `<th>Ore</th><th>%</th>`;
    cols.push({ data:`${key}_ore`, defaultContent:0 });
    cols.push({ data:`${key}_percent`, defaultContent:0 });
  });

  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    orderable: false,
    searchable: false,
    className: 'col-azioni',
    render: () => {
      return `
        <a href="{{ route('ripartizioni.volontari.edit') }}" class="btn btn-warning btn-icon" title="Modifica">
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
    paging: false,
    searching: false,
    info: false,
    responsive: true,
    language: {
      url: '/js/i18n/Italian.json'
    },
    rowCallback: function(row, data, index) {
      if (index % 2 === 0) {
        $(row).removeClass('even').removeClass('odd').addClass('even');
      } else {
        $(row).removeClass('even').removeClass('odd').addClass('odd');
      }
        //In grassetto la riga con"Totale Volontari"
      if (data.FullName === 'Totale volontari') {
        $(row).addClass('fw-bold');
        //Oppure solo la cella "Descrizione": $(row).find('td:eq(1)').css('font-weight', 'bold');
      }
    },
    stripeClasses: ['table-white', 'table-striped-anpas']
  });
});
</script>
@endpush
