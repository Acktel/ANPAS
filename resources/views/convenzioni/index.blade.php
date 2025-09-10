@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Convenzioni − Anno {{ $anno }}
  </h1>

  @if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="d-flex mb-3">
    {{-- Se ci sono associazioni in $associazioni (solo per SuperAdmin/Admin/Supervisor) --}}
@if(!empty($associazioni) && $associazioni->isNotEmpty())
  <div class="mb-3">
    <form id="assocFilterForm" method="GET" class="w-100">
      <div class="position-relative">
      <div class="input-group">
        <!-- Campo visibile -->
        <input
          id="assocFilterInput"
          name="assocLabel"
          class="form-control"
          autocomplete="off"
          placeholder="Seleziona associazione"
          value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
          aria-label="Seleziona associazione"
        >

        <!-- Bottone per aprire/chiudere -->
        <button type="button" id="assocFilterToggleBtn" class="btn btn-outline-secondary"
                aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
          <i class="fas fa-chevron-down"></i>
        </button>

        <!-- Campo nascosto con l'id reale -->
        <input type="hidden" id="assocFilterHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
      </div>

      <!-- Dropdown custom -->
      <ul id="assocFilterDropdown" class="list-group position-absolute w-100"
          style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
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


    <div class="ms-auto">
      @can('manage-all-associations')
      @if(!session()->has('impersonate'))
      <a href="{{ route('convenzioni.create') }}" class="btn btn-anpas-green">
        <i class="fas fa-plus me-1"></i> Nuova Convenzione
      </a>
      @endif
      @endcan
    </div>
  </div>


  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna convenzione presente per l’anno {{ $anno }}.<br>
    Vuoi importare le convenzioni dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white p-0">
      <table id="convenzioniTable"
             class="common-css-dataTable table table-hover table-stripe-anpas table-bordered dt-responsive nowrap mb-0 table-striped-anpas">
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Anno</th>
            <th>Descrizione</th>
            <th>Aziende sanitarie</th>
            <th>Materiali di consumo</th>
            <th data-orderable="false" class="col-actions text-center">Azioni</th>
          </tr>
        </thead>
        <tbody id="sortable-convenzioni" class="sortable">
          @forelse($convenzioni as $c)
            <tr data-id="{{ $c->idConvenzione }}">
              <td>{{ $c->idConvenzione }}</td>
              <td>{{ $c->idAnno }}</td>
              <td>{{ $c->Convenzione }}</td>
              <td>{{ $c->AziendeSanitarie }}</td>
              <td>{{ $c->MaterialeSanitario }}</td>
              <td class="text-center align-middle">
                <a href="{{ route('convenzioni.edit', $c->idConvenzione) }}"
                   class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
                  <i class="fas fa-edit"></i>
                </a>
                <form action="{{ route('convenzioni.destroy', $c->idConvenzione) }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Eliminare questa convenzione?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-3">Nessuna convenzione.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<!-- Sortable CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>


<script>
  document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;


    // Inizializza DataTable solo se ci sono righe sufficienti
    if ($('#convenzioniTable tbody tr').length > 0 &&
      $('#convenzioniTable tbody tr td').length >= 6) {
      $('#convenzioniTable').DataTable({
        paging: false,
        info: false,
        className: 'col-actions text-center',
        language: {
          url: '/js/i18n/Italian.json',
                          paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
        },
        rowCallback: function(row, data, index) {
          $(row).toggleClass('even odd', false)
            .addClass(index % 2 === 0 ? 'even' : 'odd');
        },
        stripeClasses: ['table-white', 'table-striped-anpas'],
      });
    }


    // Sortable drag & drop
    const tbody = document.querySelector('#convenzioniTable tbody.sortable');
    if (tbody) {
      Sortable.create(tbody, {
        animation: 150,
        handle: 'td',
        ghostClass: 'table-warning',
        onEnd: function() {
          const ids = Array.from(tbody.querySelectorAll('tr'))
            .map(tr => tr.dataset.id);
          fetch('/convenzioni/riordina', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
              order: ids
            })
          }).catch(() => alert('Errore nel riordino.'));
        }
      });
    }


    // Messaggio duplicazione
    fetch("{{ route('convenzioni.checkDuplicazione') }}")
      .then(r => r.json())
      .then(data => {
        if (data.mostraMessaggio) {
          document.getElementById('noDataMessage')
            .classList.remove('d-none');
        }
      });


    // Duplica
    document.getElementById('btn-duplica-si')
      ?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerText = 'Duplicazione…';
        const res = await fetch("{{ route('convenzioni.duplica') }}", {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
          }
        });
        const json = await res.json();
        if (res.ok) location.reload();
        else {
          alert(json.message || 'Errore duplicazione');
          btn.disabled = false;
          btn.innerText = 'Sì';
        }
      });


    // Nascondi prompt duplicazione
    document.getElementById('btn-duplica-no')
      ?.addEventListener('click', function() {
        document.getElementById('noDataMessage')
          .classList.add('d-none');
      });
  });
</script>

<script>
function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
  const form = document.getElementById(formId);
  const input = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  const toggleBtn = document.getElementById(toggleBtnId);
  const hidden = document.getElementById(hiddenId);

  if (!form || !input || !dropdown || !hidden) return;

  const items = Array.from(dropdown.querySelectorAll('.assoc-item'))
    .map(li => ({ id: String(li.dataset.id), name: (li.textContent || '').trim() }));

  function showDropdown() { dropdown.style.display = 'block'; toggleBtn.setAttribute('aria-expanded', 'true'); }
  function hideDropdown() { dropdown.style.display = 'none'; toggleBtn.setAttribute('aria-expanded', 'false'); }

  function filterDropdown(term) {
    term = (term || '').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      const txt = (li.textContent || '').toLowerCase();
      li.style.display = txt.includes(term) ? '' : 'none';
    });
  }

  function setSelection(id, name) {
    hidden.value = id ?? '';
    input.value = name ?? '';
    form.submit();
  }

  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', function () {
      setSelection(this.dataset.id, this.textContent.trim());
    });
  });

  input.addEventListener('input', () => filterDropdown(input.value));
  toggleBtn.addEventListener('click', () => {
    dropdown.style.display === 'block' ? hideDropdown() : showDropdown();
  });
  document.addEventListener('click', e => {
    if (!form.contains(e.target)) hideDropdown();
  });
}

// Attivazione per la select convenzioni
setupCustomSelect(
  "assocFilterForm",
  "assocFilterInput",
  "assocFilterDropdown",
  "assocFilterToggleBtn",
  "assocFilterHidden"
);
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