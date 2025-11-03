@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Ripartizione costi personale <strong>volontario</strong> − Anno {{ $anno }}
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
            aria-label="Seleziona associazione">

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
        <table id="table-rip-volontari" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
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
    const table = $('#table-rip-volontari');
    const selectedAssoc = (document.getElementById('assocSelectHidden')?.value || '').trim();

    // distruggi tabella esistente
    if ($.fn.DataTable.isDataTable(table)) {
      table.DataTable().clear().destroy();
    }

    // fetch dati filtrati
    let payload;
    try {
      const url = "{{ route('ripartizioni.volontari.data') }}" + (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');
      const res = await fetch(url);
      payload = await res.json();
    } catch (err) {
      console.error('Errore fetch ripartizioni.volontari.data:', err);
      return;
    }

    let data = payload?.data || [];
    let labels = payload?.labels || {};

    // se non ho dati, esco pulito
    if (!data.length) {
      table.DataTable({
        data: [],
        columns: [],
        paging: false,
        searching: false,
        info: false
      });
      return;
    }

    // Sposta la riga totale in fondo, se presente (FullName === 'Totale volontari' o is_totale === -1)
    const totIdx = data.findIndex(r => r.is_totale === -1 || r.FullName === 'Totale volontari');
    if (totIdx !== -1) {
      const [tot] = data.splice(totIdx, 1);
      data.push(tot);
    }

    const staticCols = [{
        key: 'Associazione',
        label: 'Associazione'
      },
      {
        key: 'FullName',
        label: 'Descrizione'
      },
      {
        key: 'OreTotali',
        label: 'Personale Volontario'
      }
    ];

    const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

    let hMain = '',
      hSub = '',
      cols = [];

    // colonne statiche
    staticCols.forEach(c => {
      hMain += `<th rowspan="2">${c.label}</th>`;
      cols.push({
        data: c.key
      });
    });

    // colonne dinamiche per convenzione
    convenzioni.forEach(key => {
      hMain += `<th colspan="2">${labels[key]}</th>`;
      hSub += `<th>personale</th><th>%</th>`;
      cols.push({
        data: null,
        defaultContent: 0,
        render: (row) => {
          const kOre = `${key}_ore`;
          const kPers = `${key}_personale`;
          const val = row[kOre] ?? row[kPers] ?? 0;
          return Number(val).toLocaleString('it-IT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        }
      });
      cols.push({
        data: `${key}_percent`,
        defaultContent: 0,
        render: (v) => Number(v ?? 0).toLocaleString('it-IT', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        })
      });
    });

    // **Aggiungi colonna Azioni** (come nel template Servizio Civile)
    hMain += `<th rowspan="2">Azioni</th>`;
    cols.push({
      data: null,
      orderable: false,
      searchable: false,
      className: 'col-azioni',
      render: () => `
      <a href="{{ route('ripartizioni.volontari.edit') }}" class="btn btn-warning btn-icon" title="Modifica">
        <i class="fas fa-edit"></i>
      </a>`
    });

    // render header
    $('#header-main').html(hMain);
    $('#header-main th[colspan]').addClass('border-bottom-special');
    $('#header-sub').html(hSub);

    // init DataTable
    table.DataTable({
      stateDuration: -1,
      stateSave: true,
      data,
      columns: cols,
      paging: false,
      searching: false,
      info: false,
      responsive: true,
      language: {
        url: '/js/i18n/Italian.json'
      },
      stripeClasses: ['table-white', 'table-striped-anpas'],
      rowCallback: function(row, data, index) {
        $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
        if (data.FullName === 'Totale volontari' || data.is_totale === -1) {
          $(row).addClass('fw-bold');
        }
      }
    });
  }

  // carica la tabella al caricamento pagina
  $(document).ready(() => loadTableData());
</script>
@endpush