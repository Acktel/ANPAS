@extends('layouts.app')

@php
use App\Http\Controllers\ConfigurazioneVeicoliController;

$user = auth()->user();
$selectedAssoc = session('associazione_selezionata') ?? $user->IdAssociazione;

{
    if ($key == 'vehicleTypes' && size$configVeicoli = ConfigurazioneVeicoliController::getConfigurazioneVeicoli();
$config = true;

foreach ($configVeicoli as $key => $value) of($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti i tipi di veicolo.';
        $link = route('configurazioni.veicoli');
        break;
    }
    if ($key == 'fuelTypes' && sizeof($value) <= 0) {
        $config = false;
        $testo = 'Mancano le configurazioni riguardanti i tipi di carburante.';
        $link = route('configurazioni.veicoli');
        break;
    }
}
@endphp

@section('content')
<div class="container-fluid container-margin">

  {{-- Titolo + Bottone --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Elenco Automezzi – Anno {{ $anno }}
    </h1>
    @if ($config)
      <a href="{{ route('automezzi.create') }}" class="btn btn-anpas-green">
        <i class="fas fa-plus me-1"></i> Nuovo Automezzo
      </a>
    @else
      <div class="text-end container-warning-create">
        <p class="mb-2 fw-bold">
          Non puoi aggiungere automezzi se prima<br>
          non completi le configurazioni:
        </p>
        <a href="{{ route('configurazioni.veicoli') }}" class="btn btn-warning">
          <i class="fas fa-cogs me-1"></i> Vai alle Configurazioni
        </a>
      </div>
    @endif
  </div>

  {{-- Filtro per associazione solo per ruoli elevati --}}
@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
<div class="mb-3" style="max-width:400px">
  <form id="assocFilterForm" class="w-100" method="GET">
    <div class="position-relative">
    <div class="input-group">
      <!-- Campo visibile -->
      <input
        id="assocSelectInput"
        class="form-control"
        autocomplete="off"
        placeholder="Seleziona associazione"
        value="{{ optional($associazioni->firstWhere('IdAssociazione', $selectedAssoc))->Associazione ?? '' }}"
        aria-label="Seleziona associazione"
      >

      <!-- Bottone dropdown -->
      <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-down"></i>
      </button>

      <!-- Campo hidden con id reale -->
      <input type="hidden" id="assocFilterHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
    </div>

    <!-- Dropdown -->
    <ul id="assocSelectDropdown" class="list-group position-absolute w-100"
        style="z-index:2000; display:none; max-height:240px; overflow:auto; background:#fff; top:100%; left:0;">
      @foreach($associazioni as $assoc)
        <li class="list-group-item assoc-item" data-id="{{ $assoc->IdAssociazione }}">
          {{ $assoc->Associazione }}
        </li>
      @endforeach
    </ul>
    </div>
  </form>
</div>
@endif


  {{-- Success message --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Messaggio di “no data” --}}
  <div id="noDataMessage" class="alert alert-info d-none">
    Nessun automezzo presente per l’anno {{ $anno }}.<br>
    Vuoi importare gli automezzi dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  {{-- Tabella in card --}}
  <div class="card-anpas mb-4 automezzi-card">
    <div class="card-body bg-anpas-white p-0">
      <table 
        id="automezziTable" 
        class="common-css-dataTable table table-hover table-striped-anpas table-bordered mb-0 w-100 table-bordered "
      >
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Associazione</th>
            <th>Anno</th>
            <th>Targa</th>
            <th>Codice ID</th>
            <th>Incluso Riparto</th>
            <th>Immatricolazione</th>
            <th>Anno d'Acquisto</th>
            <th>Modello</th>
            <th>Tipo Veicolo</th>
            <th>Km Rif.</th>
            <th>Km Totali</th>
            <th>Carburante</th>
            <th>Ult. Aut. Sanitaria</th>
            <th>Ult. Revisione</th>
            <th>Azioni</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
  
</div>
@endsection

@push('styles')
<link 
  rel="stylesheet" 
  href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" 
/>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script 
  src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"
></script>
<script 
  src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"
></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  // --- CUSTOM SELECT ---
  const form = document.getElementById('assocFilterForm');
  const input = document.getElementById('assocSelectInput');
  const dropdown = document.getElementById('assocSelectDropdown');
  const toggleBtn = document.getElementById('assocSelectToggleBtn');
  const hidden = document.getElementById('assocFilterHidden');

  // Controlla che gli elementi esistano prima di proseguire
  if (form && input && dropdown && toggleBtn && hidden) {

    const items = Array.from(dropdown.querySelectorAll('.assoc-item'))
                       .map(li => ({id: li.dataset.id, name: li.textContent.trim()}));

    function showDropdown() { dropdown.style.display='block'; toggleBtn.setAttribute('aria-expanded','true'); }
    function hideDropdown() { dropdown.style.display='none'; toggleBtn.setAttribute('aria-expanded','false'); }
    function filterDropdown(term) {
      term = (term||'').toLowerCase();
      dropdown.querySelectorAll('.assoc-item').forEach(li => {
        li.style.display = li.textContent.toLowerCase().includes(term) ? '' : 'none';
      });
    }

  function setSelection(id, name) {
    hidden.value = id;
    input.value = name;
    hideDropdown();
    // table.ajax.reload(); // aggiorna tabella senza ricaricare pagina

        // Aggiorna la sessione passando idAssociazione all’index
    const url = new URL(window.location.href);
    url.searchParams.set('idAssociazione', id);
    window.location.href = url; // ricarica la pagina con il filtro selezionato
  }

  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', () => setSelection(li.dataset.id, li.textContent.trim()));
  });

  input.addEventListener('input', () => filterDropdown(input.value));
  toggleBtn.addEventListener('click', () => dropdown.style.display==='block'?hideDropdown():showDropdown());
  document.addEventListener('click', e => { if(!form.contains(e.target)) hideDropdown(); });
   }

  // --- DATATABLE ---
  const table = $('#automezziTable').DataTable({
    processing: true,
    serverSide: false,
    stateDuration: -1,
    stateSave: true,  
    ajax: {
      url: '{{ route("automezzi.datatable") }}',
      data: function(d) {
        const selected = hidden ? hidden.value : null;
        if(selected) d.idAssociazione = selected;

        console.log('DataTable ajax payload:', d);
      }
    },
    columns: [
        { data: 'idAutomezzo' },
        { data: 'Associazione' },
        { data: 'idAnno' },
        { data: 'Targa' },
        { data: 'CodiceIdentificativo' },
        { data: 'incluso_riparto', render: data => data ? '✔️' : '❌' },
        { data: 'AnnoPrimaImmatricolazione' },
            { data: 'AnnoAcquisto', // ✅ nuovo campo
      render: function(data, type, row) {
        if (!data) return '';
        let info = row.informazioniAggiuntive ? row.informazioniAggiuntive : '';
        return `<span title="${info}">${data}</span>`;
      }
    },
//         { data: 'AnnoAcquisto',
// render: function(data, type, row) {
//     if (!data) return '';
//     let info = row.informazioniAggiuntive ? row.informazioniAggiuntive : '';
//     return `<span data-bs-toggle="tooltip" title="${info}">${data}</span>`;
// }
//         },
        { data: 'Modello' },
        { data: 'TipoVeicolo' },
        { data: 'KmRiferimento' },
        { data: 'KmTotali' },
        { data: 'TipoCarburante' },
        { data: 'DataUltimaAutorizzazioneSanitaria',
              render: function (data) {
                if (!data) return '';
                const date = new Date(data);
                return `${String(date.getDate()).padStart(2, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${date.getFullYear()}`;
              }
         },
        { data: 'DataUltimoCollaudo',
              render: function (data) {
                if (!data) return '';
                const date = new Date(data);
                return `${String(date.getDate()).padStart(2, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${date.getFullYear()}`;
              }
         },
        { data: 'Azioni', orderable: false, searchable: false, className: 'actions col-actions text-center' }
      ],
      language: {
        url: '/js/i18n/Italian.json',
                paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
      },
      stripeClasses: ['table-striped-anpas',''],
      rowCallback: function(row, data, index) {
        $(row).toggleClass('even odd', false).addClass(index % 2 === 0 ? 'even' : 'odd');
      }
    });

//     $('#automezziTable').on('draw.dt', function () {
//     var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
//     tooltipTriggerList.map(function (tooltipTriggerEl) {
//         return new bootstrap.Tooltip(tooltipTriggerEl);
//     });
// });

    // Mostra/Nascondi messaggio “no data”
    fetch("{{ route('automezzi.checkDuplicazione') }}")
      .then(res => res.json())
      .then(data => {
        if (data.mostraMessaggio) {
          document.getElementById('noDataMessage').classList.remove('d-none');
        }
      })
      .catch(console.error);

    // Duplica – “Sì”
    document.getElementById('btn-duplica-si')?.addEventListener('click', function() {
      const btn = this;
      btn.disabled = true;
      btn.innerText = 'Duplicazione…';

      fetch("{{ route('automezzi.duplica') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        }
      })
      .then(async res => {
        if (!res.ok) throw new Error((await res.json()).message);
        location.reload();
      })
      .catch(err => {
        alert(err.message || 'Errore duplicazione');
        btn.disabled = false;
        btn.innerText = 'Sì';
      });
    });

    // Duplica – “No”
    document.getElementById('btn-duplica-no')?.addEventListener('click', () => {
      document.getElementById('noDataMessage').classList.add('d-none');
    });

});
</script>

  <script>
    (function () {
      // cerca prima un elemento con id, altrimenti prende il primo .alert.alert-success
      const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
      if (!flash) return;

      // aspetta 3500ms (3.5s) poi fa fade + collapse e rimuove l'elemento
      setTimeout(() => {
        // animazione: opacità + altezza
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
        flash.style.opacity = '0';
        // per lo "slide up" imposta max-height e padding a 0
        flash.style.maxHeight = flash.scrollHeight + 'px'; // inizializza
        // forza repaint per sicurezza
        // eslint-disable-next-line no-unused-expressions
        flash.offsetHeight;
        flash.style.maxHeight = '0';
        flash.style.paddingTop = '0';
        flash.style.paddingBottom = '0';
        flash.style.marginTop = '0';
        flash.style.marginBottom = '0';

        // rimuovi dal DOM dopo che l'animazione è finita
        setTimeout(() => {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 600); // lascia un po' di tempo alla transizione
      }, 3500);
    })();
  </script>
@endpush