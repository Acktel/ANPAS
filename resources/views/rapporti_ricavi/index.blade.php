{{-- resources/views/rapporti_ricavi/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Rapporto ricavi per convenzione — Anno {{ session('anno_riferimento', now()->year) }}
    </h1>
  </div>

  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="d-flex mb-3">
      <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="me-3">
        @csrf
        <select id="assocSelect" name="idAssociazione" class="form-select" onchange="this.form.submit()">
          @foreach(($associazioni ?? collect()) as $assoc)
            <option value="{{ $assoc->idAssociazione }}" {{ (int)($selectedAssoc ?? 0) === (int)$assoc->idAssociazione ? 'selected' : '' }}>
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
        <table id="table-ricavi" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
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
  const eur  = v => new Intl.NumberFormat('it-IT', { style:'currency', currency:'EUR' }).format(Number(v||0));
  const perc = v => (Number(v||0)).toLocaleString('it-IT', { minimumFractionDigits:2, maximumFractionDigits:2 }) + '%';

  const selectedAssoc = document.getElementById('assocSelect')?.value || null;
  const baseEdit = "{{ url('/rapporti-ricavi') }}";

  // carico dati (se la rotta accetta idAssociazione lo passo, altrimenti viene ignorato)
  const url = new URL("{{ route('rapporti-ricavi.datatable') }}", window.location.origin);
  if (selectedAssoc) url.searchParams.set('idAssociazione', selectedAssoc);

  const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
  if (!res.ok) return;

  let { data = [], labels = {} } = await res.json();
  if (!Array.isArray(data)) data = [];

  // chiavi convenzioni ordinate per id (labels = { c{id}: 'Nome' })
  const convenzioni = Object.keys(labels).sort((a,b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

  // intestazioni e definizione colonne
  let hMain = '', hSub = '', cols = [];

  // colonne fisse
  const staticCols = [
    { key: 'Associazione',    label: 'Associazione',                 cls: 'text-start' },
    { key: 'TotaleEsercizio', label: "Totale ricavi dell'esercizio", cls: 'text-end', render: d => eur(d) },
  ];

  staticCols.forEach(col => {
    hMain += `<th rowspan="2" class="${col.cls||''}">${col.label}</th>`;
    cols.push({
      data: col.key,
      className: col.cls || '',
      render: col.render || (d => d)
    });
  });

  // per ogni convenzione 2 colonne (Rimborso / %)
  convenzioni.forEach(k => {
    hMain += `<th colspan="2">${labels[k]}</th>`;
    hSub   += `<th>Rimborso</th><th>%</th>`;

    cols.push({
      data: `${k}_rimborso`,
      className: 'text-end',
      defaultContent: 0,
      render: d => eur(d)
    });
    cols.push({
      data: `${k}_percent`,
      className: 'text-end',
      defaultContent: 0,
      render: d => perc(d)
    });
  });

  // colonna azioni (Modifica)
  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    orderable: false,
    searchable: false,
    className: 'col-azioni',
    render: (row) => {
      const id = row.idAssociazione || '';
      if (!id || row.is_totale === -1) return '';
      return `
        <a href="${baseEdit}/${id}/edit"
           class="btn btn-warning btn-icon"
           title="Modifica">
          <i class="fas fa-edit"></i>
        </a>`;
    }
  });

  // header in pagina
  $('#header-main').html(hMain);
  $('#header-main th').each(function(){
    if ($(this).attr('colspan')) $(this).addClass('border-bottom-special');
  });
  $('#header-sub').html(hSub);

  // riga TOTALE (somma e % globali) — calcolata sul dataset corrente
  if (data.length) {
    const totRow = { Associazione: 'Totale', TotaleEsercizio: 0, is_totale: -1 };
    const sumBy = key => data.reduce((s, r) => s + Number(r[key] || 0), 0);

    totRow.TotaleEsercizio = sumBy('TotaleEsercizio');
    convenzioni.forEach(k => {
      const s = sumBy(`${k}_rimborso`);
      totRow[`${k}_rimborso`] = s;
      totRow[`${k}_percent`]  = totRow.TotaleEsercizio > 0 ? (s / totRow.TotaleEsercizio * 100) : 0;
    });

    data.push(totRow);
  }

  // DataTable
  $('#table-ricavi').DataTable({
    data,
    columns: cols,
    order: [],
    paging: false,
    searching: false,
    info: false,
    responsive: true,
    language: { url: '/js/i18n/Italian.json' },
    rowCallback: (rowEl, rowData, index) => {
      if (rowData.is_totale === -1) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    }
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
