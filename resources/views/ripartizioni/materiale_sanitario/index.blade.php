@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      TABELLA DI CALCOLO DELLE PERCENTUALI INERENTI IL NUMERO DEI SERVIZI SVOLTI AL FINE DELLA RIPARTIZIONE DEI COSTI DI OSSIGENO E MATERIALE SANITARIO − Anno {{ $anno }}
    </h1>
  </div>

  {{-- Selezione associazione (solo ruoli amministrativi) --}}
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" id="assocSelectForm" class="w-100" style="max-width:400px">
        <div class="position-relative">
          <div class="input-group">
            {{-- Campo visibile --}}
            <input
              id="assocSelect"
              name="assocLabel"
              class="form-control"
              autocomplete="off"
              placeholder="Seleziona associazione"
              value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
              aria-label="Seleziona associazione"
            >

            {{-- Bottone toggle --}}
            <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
              <i class="fas fa-chevron-down"></i>
            </button>

            {{-- Id reale nascosto --}}
            <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
          </div>

          {{-- Dropdown custom --}}
          <ul id="assocSelectDropdown" class="list-group position-absolute w-100"
              style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0; background-color:#fff;">
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
        <style>
          /* il th “idAutomezzo” deve esistere per DataTables; lo rimpiccioliamo */
          th.th-id-automezzo { width:1px; min-width:1px; max-width:1px; padding:0; border:none; }
        </style>

        <table id="table-materiale" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
          <thead class="thead-anpas">
            <tr>
              {{-- NON nascondere con display:none: DataTables deve contarla --}}
              <th class="th-id-automezzo" rowspan="2"></th> {{-- idAutomezzo (header "fantasma") --}}
              <th rowspan="2">Targa</th>
              <th rowspan="2">Codice ID</th>
              <th rowspan="2">Incluso</th>

              {{-- intestazioni per convenzione (gruppo di 2 colonne) --}}
              @foreach($convenzioni as $conv)
                <th colspan="2" class="text-center">{{ $conv->Convenzione }}</th>
              @endforeach

              <th rowspan="2">Totale</th>
            </tr>
            <tr>
              @foreach($convenzioni as $conv)
                <th>N. servizi svolti</th>
                <th>%</th>
              @endforeach
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
/* ------- helpers ------- */
function getValueByPath(obj, path) {
  if (!path) return undefined;
  const parts = String(path).split('.');
  let cur = obj;
  for (let p of parts) {
    if (cur == null) return undefined;
    p = p.replace(/\[(\d+)\]/, '.$1');
    cur = cur[p];
  }
  return cur;
}

/* ------- init tabella ------- */
async function loadTableMateriale() {
  const tableEl = $('#table-materiale');
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

  const righe = payload?.righe || {};
  const convenzioni = payload?.convenzioni || [];

  // Estrai eventuale riga "Totale"
  let totaleRow = null;
  const data = Object.entries(righe).reduce((acc, [id, riga]) => {
    if (riga.is_totale === true || riga.is_totale === -1) {
      totaleRow = { ...riga, idAutomezzo: parseInt(id, 10), totale_riga: riga.totale ?? 0 };
      return acc;
    }
    acc.push({ ...riga, idAutomezzo: parseInt(id, 10), totale_riga: riga.totale ?? 0 });
    return acc;
  }, []);

  // definizione colonne (4 fisse + 2*convenzioni + 1 finale)
  const columns = [
    { data: 'idAutomezzo', visible: false }, // la TH esiste ma la colonna è nascosta da DT
    { data: 'Targa' },
    { data: 'CodiceIdentificativo' },
    {
      data: 'incluso_riparto',
      render: function (val) { return val ? 'SI' : 'NO'; }
    }
  ];

  convenzioni.forEach(conv => {
    const path = `valori.${conv.idConvenzione}`;

    // N. servizi
    columns.push({
      data: path,
      className: 'text-end',
      defaultContent: 0,
      render: function (val, type, row) {
        return row.incluso_riparto ? (val || 0) : 0;
      }
    });

    // %
    columns.push({
      data: path,
      className: 'text-end',
      isPercent: true,  // flag usato solo nella riga totale
      render: function (val, type, row) {
        const tot = Number(row?.totale_riga ?? 0);
        const base = row.incluso_riparto ? Number(val || 0) : 0;
        if (tot <= 0) return (type === 'sort' || type === 'type') ? 0 : '0.00';
        const pct = (base / tot) * 100;
        return (type === 'sort' || type === 'type') ? pct : pct.toFixed(2);
      }
    });
  });

  // Totale riga
  columns.push({
    data: 'totale_riga',
    className: 'text-end',
    render: val => val || 0
  });

  // reset DataTable se già presente
  if ($.fn.DataTable.isDataTable(tableEl)) tableEl.DataTable().clear().destroy();

  // init DataTable
  tableEl.DataTable({
    autoWidth: false,       // evita ricalcoli su colonne complesse
    stateDuration: -1,
    stateSave: true,
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
    rowCallback: function (rowEl, rowData, index) {
      if (rowData.is_totale === true || rowData.is_totale === -1) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },

    // Append visivamente la riga TOTALE in fondo
    drawCallback: function () {
      const api = this.api();
      $(api.table().body()).find('.totale-row').remove();

      if (!totaleRow) return;

      const $tr = $('<tr>').addClass('table-warning fw-bold totale-row');

      // costruzione celle in base alle colonne dichiarate
      for (let i = 0; i < columns.length; i++) {
        const col = columns[i];
        if (col.visible === false) continue; // idAutomezzo non visibile

        const rawVal = col.data ? getValueByPath(totaleRow, col.data) : undefined;
        let cellValue = '';

        if (col.isPercent) {
          const tot = Number(totaleRow?.totale_riga ?? 0);
          const base = Number(rawVal ?? 0);
          const pct = tot > 0 ? (base / tot) * 100 : 0;
          cellValue = pct.toFixed(2);
        } else if (col.data && String(col.data).startsWith('valori.')) {
          cellValue = rawVal ?? 0;
        } else if (typeof col.render === 'function') {
          try { cellValue = col.render(rawVal, 'display', totaleRow); }
          catch { cellValue = rawVal ?? ''; }
        } else if (col.data) {
          cellValue = rawVal ?? '';
        } else {
          cellValue = '';
        }

        $tr.append(`<td>${cellValue}</td>`);
      }

      // prima colonna visibile (Targa) come etichetta "TOTALE"
      const firstVisibleTd = $tr.find('td').get(0);
      if (firstVisibleTd) firstVisibleTd.innerText = 'TOTALE';

      $(api.table().body()).append($tr);
    }
  });
}

/* ------- select custom associazioni ------- */
function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
  const form = document.getElementById(formId);
  const input = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  const toggleBtn = document.getElementById(toggleBtnId);
  const hidden = document.getElementById(hiddenId);
  if (!form || !input || !dropdown || !hidden) return;

  function showDropdown() { dropdown.style.display = 'block'; toggleBtn.setAttribute('aria-expanded','true'); }
  function hideDropdown() { dropdown.style.display = 'none';  toggleBtn.setAttribute('aria-expanded','false'); }
  function filterDropdown(term) {
    term = (term || '').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      li.style.display = (li.textContent || '').toLowerCase().includes(term) ? '' : 'none';
    });
  }
  function setSelection(id, name) {
    hidden.value = id ?? '';
    input.value = name ?? '';
    form.submit();           
    hideDropdown();
  }

  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', () => setSelection(li.dataset.id, li.textContent.trim()));
  });

  input.addEventListener('input', () => filterDropdown(input.value));
  toggleBtn.addEventListener('click', () => dropdown.style.display === 'block' ? hideDropdown() : showDropdown());
  document.addEventListener('click', e => { if (!form.contains(e.target)) hideDropdown(); });
}

/* ------- ready ------- */
$(document).ready(function () {
  setupCustomSelect("assocSelectForm","assocSelect","assocSelectDropdown","assocSelectToggleBtn","assocSelectHidden");
  loadTableMateriale();
});
</script>
@endpush
