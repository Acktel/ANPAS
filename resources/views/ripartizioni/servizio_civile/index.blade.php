@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi <strong>Servizio Civile Nazionale</strong> – Anno {{ $anno }}
    </h1>
  </div>

  {{-- Select associazione (visibile solo a ruoli amministrativi) --}}
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" id="assocSelectForm" class="w-100" style="max-width:400px">
        <div class="position-relative">
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
        <ul id="assocSelectDropdown" class="list-group position-absolute w-100" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
               background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
          @foreach($associazioni as $assoc)
            <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
              {{ $assoc->Associazione }}
            </li>
          @endforeach
        </ul>
        </div>
      </form>
    </div>
  @endif

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
/**
 * Carica i dati della tabella (con supporto filtro per idAssociazione via hidden input)
 */
async function loadTableDataServizioCivile() {
  const table = $('#table-servizio-civile');
  const selectedAssoc = (document.getElementById('assocSelectHidden')?.value || '').trim();

    // distruggi tabella precedente se esiste
  if ($.fn.DataTable.isDataTable(table)) table.DataTable().clear().destroy();

  // fetch dati filtrati (aggiunge query param idAssociazione se presente)
  let payload;
  try {
    const url = "{{ route('ripartizioni.servizio_civile.data') }}" + (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');
    const res = await fetch(url);
    payload = await res.json();
  } catch (err) {
    console.error('Errore fetch ripartizioni.servizio_civile.data:', err);
    return;
  }

  let data = payload?.data || [];
  let labels = payload?.labels || {};
  if (!data.length) table.DataTable().clear().destroy();;

  // Sposta la riga totale in fondo, se presente (convenzione is_totale === -1)
  const totaleRowIndex = data.findIndex(r => r.is_totale === -1);
  if (totaleRowIndex !== -1) {
    const [totaleRow] = data.splice(totaleRowIndex, 1);
    data.push(totaleRow);
  }

  const staticCols = [
    { key:'Associazione', label:'Associazione' },
    { key:'FullName',     label:'Descrizione' },
    { key:'OreTotali',    label:'Unità totali Servizio Civile' } //ore totali
  ];

  const convenzioni = Object.keys(labels).sort((a,b)=>parseInt(a.slice(1)) - parseInt(b.slice(1)));

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
  // aggiunge classe alle th con colspan per gestire bordo inferiore speciale
  $('#header-main th[colspan]').addClass('border-bottom-special');
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
      }
    },
    stripeClasses: ['table-white','table-striped-anpas'],
    rowCallback: function(row, data, index) {
      $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');

      // in grassetto la riga totale (testo previsto: "Totale servizio civile")
      if (data.FullName === 'Totale servizio civile') {
        $(row).addClass('fw-bold');
      }
    }
  });
}

/**
 * Setup custom select per associazioni (riutilizza gli stessi id usati nel template volontari)
 */
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
    loadTableDataServizioCivile(); // ricarica tabella filtrata
    hideDropdown();
  }

  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', () => setSelection(li.dataset.id, li.textContent.trim()));
  });

  input.addEventListener('input', () => filterDropdown(input.value));
  toggleBtn.addEventListener('click', () => dropdown.style.display==='block'?hideDropdown():showDropdown());
  document.addEventListener('click', e => { if (!form.contains(e.target)) hideDropdown(); });
}

// inizializzazione al ready
$(document).ready(function() {
  // attiva select (se presente nel DOM)
  setupCustomSelect(
    "assocSelectForm",
    "assocSelect",
    "assocSelectDropdown",
    "assocSelectToggleBtn",
    "assocSelectHidden"
  );

  // carica dati tabella
  loadTableDataServizioCivile();
});
</script>
@endpush
