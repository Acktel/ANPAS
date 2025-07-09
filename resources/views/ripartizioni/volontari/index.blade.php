@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Ripartizione costi personale <strong>volontario</strong> − Anno {{ $anno }}
  </h1>

  <div class="table-responsive">
    <table id="table-rip-volontari" class="table table-bordered w-100 text-center align-middle">
      <thead class="table-light">
        <tr id="header-main"></tr>
        <tr id="header-sub"></tr>
      </thead>
      <tbody></tbody>
    </table>
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

  const convenzioni = Object.keys(labels)
    .sort((a,b)=>parseInt(a.slice(1))-parseInt(b.slice(1)));

  let hMain='', hSub='', cols=[];

  staticCols.forEach(c=>{
    hMain += `<th rowspan="2">${c.label}</th>`;
    cols.push({ data:c.key });
  });

  convenzioni.forEach(key=>{
    hMain += `<th colspan="2">${labels[key]}</th>`;
    hSub  += `<th>Ore</th><th>%</th>`;
    cols.push({ data:`${key}_ore`,     defaultContent:0 });
    cols.push({ data:`${key}_percent`, defaultContent:0 });
  });

  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    orderable: false,
    searchable: false,
    render: () => {
      return `
        <a href="{{ route('ripartizioni.volontari.edit') }}" class="btn btn-sm btn-warning">
          <i class="fas fa-edit"></i> Modifica
        </a>`;
    }
  });

  $('#header-main').html(hMain);
  $('#header-sub').html(hSub);

  table.DataTable({
    data,
    columns: cols,
    paging: false,
    searching: false,
    info: false,
    responsive: true,
    language:{ url:'//cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json' }
  });
});
</script>
@endpush
