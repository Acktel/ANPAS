@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Tutti gli Utenti (Admin/Supervisor)</h1>

  {{-- Riga con select sopra la tabella --}}
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" action="{{ route('all-users.index') }}" id="assocSelectForm" class="w-100" style="max-width:400px">
        <div class="position-relative">
        <div class="input-group">
          <!-- Campo visibile -->
          <input
            id="assocSelect"
            name="assocLabel"
            class="form-control"
            autocomplete="off"
            placeholder="Seleziona associazione"
            value="{{ optional($associazioni->firstWhere('IdAssociazione', $selectedAssoc))->Associazione ?? '' }}"
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
                <li class="list-group-item assoc-item" data-id="{{ $assoc->IdAssociazione }}">
                  {{ $assoc->Associazione }}
                </li>
              @endforeach
            </ul>
            </div>
      </form>
    </div>
  @endif

  {{-- Riga separata con pulsante --}}
  <div class="d-flex justify-content-end mb-3">
    <a href="{{ route('all-users.create') }}" class="btn btn-anpas-green">
      <i class="fas fa-user-plus me-1"></i> Crea Utente
    </a>
  </div>

  {{-- Tabella --}}
  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <table id="allUsersTable"
             class="common-css-dataTable table table-hover table-bordered table-striped-anpas dt-responsive nowrap w-100">
        <thead class="thead-anpas">
          <tr>
            <th>Nome</th>
            <th>Cognome</th>
            <th>Username</th>
            <th>Email</th>
            <th>Associazione</th>
            <th>Attivo</th>
            <th>Creato il</th>
            <th class="col-actions">Azioni</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const table = $('#allUsersTable').DataTable({
    stateDuration: -1,
    stateSave: true,  
    ajax: {
      url: "{{ route('all-users.data') }}",
      data: function(d) {
        // Prendo il valore dall’hidden invece che dalla select originale
        const selectedAssoc = document.getElementById('assocSelectHidden')?.value || null;
        if (selectedAssoc) {
          d.idAssociazione = selectedAssoc;
        }
      }
    },
    columns: [
      { data: 'firstname' },
      { data: 'lastname' },
      { data: 'username' },
      { data: 'email' },
      { data: 'association_name' },
      { data: 'active', render: val => val ? 'Sì' : 'No' },
      { data: 'created_at', render: date => new Date(date).toLocaleDateString('it-IT', {
          year: 'numeric', month: '2-digit', day: '2-digit'
        })
      },
      {
        data: 'id', orderable: false, searchable: false, className: 'col-actions text-center',
        render(id) {
          return `
            <a href="/all-users/${id}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
              <i class="fas fa-edit"></i>
            </a>
            <button class="btn btn-sm btn-anpas-delete btn-icon" data-id="${id}" title="Elimina">
              <i class="fas fa-trash-alt"></i>
            </button>
          `;
        }
      }
    ],
    language: { url: '/js/i18n/Italian.json',
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
    paging: true,
    searching: true,
    ordering: true
  });

  // Delete handler
  $(document).on('click', '.btn-anpas-delete', function() {
    const id = $(this).data('id');
    if (!confirm('Confermi la cancellazione dell’utente?')) return;

    fetch(`/all-users/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    })
    .then(res => {
      if (!res.ok) throw new Error('Errore nella cancellazione');
      table.ajax.reload();
    })
    .catch(err => alert(err.message));
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

  // filtro e selezione (come nel tuo codice originale)
  function showDropdown() { dropdown.style.display = 'block'; toggleBtn.setAttribute('aria-expanded', 'true'); }
  function hideDropdown() { dropdown.style.display = 'none'; toggleBtn.setAttribute('aria-expanded', 'false'); }

  function filterDropdown(term) {
    term = (term || '').toLowerCase();
    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      const txt = (li.textContent || '').toLowerCase();
      li.style.display = txt.includes(term) ? '' : 'none';
    });
  }

  function setSelection(id, name, submit = true) {
    hidden.value = id ?? '';
    input.value = name ?? '';
    if (submit) form.submit();
  }

  // Eventi
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

// Attivazione per la select che mi hai passato
setupCustomSelect(
  "assocSelectForm",
  "assocSelect",
  "assocSelectDropdown",
  "assocSelectToggleBtn",
  "assocSelectHidden"
);
    </script>
@endpush
