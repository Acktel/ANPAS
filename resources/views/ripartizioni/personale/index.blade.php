@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi personale dipendente (Autisti e Barellieri) − Anno {{ $anno }}
    </h1>
  </div>

@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
  <div class="d-flex mb-3">
    <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="me-3">
      @csrf

      <div class="position-relative" style="max-width:420px;">
        <div class="input-group">
          {{-- campo visibile con datalist --}}
          <input
            id="assocInput"
            name="assocLabel"
            class="form-control"
            {{-- list="assocList" --}}
            autocomplete="off"
            placeholder="Cerca o seleziona associazione"
            value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
            aria-label="Cerca o seleziona associazione"
          >

          {{-- bottone per <th>Anno d'acquisto</th>aprire/chiudere la tendina custom --}}
          <button type="button" id="assocToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
            <i class="fas fa-chevron-down"></i>
          </button>

          {{-- campo nascosto che contiene l'id reale (invia come prima) --}}
          <input type="hidden" id="idAssociazione" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
        </div>

        {{-- datalist nativo (per suggerimenti durante la digitazione) --}}
        <datalist id="assocList">
          @foreach($associazioni as $assoc)
            <option data-id="{{ $assoc->idAssociazione }}" value="{{ $assoc->Associazione }}"></option>
          @endforeach
        </datalist>

        {{-- tendina custom (usata per la selezione a tendina completa e per il filtraggio) --}}
        <ul id="assocDropdown" class="list-group position-absolute w-100 shadow-sm"
                style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
           background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
          @foreach($associazioni as $assoc)
            <li class="list-group-item list-group-item-action assoc-item" data-id="{{ $assoc->idAssociazione }}" role="option">
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
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
$(async function () {
  // ---------- ASSOCIAZIONI: gestione input + datalist + dropdown ----------
  const assocForm = document.getElementById('assocFilterForm');
  const assocInput = document.getElementById('assocInput');
  const assocDropdown = document.getElementById('assocDropdown'); // ul custom
  const assocToggleBtn = document.getElementById('assocToggleBtn');
  const idAssociazione = document.getElementById('idAssociazione');

  // Sposta la riga totale in fondo
  const totaleRow = data.find(r => r.is_totale === -1);
  data = data.filter(r => r.is_totale !== -1);
  if (totaleRow) data.push(totaleRow);

  if (!assocForm || !assocInput || !assocDropdown || !idAssociazione) {
    console.warn('assoc filter: elementi mancanti nel DOM (assocForm/assocInput/assocDropdown/idAssociazione)');
  } else {
    // costruisci lista affidabile di {id, name} dai <li>
    const items = Array.from(assocDropdown.querySelectorAll('.assoc-item'))
      .map(li => ({ id: String(li.dataset.id), name: (li.textContent || '').trim() }));


  const staticCols = [   
    { key: 'idDipendente', label: '',               hidden: true  },
    { key: 'Associazione', label: 'Associazione',   hidden: false },
    { key: 'FullName',     label: 'Dipendente',     hidden: false },
    { key: 'OreTotali',    label: 'Ore Totali',     hidden: false },
    { key: 'is_totale',    label: '',               hidden: true  },
  ];


  const convenzioni = Object.keys(labels).sort((a,b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

  let hMain = '', hSub = '', cols = [];

  staticCols.forEach(col => {
    hMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    cols.push({ data: col.key, visible: !col.hidden });
  });


  convenzioni.forEach(key => {
    hMain += `<th colspan="2">${labels[key]}</th>`;
    hSub   += `<th>Ore Servizio</th><th>%</th>`;
    cols.push({ data: `${key}_ore`, defaultContent: 0 });
    cols.push({ data: `${key}_percent`, defaultContent: 0 });
  });


  hMain += `<th rowspan="2">Azioni</th>`;
  cols.push({
    data: null,
    orderable: false,
    searchable: false,
    className: 'col-azioni',
    render: row => {
      if (row.is_totale === -1) return '';
      return `
        <a href="/ripartizioni/personale/${row.idDipendente}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza">
          <i class="fas fa-eye"></i>
        </a>
        <a href="/ripartizioni/personale/${row.idDipendente}/edit" class="btn btn-warning me-1 btn-icon" title="Modifica">
          <i class="fas fa-edit"></i>
        </a>`;
    // crea mappa nameLower -> array di id (per gestire duplicati)
    const mapNameToIds = {};
    items.forEach(it => {
      const key = it.name.toLowerCase();
      if (!mapNameToIds[key]) mapNameToIds[key] = [];
      mapNameToIds[key].push(it.id);
    });

    console.log('assoc: mapNameToIds', mapNameToIds);

    // helper show/hide
    function showDropdown() {
      assocDropdown.style.display = 'block';
      assocToggleBtn.setAttribute('aria-expanded', 'true');
    }
    function hideDropdown() {
      assocDropdown.style.display = 'none';
      assocToggleBtn.setAttribute('aria-expanded', 'false');
    }
    function toggleDropdown() {
      if (assocDropdown.style.display === 'block') hideDropdown();
      else {
        filterDropdown('');
        showDropdown();
        assocInput.focus();
      }
    }

    // filtro: mostra solo gli elementi che contengono il termine
    function filterDropdown(term) {
      term = (term || '').toLowerCase();
      assocDropdown.querySelectorAll('.assoc-item').forEach(li => {
        const txt = (li.textContent || '').toLowerCase();
        li.style.display = txt.indexOf(term) === -1 ? 'none' : '';
      });
    }

    // imposta il campo nascosto e il campo visibile (submit opzionale)
    function setSelection(id, name, submit = false) {
      console.log('assoc: setSelection', { id, name, submit });
      idAssociazione.value = id ?? '';
      assocInput.value = name ?? '';
      if (submit) assocForm.submit();
    }

    // click su ogni elemento: setta id e invia subito (come comportamento select onchange)
    assocDropdown.querySelectorAll('.assoc-item').forEach(li => {
      li.style.cursor = 'pointer';
      li.addEventListener('click', function () {
        const id = String(this.dataset.id);
        const name = this.textContent.trim();
        setSelection(id, name, true);
      });
    });

    // quando l'utente scrive: filtra la lista. non submit automatico a meno che ci sia un unico id esatto.
    assocInput.addEventListener('input', function () {
      const v = assocInput.value.trim();
      filterDropdown(v);

      const ids = mapNameToIds[v.toLowerCase()] || [];
      console.log('assoc: input changed', { value: v, matchedIds: ids });

      if (v.length === 0) {
        idAssociazione.value = '';
      } else if (ids.length === 1) {
        // imposta id ma non submit automatico (riduce race condition)
        idAssociazione.value = ids[0];
      } else {
        idAssociazione.value = '';
      }
    });

    // Enter: se c'è un id valido invia, altrimenti apri dropdown
    assocInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        const v = assocInput.value.trim();
        const ids = mapNameToIds[v.toLowerCase()] || [];
        console.log('assoc: Enter pressed', { value: v, matchedIds: ids });
        if (ids.length === 1) {
          e.preventDefault();
          setSelection(ids[0], v, true);
        } else if (ids.length > 1) {
          e.preventDefault();
          filterDropdown(v);
          showDropdown();
        } else {
          e.preventDefault();
          showDropdown();
        }
      }
      if (e.key === 'Escape') {
        hideDropdown();
      }
    });

    // Toggle button
    assocToggleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      toggleDropdown();
    });

    // Click fuori: nascondi
    document.addEventListener('click', function (e) {
      if (!assocForm.contains(e.target)) {
        hideDropdown();
      }
    });

    // imposta label iniziale se esiste id selezionato dal backend
    (function setInitialLabelFromId() {
      const currentId = String(idAssociazione.value || '').trim();
      if (!currentId) return;
      const found = items.find(it => it.id === currentId);
      if (found) assocInput.value = found.name;
      console.log('assoc: initial selection', { currentId, found });
    })();
  } // end assoc handlers


  // ---------- FETCH DATI E DataTable (modificata per leggere selectedAssoc dall'id nascosto) ----------
  // prendi selectedAssoc dal campo nascosto (lo imposta il backend se c'era una selezione)
  const selectedAssoc = document.getElementById('idAssociazione')?.value || null;

  try {
    const res = await fetch("{{ route('ripartizioni.personale.data') }}" + (selectedAssoc ? `?idAssociazione=${selectedAssoc}` : ''));
    let { data, labels } = await res.json();
    if (!data || !data.length) return;

    // Sposta la riga totale in fondo
    const totaleRow = data.find(r => r.is_totale === -1);
    data = data.filter(r => r.is_totale !== -1);

    const table = $('#table-ripartizione');

    const staticCols = [
      { key: 'idDipendente', label: '',               hidden: true  },
      { key: 'Associazione', label: 'Associazione',   hidden: false },
      { key: 'FullName',     label: 'Dipendente',     hidden: false },
      { key: 'OreTotali',    label: 'Ore Totali',     hidden: false },
      { key: 'is_totale',    label: '',               hidden: true  },
    ];

    const convenzioni = Object.keys(labels).sort((a,b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

    let hMain = '', hSub = '', cols = [];

    staticCols.forEach(col => {
      hMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
      cols.push({ data: col.key, visible: !col.hidden });
    });

    convenzioni.forEach(key => {
      hMain += `<th colspan="2">${labels[key]}</th>`;
      hSub   += `<th>Ore Servizio</th><th>%</th>`;
      cols.push({ data: `${key}_ore`, defaultContent: 0 });
      cols.push({ data: `${key}_percent`, defaultContent: 0 });
    });

    hMain += `<th rowspan="2">Azioni</th>`;
    cols.push({
      data: null,
      orderable: false,
      searchable: false,
      className: 'col-azioni',
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
    $('#header-main th').each(function() {
      if ($(this).attr('colspan')) {
        $(this).addClass('border-bottom-special');
      }
    });
    $('#header-sub').html(hSub);

    table.DataTable({
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
        },
      },
      rowCallback: (rowEl, rowData, index) => {
        if (rowData.is_totale === -1) {
          $(rowEl).addClass('table-warning fw-bold');
        }
        $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
      },
      stripeClass: ['table-striped-anpas'],
      drawCallback: function(settings) {
        const api = this.api();
        const pageRows = api.rows({ page: 'current' }).nodes();

        // Rimuovi eventuali righe "TOTALE" precedenti
        $(pageRows).filter('.totale-row').remove();

        // Aggiungi la riga TOTALE alla fine della pagina
        if (totaleRow) {
          const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

          api.columns().every(function(index) {
            const col = api.column(index);
            if (!col.visible()) return;

            let cellValue = '';
            const colData = col.dataSrc();

            if (typeof colData === 'function') {
              cellValue = colData(totaleRow);
            } else {
              cellValue = totaleRow[colData] ?? '';
            }

            $lastRow.append(`<td>${cellValue}</td>`);
          });

     //   $(api.table().body()).append($lastRow);
    }
}
          $(api.table().body()).append($lastRow);
        }
      }
    });

  } catch (err) {
    console.error('Errore fetch ripartizioni/personale/data:', err);
  }

}); // end $(async function)
</script>
@endpush
