@extends('layouts.app')

@php
$isImpersonating = session()->has('impersonate');
@endphp
@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Convenzioni − Anno {{ $anno }}
  </h1>

  @if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="d-flex mb-3">
    {{-- Selettore associazione (solo per ruoli elevati) --}}
    @if(!empty($associazioni) && $associazioni->isNotEmpty() && !$isImpersonating )
    <div class="mb-3">
      <form id="assocFilterForm" method="GET" class="w-100">
        <div class="position-relative">
          <div class="input-group">
            <input
              id="assocFilterInput"
              name="assocLabel"
              class="form-control"
              autocomplete="off"
              placeholder="Seleziona associazione"
              value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
              aria-label="Seleziona associazione">
            <button type="button" id="assocFilterToggleBtn" class="btn btn-outline-secondary"
              aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
              <i class="fas fa-chevron-down"></i>
            </button>
            <input type="hidden" id="assocFilterHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
          </div>

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
        class="common-css-dataTable table table-hover table-bordered dt-responsive nowrap mb-0 table-striped-anpas">
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Anno</th>
            <th>Descrizione</th>
            <th>Aziende sanitarie</th>
            <th>Materiale fornito da ASL</th>
            <th data-orderable="false" class="col-actions text-center">Azioni</th>
          </tr>
        </thead>
        <tbody id="sortable-convenzioni" class="sortable">
          @foreach($convenzioni as $c)
          <tr data-id="{{ $c->idConvenzione }}">
            <td>{{ $c->idConvenzione }}</td>
            <td>{{ $c->idAnno }}</td>
            <td>{{ $c->Convenzione }}</td>
            <td>{{ $c->AziendeSanitarie }}</td>
            <td>
              @if((int)($c->materiale_fornito_asl ?? 0) === 1)
              <span class="badge text-bg-success">Sì</span>
              @else
              <span class="badge text-bg-secondary">No</span>
              @endif
            </td>
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
          @endforeach
        </tbody>

      </table>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // —————————————————————————————————————————————
  // DataTables: init robusto anche con tabella VUOTA
  // —————————————————————————————————————————————
  const $tbl = $('#convenzioniTable');

  // Se per qualsiasi ragione esiste già un'istanza, distruggila
  if ($.fn.DataTable.isDataTable($tbl)) {
    $tbl.DataTable().destroy();
  }

  // (Opzionale) pulizia di eventuale state salvato che può creare mismatch colonne
  try {
    Object.keys(localStorage).forEach(k => {
      if (k.indexOf('DataTables_convenzioniTable') !== -1) localStorage.removeItem(k);
    });
  } catch (_) {}

  const dt = $tbl.DataTable({
    paging: true,
    stateSave: false,          // evita conflitti quando cambia lo schema o la pagina è vuota
    info: false,
    autoWidth: false,
    language: {
      url: '/js/i18n/Italian.json',
      emptyTable: 'Nessuna convenzione.', // messaggio quando non ci sono righe
      paginate: {
        first: '<i class="fas fa-angle-double-left"></i>',
        last: '<i class="fas fa-angle-double-right"></i>',
        next: '<i class="fas fa-angle-right"></i>',
        previous: '<i class="fas fa-angle-left"></i>'
      },
    },
    // La colonna "Azioni" è l'ultima: disattiviamo ordinamento/ricerca lì
    columnDefs: [
      { targets: -1, orderable: false, searchable: false }
    ],
    rowCallback: function (row, data, index) {
      $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },
    stripeClasses: ['table-white', 'table-striped-anpas'],
  });

  // —————————————————————————————————————————————
  // Drag & drop ordinamento (solo se ci sono righe reali)
  // —————————————————————————————————————————————
  const tbody = document.querySelector('#convenzioniTable tbody.sortable');
  if (tbody && tbody.querySelectorAll('tr').length > 0) {
    Sortable.create(tbody, {
      animation: 150,
      handle: 'td',
      ghostClass: 'table-warning',
      onEnd: function () {
        const ids = Array.from(tbody.querySelectorAll('tr')).map(tr => tr.dataset.id);
        fetch("{{ route('convenzioni.riordina') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ order: ids })
        }).catch(() => alert('Errore nel riordino.'));
      }
    });
  }

  // —————————————————————————————————————————————
  // Messaggio duplicazione (check)
  // —————————————————————————————————————————————
  fetch("{{ route('convenzioni.checkDuplicazione') }}")
    .then(r => r.json())
    .then(data => {
      if (data && data.mostraMessaggio) {
        const box = document.getElementById('noDataMessage');
        if (box) box.classList.remove('d-none');
      }
    })
    .catch(() => { /* silenzio */ });

  // —————————————————————————————————————————————
  // Duplica da anno precedente
  // —————————————————————————————————————————————
  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Duplicazione…';
    try {
      const res = await fetch("{{ route('convenzioni.duplica') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
      });
      const json = await res.json().catch(() => ({}));
      if (res.ok) {
        location.reload();
      } else {
        alert(json.message || 'Errore duplicazione');
        btn.disabled = false;
        btn.textContent = 'Sì';
      }
    } catch {
      alert('Errore duplicazione');
      btn.disabled = false;
      btn.textContent = 'Sì';
    }
  });

  // Nascondi prompt duplicazione
  document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
    document.getElementById('noDataMessage')?.classList.add('d-none');
  });
});
</script>

<script>
/**
 * Select custom per filtro associazione
 * - Ricerca client-side
 * - Toggle con bottone
 * - Submit immediato al click
 */
function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
  const form = document.getElementById(formId);
  const input = document.getElementById(inputId);
  const dropdown = document.getElementById(dropdownId);
  const toggleBtn = document.getElementById(toggleBtnId);
  const hidden = document.getElementById(hiddenId);
  if (!form || !input || !dropdown || !hidden || !toggleBtn) return;

  function showDropdown() {
    dropdown.style.display = 'block';
    toggleBtn.setAttribute('aria-expanded', 'true');
  }
  function hideDropdown() {
    dropdown.style.display = 'none';
    toggleBtn.setAttribute('aria-expanded', 'false');
  }
  function filterDropdown(term) {
    const t = (term || '').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      const txt = (li.textContent || '').toLowerCase();
      li.style.display = txt.includes(t) ? '' : 'none';
    });
  }
  function setSelection(id, name) {
    hidden.value = id ?? '';
    input.value = name ?? '';
    form.submit();
  }

  // Click su una voce
  dropdown.querySelectorAll('.assoc-item').forEach(li => {
    li.style.cursor = 'pointer';
    li.addEventListener('click', function () {
      setSelection(this.dataset.id, this.textContent.trim());
    });
  });

  // Digitazione per filtrare
  input.addEventListener('input', () => filterDropdown(input.value));

  // Invio = scegli la prima voce visibile (se c'è)
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = dropdown.querySelector('.assoc-item:not([style*="display: none"])');
      if (first) {
        setSelection(first.dataset.id, first.textContent.trim());
      } else {
        form.submit();
      }
    }
  });

  // Toggle apertura
  toggleBtn.addEventListener('click', () => {
    dropdown.style.display === 'block' ? hideDropdown() : showDropdown();
    if (dropdown.style.display === 'block') input.focus();
  });

  // Chiudi cliccando fuori
  document.addEventListener('click', e => {
    if (!form.contains(e.target)) hideDropdown();
  });
}

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
  const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
  if (!flash) return;
  setTimeout(() => {
    flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
    flash.style.opacity = '0';
    flash.style.maxHeight = flash.scrollHeight + 'px';
    // trigger reflow
    // eslint-disable-next-line no-unused-expressions
    flash.offsetHeight;
    flash.style.maxHeight = '0';
    flash.style.paddingTop = '0';
    flash.style.paddingBottom = '0';
    flash.style.marginTop = '0';
    flash.style.marginBottom = '0';
    setTimeout(() => { if (flash.parentNode) flash.parentNode.removeChild(flash); }, 600);
  }, 3500);
})();
</script>
@endpush
