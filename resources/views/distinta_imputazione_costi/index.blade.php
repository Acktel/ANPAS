@extends('layouts.app')

@php
$user = Auth::user();
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
    <form method="POST" action="{{ route('sessione.setAssociazione') }}" id="assocForm" class="w-100 position-relative" style="max-width:400px">
      @csrf
      <div class="input-group">
        <!-- Campo visibile -->
        <input
          id="assocSelect"
          name="assocLabel"
          class="form-control"
          autocomplete="off"
          placeholder="Seleziona associazione"
          value="{{ optional($associazioni->firstWhere('idAssociazione', session('associazione_selezionata')))->Associazione ?? '' }}"
          aria-label="Seleziona associazione"
          aria-haspopup="listbox"
          aria-expanded="false"
          role="combobox"
        >

        <!-- Bottone per aprire/chiudere -->
        <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
          <i class="fas fa-chevron-down"></i>
        </button>

        <!-- Campo nascosto con l'id reale -->
        <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ session('associazione_selezionata') ?? '' }}">
      </div>

      <!-- Dropdown custom -->
      <ul id="assocSelectDropdown"
          class="list-group shadow-sm"
          style="z-index:2000; display:none; max-height:240px; overflow:auto; position:absolute; width:100%; top:100%; left:0; background-color:#fff;">
        @foreach($associazioni as $assoc)
          <li tabindex="0" class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
            {{ $assoc->Associazione }}
          </li>
        @endforeach
      </ul>
    </form>
  </div>
  @endif

  @php
  $sezioni = [
    2 => 'Automezzi e Attrezzature Sanitarie',
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
            <div class="col-2" id="summary-bilancio-{{ $id }}">-</div>
            <div class="col-2" id="summary-diretta-{{ $id }}">-</div>
            <div class="col-2" id="summary-totale-{{ $id }}">-</div>
          </div>
        </button>
      </h2>
      <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionDistinta">
        <div class="accordion-body">
          <div class="mb-2 text-end">
            <a href="{{ route('distinta.imputazione.create', ['sezione' => $id]) }}" class="btn btn-sm btn-anpas-green p-2">
              <i class="fas fa-plus me-1"></i>Aggiungi Costi diretti
            </a>
          </div>

          <div class="table-responsive">
            <table id="table-distinta-{{ $id }}" class="common-css-dataTable table table-hover table-striped-anpas table-bordered w-100 mb-0">
              <thead class="thead-anpas">
                <tr id="header-main-{{ $id }}">
                  <th rowspan="2">Voce</th>
                  <th rowspan="2">Importo Totale da Bilancio Consuntivo</th>
                  <th rowspan="2">Costi di Diretta Imputazione</th>
                  <th rowspan="2">Totale Costi Ripartiti</th>
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
          <div class="col-2" id="tot-bilancio">-</div>
          <div class="col-2" id="tot-diretta">-</div>
          <div class="col-2" id="tot-totale">-</div>
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
  csrf: '{{ csrf_token() }}'
};

$(function () {
  const intestazioniAggiunte = new Set();

  $.ajax({
    url: '{{ route("distinta.imputazione.data") }}',
                    paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
    method: 'GET',
    success: function (response) {
      const convenzioni = response.convenzioni;
      const righe = response.data;

      const totaliGenerali = { bilancio: 0, diretta: 0, totale: 0 };
      const totaliPerSezione = {};

      Object.keys(window.distintaCosti.sezioni).forEach(idSezione => {
        const headerMain = $(`#header-main-${idSezione}`);
        const headerSub = $(`#header-sub-${idSezione}`);

        if (!intestazioniAggiunte.has(idSezione)) {
          convenzioni.forEach(conv => {
            headerMain.append(`<th colspan="2" class="text-center">${conv}</th>`);
            headerSub.append(`<th class="text-center">Diretti</th><th class="text-center">Indiretti</th>`);
          });
          intestazioniAggiunte.add(idSezione);
        }

        totaliPerSezione[idSezione] = { bilancio: 0, diretta: 0, totale: 0 };
      });

      righe.forEach(riga => {
        const idSezione = riga.sezione_id;
        if (!idSezione) return;

        const $tbody = $(`tbody[data-sezione="${idSezione}"]`);
        if ($tbody.length === 0) return;

        let html = `<tr>
          <td>${riga.voce}</td>
          <td class="text-end">${riga.bilancio?.toFixed(2) ?? '0.00'}</td>
          <td class="text-end">${riga.diretta?.toFixed(2) ?? '0.00'}</td>
          <td class="text-end">${riga.totale?.toFixed(2) ?? '0.00'}</td>`;

        Object.keys(convenzioni).forEach(key => {
          const valore = riga[convenzioni[key]] || {};
          const diretti = valore.diretti ?? 0;
          const indiretti = valore.indiretti ?? 0;

          html += `<td class="text-end">${parseFloat(diretti).toFixed(2)}</td>`;
          html += `<td class="text-end">${parseFloat(indiretti).toFixed(2)}</td>`;
        });

        html += `</tr>`;
        $tbody.append(html);

        totaliPerSezione[idSezione].bilancio += parseFloat(riga.bilancio || 0);
        totaliPerSezione[idSezione].diretta += parseFloat(riga.diretta || 0);
        totaliPerSezione[idSezione].totale += parseFloat(riga.totale || 0);

        totaliGenerali.bilancio += parseFloat(riga.bilancio || 0);
        totaliGenerali.diretta += parseFloat(riga.diretta || 0);
        totaliGenerali.totale += parseFloat(riga.totale || 0);
      });

      Object.keys(totaliPerSezione).forEach(id => {
        $(`#summary-bilancio-${id}`).text(totaliPerSezione[id].bilancio.toFixed(2));
        $(`#summary-diretta-${id}`).text(totaliPerSezione[id].diretta.toFixed(2));
        $(`#summary-totale-${id}`).text(totaliPerSezione[id].totale.toFixed(2));
      });

      $('#tot-bilancio').text(totaliGenerali.bilancio.toFixed(2));
      $('#tot-diretta').text(totaliGenerali.diretta.toFixed(2));
      $('#tot-totale').text(totaliGenerali.totale.toFixed(2));
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

    // Toggle dropdown
    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (dropdown.style.display === 'block') closeDropdown();
        else openDropdown();
    });

    // Filter list on input
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
        if (anyVisible) openDropdown(); else closeDropdown();
    });

    // Keyboard navigation
    input.addEventListener('keydown', function (e) {
        const visibleItems = items().filter(i => i.style.display !== 'none');
        if (!visibleItems.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = Math.min(highlighted + 1, visibleItems.length - 1);
            // map highlighted to index within all items
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
            if (highlighted >= 0) {
                const chosen = items()[highlighted];
                chooseItem(chosen);
            } else if (visibleItems.length === 1) {
                chooseItem(visibleItems[0]);
            } else {
                // optionally submit if exact match found
                const exact = visibleItems.find(i => i.textContent.trim().toLowerCase() === input.value.trim().toLowerCase());
                if (exact) chooseItem(exact);
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    // click selection
    function chooseItem(item) {
        if (!item) return;
        input.value = item.textContent.trim();
        hidden.value = item.getAttribute('data-id');
        closeDropdown();
        // submit the form
        form.submit();
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

    // click outside closes the dropdown
    document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) {
            closeDropdown();
        }
    });

    // keep dropdown position/width in sync if window resized
    window.addEventListener('resize', function () {
        // nothing specific for now, but placeholder if needed
    });
});
</script>
@endpush