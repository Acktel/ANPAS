@extends('layouts.app')

@php
  $user = Auth::user();
  $isImpersonating = session()->has('impersonate');
  $sezioniBilancioEdit = [5,6,8,9,10,11];

  // $selectedAssoc arriva dal controller; fallback d’emergenza
  $selectedAssoc = $selectedAssoc
    ?? session('associazione_selezionata')
    ?? request('idAssociazione')
    ?? ($user?->IdAssociazione ?? null);
@endphp

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Distinta Imputazione Costi − Anno {{ $anno }}
    </h1>
  </div>

  @if($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating)
  <div class="mb-3">
    {{-- Selettore associazione: usa GET su index con ?idAssociazione=... --}}
    <form method="GET" action="{{ route('distinta.imputazione.index') }}" id="assocForm" class="w-100 position-relative" style="max-width:400px">
      <div class="input-group">
        <input
          id="assocSelect"
          name="assocLabel"
          class="form-control"
          autocomplete="off"
          placeholder="Seleziona associazione"
          value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
          aria-label="Seleziona associazione"
          aria-haspopup="listbox"
          aria-expanded="false"
          role="combobox"
        >
        <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
          <i class="fas fa-chevron-down"></i>
        </button>
        <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
      </div>

      <ul id="assocSelectDropdown"
          class="list-group shadow-sm"
          style="z-index:2000; display:none; max-height:240px; overflow:auto; position:absolute; width:100%; top:100%; left:0; background-color:#fff;">
        @foreach($associazioni as $assoc)
          <li tabindex="0" class="list-group-item assoc-item" data-id="{{ (int)$assoc->idAssociazione }}">
            {{ $assoc->Associazione }}
          </li>
        @endforeach
      </ul>
    </form>
  </div>
  @endif

  @php
  $sezioni = [
    2 => 'Automezzi',
    3 => 'Attrezzatura Sanitaria',
    4 => 'Telecomunicazioni',
    5 => 'Costi gestione struttura',
    6 => 'Costo del personale',
    7 => 'Materiale sanitario di consumo',
    8 => 'Costi amministrativi',
    9 => 'Quote di ammortamento',
    10 => 'Beni Strumentali inferiori a 516,00 euro',
    11 => 'Altri costi'
  ];
  @endphp

  <div class="accordion" id="accordionDistinta">
    @foreach ($sezioni as $id => $titolo)
    <div class="accordion-item mb-2">
      <h2 class="accordion-header" id="heading-{{ $id }}">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapse-{{ $id }}" aria-expanded="false" aria-controls="collapse-{{ $id }}">
          <div class="row w-100 text-start gx-2">
            <div class="col-6 fw-bold">{{ $titolo }}</div>
            <div class="col-2" id="summary-bilancio-{{ $id }}">Importo Totale da Bilancio Consuntivo:-</div>
            <div class="col-2" id="summary-diretta-{{ $id }}">Costi di Diretta Imputazione (Netti):-</div>
            <div class="col-2" id="summary-totale-{{ $id }}">Totale Costi Ripartiti (Indiretti):-</div>
          </div>
        </button>
      </h2>
      <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionDistinta">
        <div class="accordion-body">
          <div class="mb-2 text-end">
            <a href="{{ $selectedAssoc ? route('distinta.imputazione.create', ['sezione' => $id, 'idAssociazione' => $selectedAssoc]) : '#' }}"
               class="btn btn-sm btn-anpas-green p-2 me-2 {{ $selectedAssoc ? '' : 'disabled' }}"
               @if(!$selectedAssoc) tabindex="-1" aria-disabled="true" @endif>
              <i class="fas fa-plus me-1"></i>Aggiungi Costi
            </a>

            @if(in_array($id, $sezioniBilancioEdit, true))
              <a href="{{ $selectedAssoc ? route('distinta.imputazione.editBilancio', ['sezione' => $id, 'idAssociazione' => $selectedAssoc]) : '#' }}"
                 class="btn btn-sm btn-warning p-2 {{ $selectedAssoc ? '' : 'disabled' }}"
                 @if(!$selectedAssoc) tabindex="-1" aria-disabled="true" @endif>
                <i class="fas fa-pen me-1"></i> Modifica Importi da Bilancio
              </a>
            @endif
          </div>

          <div class="table-responsive">
            <table id="table-distinta-{{ $id }}" class="common-css-dataTable table table-hover table-striped-anpas table-bordered w-100 mb-0">
              <thead class="thead-anpas">
                <tr id="header-main-{{ $id }}">
                  <th rowspan="2">Voce</th>
                  <th rowspan="2">Importo Totale da Bilancio Consuntivo</th>
                  <th rowspan="2">Costi di Diretta Imputazione (Netti)</th>
                  <th rowspan="2">Totale Costi Ripartiti (Indiretti)</th>
                </tr>
                <tr id="header-sub-{{ $id }}"></tr>
              </thead>
              <tbody class="sortable" data-sezione="{{ $id }}"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    @endforeach

    <div class="accordion-item mt-4">
      <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
        <div class="row w-100 text-start gx-2">
          <div class="col-6">Totale generale</div>
          <div class="col-2" id="tot-bilancio">Importo Totale da Bilancio Consuntivo: </div>
          <div class="col-2" id="tot-diretta">Costi di Diretta Imputazione (Netti): </div>
          <div class="col-2" id="tot-totale">Totale Costi Ripartiti (Indiretti): </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.distintaCosti = {
  sezioni: @json($sezioni),
  csrf: '{{ csrf_token() }}',
  selectedAssoc: {{ $selectedAssoc ? (int)$selectedAssoc : 'null' }},
};

function fmt2(n){ n = Number(n||0); return Number.isFinite(n) ? n.toFixed(2) : '0.00'; }

$(function () {
  AnpasLoader.show();
  const intestazioniAggiunte = new Set();

  // Passo idAssociazione all’API /data
  const dataUrl = (function(){
    const base = '{{ route("distinta.imputazione.data") }}';
    const idA  = window.distintaCosti.selectedAssoc;
    return idA ? (base + '?idAssociazione=' + encodeURIComponent(idA)) : base;
  })();

  $.ajax({
    url: dataUrl,
    method: 'GET',
    success: function (response) {
      if (!response) return;

      const convMap = (function(c){
        if (!c) return {};
        if (Array.isArray(c)) {
          const m={}; c.forEach((name,idx)=> m[String(idx)] = String(name)); return m;
        }
        const out={}; Object.keys(c).forEach(k => out[String(k)] = String(c[k])); return out;
      })(response.convenzioni);

      const convIds   = Object.keys(convMap);
      const convNames = convIds.map(id => convMap[id]);

      const righe = Array.isArray(response.data) ? response.data : [];

      const totaliGenerali = { bilancio: 0, diretta: 0, totale: 0 };
      const totaliPerSezione = {};

      Object.keys(window.distintaCosti.sezioni).forEach(idSezione => {
        const headerMain = $(`#header-main-${idSezione}`);
        const headerSub  = $(`#header-sub-${idSezione}`);

        if (!intestazioniAggiunte.has(idSezione)) {
          convNames.forEach(name => {
            headerMain.append(`<th colspan="3" class="text-center">${$('<div>').text(name).html()}</th>`);
            headerSub.append(`
              <th class="text-center">Diretti</th>
              <th class="text-center">Sconto</th>
              <th class="text-center">Indiretti</th>
            `);
          });
          intestazioniAggiunte.add(idSezione);
        }

        totaliPerSezione[idSezione] = { bilancio: 0, diretta: 0, totale: 0 };
      });

      righe.forEach(riga => {
    const idSezione = riga.sezione_id || riga.sezione || riga.idSezione;
    if (!idSezione) return;

    const $tbody = $(`tbody[data-sezione="${idSezione}"]`);
    if ($tbody.length === 0) return;

    // ===== TESTO HOVER (standard, affidabile) =====
    const hoverText =
        'Voce: ' + (riga.voce ?? '-') + '\n';

    let html = `
        <tr title="${$('<div>').text(hoverText).html()}">
            <td>${$('<div>').text(riga.voce ?? '').html()}</td>
            <td class="text-end">${fmt2(riga.bilancio)}</td>
            <td class="text-end">${fmt2(riga.diretta)}</td>
            <td class="text-end">${fmt2(riga.totale)}</td>
    `;

    convIds.forEach(cid => {
        const cname = convMap[cid];

        const cellById   = riga[cid]   || (riga.per_conv && riga.per_conv[cid]);
        const cellByName = riga[cname] || (riga.per_conv && riga.per_conv[cname]);

        const cell = cellById || cellByName || {};
        const diretti = Number(cell.diretti ?? cell.diretta ?? 0);
        const amm     = Number(cell.ammortamento ?? 0);
        const ind     = Number(cell.indiretti ?? cell.indiretto ?? 0);

        html += `<td class="text-end">${fmt2(diretti)}</td>`;
        html += `<td class="text-end">${fmt2(amm)}</td>`;
        html += `<td class="text-end">${fmt2(ind)}</td>`;
    });

    html += `</tr>`;
    $tbody.append(html);

    // ===== TOTALI =====
    totaliPerSezione[idSezione].bilancio += Number(riga.bilancio || 0);
    totaliPerSezione[idSezione].diretta  += Number(riga.diretta  || 0);
    totaliPerSezione[idSezione].totale   += Number(riga.totale   || 0);

    totaliGenerali.bilancio += Number(riga.bilancio || 0);
    totaliGenerali.diretta  += Number(riga.diretta  || 0);
    totaliGenerali.totale   += Number(riga.totale   || 0);
});


      Object.keys(totaliPerSezione).forEach(id => {
        $(`#summary-bilancio-${id}`).text('Importo Totale da Bilancio Consuntivo:  '+fmt2(totaliPerSezione[id].bilancio));
        $(`#summary-diretta-${id}`).text('Costi di Diretta Imputazione (Netti):  '+fmt2(totaliPerSezione[id].diretta));
        $(`#summary-totale-${id}`).text('Totale Costi Ripartiti (Indiretti):  '+fmt2(totaliPerSezione[id].totale));
      });

      $('#tot-bilancio').text('Importo Totale da Bilancio Consuntivo:  '+fmt2(totaliGenerali.bilancio));
      $('#tot-diretta').text('Costi di Diretta Imputazione (Netti):  '+fmt2(totaliGenerali.diretta));
      $('#tot-totale').text('Totale Costi Ripartiti (Indiretti):  '+fmt2(totaliGenerali.totale));
      AnpasLoader.hide();
    },
    error: function (xhr) {
      console.error("Errore caricamento distinta costi", xhr);
    }
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('assocSelect');
  const toggleBtn = document.getElementById('assocSelectToggleBtn');
  const dropdown = document.getElementById('assocSelectDropdown');
  const hidden = document.getElementById('assocSelectHidden');
  const form = document.getElementById('assocForm');
  const items = () => Array.from(dropdown.querySelectorAll('.assoc-item'));
  let highlighted = -1;

  function openDropdown() {
    dropdown.style.display = 'block';
    input.setAttribute('aria-expanded', 'true');
    toggleBtn.setAttribute('aria-expanded', 'true');
  }
  function closeDropdown() {
    dropdown.style.display = 'none';
    input.setAttribute('aria-expanded', 'false');
    toggleBtn.setAttribute('aria-expanded', 'false');
    clearHighlight();
  }
  function clearHighlight() {
    items().forEach(i => i.classList.remove('active'));
    highlighted = -1;
  }
  function highlight(index) {
    clearHighlight();
    const list = items();
    if (index >= 0 && index < list.length) {
      list[index].classList.add('active');
      list[index].scrollIntoView({ block: 'nearest' });
      highlighted = index;
    }
  }

  toggleBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    (dropdown.style.display === 'block') ? closeDropdown() : openDropdown();
  });

  input.addEventListener('input', function () {
    const filter = input.value.trim().toLowerCase();
    let anyVisible = false;
    items().forEach(item => {
      if (item.textContent.toLowerCase().includes(filter)) {
        item.style.display = '';
        anyVisible = true;
      } else {
        item.style.display = 'none';
      }
    });
    anyVisible ? openDropdown() : closeDropdown();
  });

  input.addEventListener('keydown', function (e) {
    const visibleItems = items().filter(i => i.style.display !== 'none');
    if (!visibleItems.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      const next = Math.min(highlighted + 1, visibleItems.length - 1);
      const all = items();
      const idx = all.indexOf(visibleItems[next]);
      highlight(idx);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      const prev = Math.max(highlighted - 1, 0);
      const all = items();
      const idx = all.indexOf(visibleItems[prev] || visibleItems[0]);
      highlight(idx);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (highlighted >= 0) chooseItem(items()[highlighted]);
      else if (visibleItems.length === 1) chooseItem(visibleItems[0]);
      else {
        const exact = visibleItems.find(i => i.textContent.trim().toLowerCase() === input.value.trim().toLowerCase());
        if (exact) chooseItem(exact);
      }
    } else if (e.key === 'Escape') {
      closeDropdown();
    }
  });

  function chooseItem(item) {
    if (!item) return;
    const id = item.getAttribute('data-id');
    hidden.value = id;
    // redirect con ?idAssociazione=...
    window.location = '{{ route('distinta.imputazione.index') }}' + '?idAssociazione=' + encodeURIComponent(id);
  }

  items().forEach(item => {
    item.addEventListener('click', function (ev) {
      ev.stopPropagation();
      chooseItem(this);
    });
    item.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        chooseItem(this);
      }
    });
  });

  document.addEventListener('click', function (e) {
    if (!form.contains(e.target)) closeDropdown();
  });
  
});
</script>
@endpush
