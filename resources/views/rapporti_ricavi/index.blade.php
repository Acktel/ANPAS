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

  // 1) colonne statiche
  const staticCols = [
    { key: 'idAssociazione',  label: '',                     hidden: true  },
    { key: 'Associazione',    label: 'Associazione',         hidden: false },
    { key: 'TotaleEsercizio', label: 'Totale Ricavi Esercizio', hidden: false },
  ];

  // 2) convenzioni dinamiche
  const convenzioni = Object.keys(labels)
    .sort((a,b)=>parseInt(a.slice(1)) - parseInt(b.slice(1)));

  // 3) montiamo header e colonne
  let hMain='', hSub='', cols=[];

  staticCols.forEach(col=>{
    hMain += `<th rowspan="2"${col.hidden?' style="display:none"':''}>${col.label}</th>`;
    cols.push({ data: col.key, visible: !col.hidden });
  });

  convenzioni.forEach(key=>{
    hMain += `<th colspan="2">${labels[key]}</th>`;
    hSub  += `<th>Rimborso</th><th>%</th>`;
    cols.push({ data: `${key}_rimborso`, defaultContent:'0,00' });
    cols.push({ data: `${key}_percent`,  defaultContent:0 });
  });

  // 4) colonna Azioni
  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data:null, orderable:false, searchable:false,
    render: row=>{
      return `
        <a href="/rapporti-ricavi/${row.idAssociazione}" class="btn btn-sm btn-info me-1">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/rapporti-ricavi/${row.idAssociazione}/edit" class="btn btn-sm btn-warning me-1">
          <i class="fas fa-edit"></i>
        </a>
        <form method="POST" action="/rapporti-ricavi/${row.idAssociazione}" class="d-inline-block"
              onsubmit="return confirm('Eliminare i dati?')">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">
          <input type="hidden" name="_method" value="DELETE">
          <button class="btn btn-sm btn-danger">
            <i class="fas fa-trash-alt"></i>
          </button>
        </form>
      `;
    }
  });

  $('#header-main').html(hMain);
  $('#header-sub').html(hSub);

  // 5) inizializzo DataTable senza forzare alcun ordinamento sul “totale”
  table.DataTable({
    data,
    columns: cols,
    responsive: true,
    language: { url: '/js/i18n/Italian.json',
                      paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
     }
  });
});
</script>
@endpush
