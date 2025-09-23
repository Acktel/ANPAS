@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Aziende Sanitarie
  </h1>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" id="convSelectForm" action="{{ route('aziende-sanitarie.index') }}" class="w-100" style="max-width:400px; position:relative;">
        <div class="input-group">
          <!-- Campo visibile -->
          <input
            id="convSelect"
            name="convLabel"
            class="form-control"
            autocomplete="off"
            placeholder="Seleziona convenzione"
            value="{{ optional($convenzioni->firstWhere('idConvenzione', $selectedConvenzione))->Convenzione ?? '' }}"
            aria-label="Seleziona convenzione">

          <!-- Bottone per aprire/chiudere -->
          <button type="button" id="convSelectToggleBtn" class="btn btn-outline-secondary"
                  aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
            <i class="fas fa-chevron-down"></i>
          </button>

          <!-- Campo nascosto con l'id reale -->
          <input type="hidden" id="convSelectHidden" name="idConvenzione" value="{{ $selectedConvenzione ?? '' }}">
        </div>

        <!-- Dropdown custom -->
        <ul id="convSelectDropdown" class="list-group position-absolute w-100"
            style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
                   background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
          @foreach ($convenzioni as $conv)
            <li class="list-group-item conv-item" data-id="{{ $conv->idConvenzione }}">
              {{ $conv->Convenzione }}
            </li>
          @endforeach
        </ul>
      </form>
    </div>
  @endif

  <!-- Messaggio duplicazione (come nelle convenzioni) -->
  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna azienda sanitaria presente per l’anno corrente.<br>
    Vuoi importare le anagrafiche e i collegamenti dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  <div class="d-flex mb-3">
    <div class="ms-auto">
      @can('manage-all-associations')
        @if (!session()->has('impersonate'))
          <a href="{{ route('aziende-sanitarie.create') }}" class="btn btn-anpas-green">
            <i class="fas fa-plus me-1"></i> Nuova Azienda Sanitaria
          </a>
        @endif
      @endcan
    </div>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white p-0">
      <table id="aziendeSanitarieTable"
             class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0 table-striped-anpas">
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Indirizzo</th>
            <th>Provincia</th>
            <th>Città</th>
            <th>Email</th>
            <th>Convenzioni</th>
            <th>Lotti</th>
            <th class="col-actions text-center">Azioni</th>
          </tr>
        </thead>
        <tbody>
          @forelse($aziende as $a)
            <tr>
              <td>{{ $a->idAziendaSanitaria }}</td>
              <td>{{ $a->Nome }}</td>
              <td>{{ $a->Indirizzo }}</td>
              <td>{{ $a->provincia }}</td>
              <td>{{ $a->citta }}</td>
              <td>{{ $a->mail }}</td>
              <td>
                @if (!empty($a->Convenzioni))
                  {{ implode(', ', $a->Convenzioni) }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>
                @if (!empty($a->Lotti))
                  {{ implode(', ', $a->Lotti) }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-center">
                <a href="{{ route('aziende-sanitarie.edit', $a->idAziendaSanitaria) }}"
                   class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
                  <i class="fas fa-edit"></i>
                </a>
                <form action="{{ route('aziende-sanitarie.destroy', $a->idAziendaSanitaria) }}"
                      method="POST" class="d-inline"
                      onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
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
              <td colspan="9" class="text-center py-3">Nessuna azienda sanitaria trovata.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  // === DataTable via AJAX con filtro convenzione ===
  const table = $('#aziendeSanitarieTable').DataTable({
    ajax: {
      url: '{{ route('aziende-sanitarie.data') }}',
      data: function(d) {
        d.idConvenzione = document.getElementById('convSelectHidden')?.value || '';
      }
    },
    columns: [
      { data: 'idAziendaSanitaria' },
      { data: 'Nome' },
      { data: 'Indirizzo' },
      { data: 'provincia' },
      { data: 'citta' },
      { data: 'mail' },
      {
        data: 'Convenzioni',
        render: function(data) {
          if (Array.isArray(data)) return data.length ? data.join(', ') : '<span class="text-muted">—</span>';
          return '<span class="text-muted">—</span>';
        }
      },
      {
        data: 'Lotti',
        render: function(data) {
          if (Array.isArray(data)) return data.length ? data.join(', ') : '<span class="text-muted">—</span>';
          return '<span class="text-muted">—</span>';
        }
      },
      {
        data: 'idAziendaSanitaria',
        orderable: false,
        searchable: false,
        className: 'text-center',
        render: function(id) {
          return `
            <a href="/aziende-sanitarie/${id}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
              <i class="fas fa-edit"></i>
            </a>
            <form action="/aziende-sanitarie/${id}" method="POST" class="d-inline" onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
              @csrf
              @method('DELETE')
              <button class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina">
                <i class="fas fa-trash-alt"></i>
              </button>
            </form>
          `;
        }
      }
    ],
    paging: true,
    info: true,
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
      $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },
    stripeClasses: ['table-white', 'table-striped-anpas'],
  });

  // === Dropdown / select custom per convenzioni ===
  const convInput = document.getElementById('convSelect');
  const convHidden = document.getElementById('convSelectHidden');
  const convDropdown = document.getElementById('convSelectDropdown');
  const convToggle = document.getElementById('convSelectToggleBtn');
  const convForm = document.getElementById('convSelectForm');

  function openDropdown() {
    convDropdown.style.display = 'block';
    convToggle?.setAttribute('aria-expanded', 'true');
    convInput?.focus();
  }
  function closeDropdown() {
    convDropdown.style.display = 'none';
    convToggle?.setAttribute('aria-expanded', 'false');
  }

  convToggle?.addEventListener('click', function (e) {
    e.preventDefault();
    if (convDropdown.style.display === 'block') closeDropdown(); else openDropdown();
  });

  document.addEventListener('click', function(e) {
    if (!convForm.contains(e.target)) closeDropdown();
  });

  convInput?.addEventListener('input', function () {
    const q = convInput.value.trim().toLowerCase();
    convDropdown.querySelectorAll('.conv-item').forEach(li => {
      const txt = (li.textContent || '').toLowerCase();
      li.style.display = txt.includes(q) ? '' : 'none';
    });
    openDropdown();
  });

  convDropdown?.addEventListener('click', function (e) {
    const li = e.target.closest('.conv-item');
    if (!li) return;
    const id = li.getAttribute('data-id');
    const label = li.textContent.trim();
    convHidden.value = id;
    convInput.value = label;
    closeDropdown();
    table.ajax.reload();  // ricarica con filtro
    convForm.submit();    // persisti in session come fai nelle convenzioni
  });

  convInput?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const firstVisible = convDropdown.querySelector('.conv-item:not([style*="display: none"])');
      if (firstVisible) firstVisible.click(); else convForm.submit();
    } else if (e.key === 'Escape') {
      closeDropdown();
    }
  });

  convInput?.addEventListener('input', function() {
    if (!convInput.value) {
      convHidden.value = '';
      table.ajax.reload();
    }
  });

  // === Messaggio duplicazione (check+azione) ===
  fetch("{{ route('aziende-sanitarie.checkDuplicazione') }}")
    .then(r => r.json())
    .then(data => {
      if (data.mostraMessaggio) {
        document.getElementById('noDataMessage')?.classList.remove('d-none');
      }
    })
    .catch(() => { /* ignora */ });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione…';
    try {
      const res = await fetch("{{ route('aziende-sanitarie.duplica') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        }
      });
      const json = await res.json();
      if (res.ok) {
        // ricarico tabella e nascondo banner
        table.ajax.reload(() => location.reload());
      } else {
        alert(json.message || 'Errore duplicazione');
        btn.disabled = false;
        btn.innerText = 'Sì';
      }
    } catch (e) {
      alert('Errore duplicazione');
      btn.disabled = false;
      btn.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
    document.getElementById('noDataMessage')?.classList.add('d-none');
  });
});
</script>

<script>
(function () {
  const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
  if (!flash) return;
  setTimeout(() => {
    flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
    flash.style.opacity = '0';
    flash.style.maxHeight = flash.scrollHeight + 'px';
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
