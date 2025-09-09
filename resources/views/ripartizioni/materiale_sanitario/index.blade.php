@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      TABELLA DI CALCOLO DELLE PERCENTUALI INERENTI IL NUMERO DEI SERVIZI SVOLTI AL FINE DELLA RIPARTIZIONE DEI COSTI DI OSSIGENO E MATERIALE SANITARIO − Anno {{ $anno }}
    </h1>
  </div>

  {{-- Select associazione (visibile solo a ruoli amministrativi) --}}
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
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
        <table id="table-materiale" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
          <thead class="thead-anpas">
            <tr>
              <th style="display: none;"></th> {{-- idAutomezzo --}}
              <th>Automezzo</th>
              <th>Targa</th>
              <th>Codice ID</th>
              <th>Incluso</th>
              @foreach($convenzioni as $conv)
                <th>{{ $conv->Convenzione }}</th>
              @endforeach
              <th>Totale</th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot class="table-light fw-bold">
            <tr></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * Helper: prende valore da oggetto tramite path tipo 'valori.123'
 */
function getValueByPath(obj, path) {
  if (!path) return undefined;
  const parts = path.split('.');
  let cur = obj;
  for (let p of parts) {
    if (cur == null) return undefined;
    // supporta anche 'valori[123]' stile se mai presente
    p = p.replace(/\[(\d+)\]/, '.$1');
    cur = cur[p];
  }
  return cur;
}

async function loadTableMateriale() {
  const table = $('#table-materiale');
  const selectedAssoc = (document.getElementById('assocSelectHidden')?.value || '').trim();

  let payload;
  try {
    const url = "{{ route('ripartizioni.materiale_sanitario.data') }}" + (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');
    const res = await fetch(url);
    payload = await res.json();
  } catch (err) {
    console.error('Errore fetch ripartizioni.materiale_sanitario.data:', err);
    return;
  }

  // payload.righe è un oggetto { id: riga, ... }
  const righe = payload?.righe || {};
  const convenzioni = payload?.convenzioni || [];

  // Estrai e rimuovi la riga dei totali
  let totaleRow = null;
  const data = Object.entries(righe).reduce((acc, [id, riga]) => {
    // consideriamo sia is_totale === true sia is_totale === -1
    if (riga.is_totale === true || riga.is_totale === -1) {
      totaleRow = {
        ...riga,
        idAutomezzo: parseInt(id, 10),
        totale_riga: riga.totale ?? 0
      };
      return acc;
    }

    acc.push({
      ...riga,
      idAutomezzo: parseInt(id, 10),
      totale_riga: riga.totale ?? 0
    });
    return acc;
  }, []);

  // Se c'è totaleRow e non è stato incluso nell'array, lo aggiungiamo in fondo dopo l'init di DataTable (drawCallback farà il push visivo)

  // Costruzione colonne per DataTable (deve corrispondere ai <th> in pagina)
  const columns = [
    { data: 'idAutomezzo', visible: false },
    { data: 'Automezzo' },
    { data: 'Targa' },
    { data: 'CodiceIdentificativo' },
    {
      data: 'incluso_riparto',
      render: function(val) { return val ? 'SI' : 'NO'; }
    }
  ];

  // colonne dinamiche per ogni convenzione: leggiamo idConvenzione da payload.convenzioni
  convenzioni.forEach(conv => {
    // useremo path 'valori.{idConvenzione}' per recuperarlo dall'oggetto riga
    columns.push({
      data: `valori.${conv.idConvenzione}`,
      className: 'valore-servizio',
      defaultContent: 0,
      render: function(val, type, row) {
        // se la riga NON è inclusa nel riparto, mostriamo 0
        return row.incluso_riparto ? (val || 0) : 0;
      }
    });
  });

  // ultima colonna Totale
  columns.push({
    data: 'totale_riga',
    className: 'totale-riga',
    render: function(val) { return val || 0; }
  });

  // distruggi eventuale DataTable esistente
  if ($.fn.DataTable.isDataTable(table)) table.DataTable().clear().destroy();

  // inizializzo DataTable
  table.DataTable({
    data,
    columns,
    paging: true,
    searching: false,
    ordering: false,
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
    rowCallback: function(rowEl, rowData, index) {
      if (rowData.is_totale === true || rowData.is_totale === -1) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },

    /**
     * drawCallback: aggiunge la riga "TOTALE" in fondo (visivamente)
     * e garantisce che non ci siano duplicati su ogni draw.
     */
    drawCallback: function(settings) {
      const api = this.api();
      // rimuovo eventuali righe totali precedenti
      $(api.table().body()).find('.totale-row').remove();

      if (!totaleRow) return;

      // costruisco la TR del totale
      const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

      // itero le colonne definite in `columns` per creare le celle (rispettando visible)
      for (let i = 0; i < columns.length; i++) {
        const col = columns[i];
        if (col.visible === false) continue;

        // prendo il valore dal totaleRow seguendo il path col.data (es: 'valori.123')
        const rawVal = getValueByPath(totaleRow, col.data);

        let cellValue = '';
        if (typeof col.render === 'function') {
          try {
            // chiamiamo il renderer con signature (data, type, row)
            cellValue = col.render(rawVal, 'display', totaleRow);
          } catch (e) {
            console.warn('Errore render colonna totale:', e);
            cellValue = rawVal ?? '';
          }
        } else if (col.data) {
          cellValue = rawVal ?? '';
        }

        $lastRow.append(`<td>${cellValue}</td>`);
      }

      // appendiamo la riga totale al body
      $(api.table().body()).append($lastRow);
    }
  });
}

/**
 * Setup custom select per associazioni (riutilizza gli stessi id usati negli altri template)
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
    loadTableMateriale(); // ricarica tabella filtrata
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
  setupCustomSelect(
    "assocSelectForm",
    "assocSelect",
    "assocSelectDropdown",
    "assocSelectToggleBtn",
    "assocSelectHidden"
  );

  loadTableMateriale();
});
</script>
@endpush
