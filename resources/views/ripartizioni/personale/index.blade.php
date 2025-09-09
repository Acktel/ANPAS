@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi personale dipendente (Autisti e Barellieri) − Anno {{ $anno }}
    </h1>
  </div>

        @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      {{-- action="{{ route('aziende_sanitarie.index') }}" --}}
      <form method="GET" id="assocSelectForm" class="w-100" style="max-width:400px">
        <div class="input-group">
          <!-- Campo visibile -->
          <input
            id="assocSelect"
            name="assocLabel"
            class="form-control"
            autocomplete="off"
            placeholder="Seleziona associazione"
            value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
            aria-label="Seleziona associazione"
          >

          <!-- Bottone per aprire/chiudere -->
          <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
            <i class="fas fa-chevron-down"></i>
          </button>

          <!-- Campo nascosto con l'id reale -->
          <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
        </div>

        <!-- Dropdown custom -->
            <ul id="assocSelectDropdown" class="list-group" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
                   background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
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
async function loadTableData() {
  const table = $('#table-ripartizione');
  const selectedAssoc = (document.getElementById('assocSelectHidden')?.value || '').trim();

  // fetch dati filtrati
  let payload;
  try {
    const url = "{{ route('ripartizioni.personale.data') }}" + (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');
    const res = await fetch(url);
    payload = await res.json();
  } catch (err) {
    console.error('Errore fetch ripartizioni/personale/data:', err);
    return;
  }

  let data = payload?.data || [];
  let labels = payload?.labels || {};

  // sposta riga TOTALE in fondo (la rimuoviamo dall'array dei dati e la teniamo in una variabile)
  const totIdx = data.findIndex(r => r.is_totale === -1);
  const totaleRow = totIdx >= 0 ? data.splice(totIdx, 1)[0] : null;

  // colonne statiche
  const staticCols = [
    { key: 'idDipendente', label: '', hidden: true },
    { key: 'Associazione', label: 'Associazione', hidden: false },
    { key: 'FullName', label: 'Dipendente', hidden: false },
    { key: 'OreTotali', label: 'Ore Totali', hidden: false },
    { key: 'is_totale', label: '', hidden: true },
  ];

  const convenzioni = Object.keys(labels).sort((a,b) => {
    const na = parseInt(a.replace(/^\D+/,'')) || 0;
    const nb = parseInt(b.replace(/^\D+/,'')) || 0;
    return na - nb;
  });

  let hMain = '', hSub = '';
  const cols = [];

  staticCols.forEach(col => {
    hMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    // assegno anche "name" identico alla chiave per il mapping sicuro
    cols.push({ data: col.key, name: col.key, visible: !col.hidden, defaultContent: '' });
  });

  convenzioni.forEach(k => {
    hMain += `<th colspan="2">${labels[k]}</th>`;
    hSub  += `<th>Ore Servizio</th><th>%</th>`;
    cols.push({ data: `${k}_ore`, name: `${k}_ore`, className: 'text-end', defaultContent: 0 });
    cols.push({ data: `${k}_percent`, name: `${k}_percent`, className: 'text-end', defaultContent: 0, render: d => (d ?? 0) });
  });

  // colonna azioni
  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    name: 'azioni',
    orderable: false,
    searchable: false,
    className: 'col-azioni text-center',
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
  $('#header-sub').html(hSub);
  $('#header-main th[colspan]').addClass('border-bottom-special');

  // distruggi tabella esistente se presente
  if ($.fn.DataTable.isDataTable(table)) {
    table.DataTable().clear().destroy(); 
  }

  // inizializzo DataTable — la footerCallback userà il mapping by-name
  const dt = table.DataTable({
    data,
    columns: cols,
    order: [],
    responsive: true,
    language: { url: '/js/i18n/Italian.json',
      paginate: {
        first: '<i class="fas fa-angle-double-left"></i>',
        last: '<i class="fas fa-angle-double-right"></i>',
        next: '<i class="fas fa-angle-right"></i>',
        previous: '<i class="fas fa-angle-left"></i>'
      }
    },
    stripeClasses: ['table-striped-anpas',''],
    rowCallback: (rowEl, rowData, index) => {
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },
    footerCallback: function(row, dataDrawn, start, end, display) {
      const api = this.api();

      // se non esiste il footer row, ricrealo con il numero di colonne effettivo
      if (! $(api.table().footer()).find('tr#totale-row-foot').length ) {
        $(api.table().footer()).empty();
        const $r = $('<tr id="totale-row-foot"></tr>');
        const totalCols = api.columns().count();
        for (let i = 0; i < totalCols; i++) $r.append('<td></td>');
        $(api.table().footer()).append($r);
      }

      // aggiorno le celle del footer mappando ogni col di `cols` tramite il suo name
      cols.forEach(c => {
        if (!c.name) return;
        // prendo la colonna con name=c.name
        const colApi = api.column(`${c.name}:name`);
        // se non la trova, skip
        if (!colApi || typeof colApi.index !== 'function') return;
        const idx = colApi.index();
        const $cell = $(api.column(idx).footer());
        if (!$cell.length) return;

        let html = '';
        if (totaleRow && typeof c.data === 'string') {
          let val = totaleRow[c.data] ?? '';
          if (c.data.endsWith('_percent') && val !== '') val = Number(val).toFixed(2);
          html = (val === null || val === undefined) ? '' : val;
        } else {
          html = '';
        }
        $cell.html(html);

        if (c.className) $cell.attr('class', c.className); else $cell.removeAttr('class');
      });

      // stili della riga
      if (totaleRow) {
        $(api.table().footer()).find('tr#totale-row-foot').addClass('table-warning fw-bold');
      } else {
        $(api.table().footer()).find('tr#totale-row-foot').removeClass('table-warning fw-bold');
      }
    }
  });

  // Dopo l'inizializzazione assicuro che il footer sia creato correttamente e faccio un primo draw
  dt.draw(false);
}


// carica la tabella al caricamento pagina
$(document).ready(() => loadTableData());
</script>

<script>
function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
  const form = document.getElementById(formId);
  const input = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  const toggleBtn = document.getElementById(toggleBtnId);
  const hidden = document.getElementById(hiddenId);
  if (!form || !input || !dropdown || !hidden) return;

  function showDropdown() { dropdown.style.display = 'block'; toggleBtn.setAttribute('aria-expanded','true'); }
  function hideDropdown() { dropdown.style.display = 'none'; toggleBtn.setAttribute('aria-expanded','false'); }

  function filterDropdown(term) {
    term = (term || '').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      li.style.display = (li.textContent || '').toLowerCase().includes(term) ? '' : 'none';
    });
  }

  function setSelection(id, name) {
    hidden.value = id ?? '';
    input.value = name ?? '';
    loadTableData(); // ricarica tabella filtrata
    hideDropdown();
  }

  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', () => setSelection(li.dataset.id, li.textContent.trim()));
  });

  input.addEventListener('input', () => filterDropdown(input.value));
  toggleBtn.addEventListener('click', () => {
    dropdown.style.display === 'block' ? hideDropdown() : showDropdown();
  });
  document.addEventListener('click', e => { if (!form.contains(e.target)) hideDropdown(); });
}

// attiva la select
setupCustomSelect(
  "assocSelectForm",
  "assocSelect",
  "assocSelectDropdown",
  "assocSelectToggleBtn",
  "assocSelectHidden"
);
</script>
@endpush