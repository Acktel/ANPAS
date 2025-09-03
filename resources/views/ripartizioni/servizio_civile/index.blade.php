@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi <strong>Servizio Civile Nazionale</strong> – Anno {{ $anno }}
    </h1>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table id="table-servizio-civile" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
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
  const res = await fetch("{{ route('ripartizioni.servizio_civile.data') }}");
  let { data, labels } = await res.json();
  if (!data.length) return;

    // Sposta la riga totale in fondo
  const totaleRow = data.find(r => r.is_totale === -1);
  data = data.filter(r => r.is_totale !== -1);
  if (totaleRow) data.push(totaleRow);

  const table = $('#table-servizio-civile');

  const staticCols = [
    { key:'Associazione', label:'Associazione' },
    { key:'FullName',     label:'Descrizione' },
    { key:'OreTotali',    label:'Ore Totali Servizio Civile' }
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
    cols.push({ data:`${key}_ore`,     defaultContent: 0 });
    cols.push({ data:`${key}_percent`, defaultContent: 0 });
  });

  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    orderable: false,
    searchable: false,
    className: 'col-azioni',
    render: () => `
      <a href="{{ route('ripartizioni.servizio_civile.edit') }}" class="btn btn-warning btn-icon" title="Modifica">
        <i class="fas fa-edit"></i>
      </a>`
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
      url: '/js/i18n/Italian.json',
                      paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
    },
    rowCallback: function(row, data, index) {
      if (index % 2 === 0) {
        $(row).removeClass('even odd').addClass('even');
      } else {
        $(row).removeClass('even odd').addClass('odd');
      }
              //In grassetto la riga con"Totale Volontari"
      if (data.FullName === 'Totale servizio civile') {
        $(row).addClass('fw-bold');
        //Oppure solo la cella "Descrizione": $(row).find('td:eq(1)').css('font-weight', 'bold');
      }
    },
    stripeClasses: ['table-white', 'table-striped-anpas']
  });
});
</script>
@endpush
