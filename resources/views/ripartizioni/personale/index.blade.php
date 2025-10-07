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
  <table id="table-ripartizione" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
    <thead class="thead-anpas">
      <tr id="header-main"></tr>
      <tr id="header-sub"></tr>
    </thead>
    <tbody></tbody>
    <tfoot>
      <tr id="totale-row-foot"></tr>
      <tr id="personale-row-foot"></tr>
    </tfoot>
  </table>
</div>



      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
async function loadTableData() {
  const $table = $('#table-ripartizione');
  const selectedAssoc = (document.getElementById('assocSelectHidden')?.value || '').trim();

  // ===== fetch dati tabella (ore / %) =====
  let payload;
  try {
    const url = "{{ route('ripartizioni.personale.data') }}" + (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');
    const res = await fetch(url);
    payload = await res.json();
  } catch (err) {
    console.error('Errore fetch ripartizioni/personale/data:', err);
    return;
  }

  let data   = payload?.data   || [];
  let labels = payload?.labels || {}; // { c{idConv} : Nome }

  // stacco riga totale
  const totIdx    = data.findIndex(r => r.is_totale === -1);
  const totaleRow = totIdx >= 0 ? data.splice(totIdx, 1)[0] : null;

  // colonne statiche
  const staticCols = [
    { key: 'idDipendente', label: '',             hidden: true  },
    { key: 'Associazione', label: 'Associazione', hidden: false },
    { key: 'FullName',     label: 'Dipendente',   hidden: false },
    { key: 'OreTotali',    label: 'Ore Totali',   hidden: false },
    { key: 'is_totale',    label: '',             hidden: true  },
  ];

  const convenzioni = Object.keys(labels).sort((a,b) => {
    const na = parseInt(a.replace(/^\D+/,'')) || 0;
    const nb = parseInt(b.replace(/^\D+/,'')) || 0;
    return na - nb;
  });

  let hMain = '', hSub = '';
  const cols = [];

  // header + statiche
  staticCols.forEach(col => {
    hMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    cols.push({ data: col.key, visible: !col.hidden, defaultContent: '', className: 'text-center' });
  });

  // convenzioni
  convenzioni.forEach(k => {
    hMain += `<th colspan="2">${labels[k]}</th>`;
    hSub  += `<th>Ore Servizio</th><th>%</th>`;
    cols.push({ data: `${k}_ore`,     className: 'text-end', defaultContent: 0 });
    cols.push({ data: `${k}_percent`, className: 'text-end', defaultContent: 0, render: d => (d ?? 0) });
  });

  // azioni
  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null, orderable: false, searchable: false, className: 'col-azioni text-center',
    render: row => row.is_totale === -1 ? '' : `
      <a href="/ripartizioni/personale/${row.idDipendente}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza"><i class="fas fa-eye"></i></a>
      <a href="/ripartizioni/personale/${row.idDipendente}/edit" class="btn btn-warning me-1 btn-icon" title="Modifica"><i class="fas fa-edit"></i></a>`
  });

  // header
  $('#header-main').html(hMain);
  $('#header-sub').html(hSub);

  // assicura <tfoot> con 2 righe
  const tableEl = $table.get(0);
  if (tableEl && !tableEl.tFoot) tableEl.createTFoot();
  const $tfoot  = $($table.get(0).tFoot);
  if (!$tfoot.find('#totale-row-foot').length)    $tfoot.append('<tr id="totale-row-foot"></tr>');
  if (!$tfoot.find('#personale-row-foot').length) $tfoot.append('<tr id="personale-row-foot"></tr>');

  // distruggi DT se presente
  if ($.fn.DataTable.isDataTable($table)) $table.DataTable().clear().destroy();

  // variabili condivise per footerCallback
  const convIdByKey = {};
  Object.keys(labels).forEach(k => { convIdByKey[k] = parseInt(k.replace(/^c/,'')) || 0; });

  const dt = $table.DataTable({
    stateDuration: -1,
    stateSave: true, 
    data,
    columns: cols,
    order: [],
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
    stripeClasses: ['table-striped-anpas',''],
    rowCallback: (rowEl, rowData, index) => {
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },

    footerCallback: async function () {
      const api = this.api();
      const settings = api.settings()[0];

      // colonne visibili (indici reali)
      const visIdxArr = api.columns(':visible').indexes().toArray();
      const visibleCount = visIdxArr.length;
      const $tfoot = $($table.get(0).tFoot);
      const $totRow  = $tfoot.find('#totale-row-foot');
      const $persRow = $tfoot.find('#personale-row-foot');

      // sync # celle con # colonne visibili
      const syncCells = ($row) => {
        const diff = visibleCount - $row.children('td').length;
        if (diff > 0) for (let i = 0; i < diff; i++) $row.append('<td></td>');
        if (diff < 0) for (let i = 0; i < -diff; i++) $row.children('td').last().remove();
      };
      syncCells($totRow); syncCells($persRow);

      const firstPos = 0; // prima cella visibile dove scrivere l'etichetta

      // ===== riga TOTALE =====
      visIdxArr.forEach((realIdx, pos) => {
        const meta = settings.aoColumns[realIdx] || {};
        const dataKey = meta.mData || meta.data;
        const $cell = $totRow.children('td').eq(pos);

        let html = (pos === firstPos) ? 'TOTALE' : '';
        if (totaleRow && typeof dataKey === 'string') {
          let val = totaleRow[dataKey];
          if (val !== undefined && val !== null && val !== '') {
            if (dataKey.endsWith('_percent')) val = Number(val).toFixed(2);
            if (pos !== firstPos) html = val;
          }
        }
        $cell.html(html);
        if (meta.sClass || meta.className) $cell.attr('class', meta.sClass || meta.className);
        if (pos === firstPos) $cell.removeClass('text-end').addClass('text-start');
        else                  $cell.removeClass('text-start').addClass('text-end');
      });
      $totRow.toggleClass('table-warning fw-bold', !!totaleRow);

      // ===== riga COSTO PERSONALE (per convenzione) =====
      let perConv = {}, totalePers = 0;
      try {
        const urlPers = "{{ route('distinta.imputazione.personale_per_convenzione') }}" + (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');
        const resPers = await fetch(urlPers);
        const jsonPers = await resPers.json();
        perConv    = jsonPers?.per_conv || {};   // [idConv => importo]
        totalePers = jsonPers?.totale   || 0;
      } catch (e) {
        console.error('Errore fetch personale-per-convenzione', e);
      }

      visIdxArr.forEach((realIdx, pos) => {
        const meta = settings.aoColumns[realIdx] || {};
        const dataKey = meta.mData || meta.data;
        const $cell = $persRow.children('td').eq(pos);

        let html = (pos === firstPos) ? 'COSTO PERSONALE' : '';

        if (typeof dataKey === 'string') {
          if (dataKey === 'OreTotali') {
            html = Number(totalePers || 0).toFixed(2);
          } else if (dataKey.endsWith('_percent')) {
            const key = dataKey.replace('_percent',''); // es. 'c12'
            const idC = convIdByKey[key];
            if (idC && perConv[idC] != null) html = Number(perConv[idC]).toFixed(2);
          }
        }

        $cell.html(html);
        if (meta.sClass || meta.className) $cell.attr('class', meta.sClass || meta.className);
        if (pos === firstPos) $cell.removeClass('text-end').addClass('text-start');
        else                  $cell.removeClass('text-start').addClass('text-end');
      });

      $persRow.addClass('fw-bold');
      api.columns.adjust(); // aggiusta larghezze se Responsive ha cambiato il layout
    }
  });

  dt.draw(false);
}

// load iniziale
$(document).ready(() => loadTableData());

// dropdown associazione
function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
  const form     = document.getElementById(formId);
  const input    = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  const toggle   = document.getElementById(toggleBtnId);
  const hidden   = document.getElementById(hiddenId);
  if (!form || !input || !dropdown || !hidden) return;

  function showDd(){ dropdown.style.display='block'; toggle.setAttribute('aria-expanded','true'); }
  function hideDd(){ dropdown.style.display='none';  toggle.setAttribute('aria-expanded','false'); }
  function filterDd(t){
    const s=(t||'').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li=>{
      li.style.display=(li.textContent||'').toLowerCase().includes(s)?'':'none';
    });
  }
  function setSelection(id,name){
    hidden.value=id??''; input.value=name??''; hideDd(); loadTableData();
  }

  dropdown.querySelectorAll('.assoc-item').forEach(li=>{
    li.style.cursor='pointer';
    li.addEventListener('click',()=>setSelection(li.dataset.id,(li.textContent||'').trim()));
  });
  input.addEventListener('input',()=>filterDd(input.value));
  toggle.addEventListener('click',()=>dropdown.style.display==='block'?hideDd():showDd());
  document.addEventListener('click',e=>{ if(!form.contains(e.target)) hideDd(); });
}
setupCustomSelect("assocSelectForm","assocSelect","assocSelectDropdown","assocSelectToggleBtn","assocSelectHidden");
</script>
@endpush
