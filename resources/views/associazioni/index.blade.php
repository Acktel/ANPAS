@extends('layouts.app')

@php
  $user = Auth::user();
  $isSuperAdmin = $user->hasRole('SuperAdmin');
  // dd($associazioni);
@endphp

@section('content')
<div class="container-fluid container-margin">
  <h1 class="text-anpas-green mb-4 container-title">Associazioni</h1>

@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" action="{{ route('associazioni.index') }}" id="assocSelectForm" class="w-100" style="max-width:400px">
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
            <ul id="assocSelectDropdown" class="list-group position-absolute w-100" style="z-index:2000; display:none; max-height:200px; overflow:auto; top:100%; left:0;
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

  <a href="{{ route('associazioni.create') }}" class="btn btn-anpas-green mb-3 float-end">
    <i class="fas fa-plus me-1"></i> Aggiungi Associazione
  </a>

  <table id="associazioniTable"
         class="common-css-dataTable table table-bordered table-hover dt-responsive nowrap w-100 table-striped-anpas">
    <thead class="thead-anpas">
      <tr>
        <th>Associazione</th>
        <th>Email</th>
        <th>Provincia</th>
        <th>Città</th>
        <th>Indirizzo</th>
        <th>Aggiornato da</th>
        <th class="col-actions">Azioni</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.head.querySelector('meta[name="csrf-token"]').content;
  const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};

  $('#associazioniTable').DataTable({
    ajax: {
      url: "{{ route('associazioni.data') }}",
          data: function(d) {
        d.idAssociazione = $('#assocSelectHidden').val();
    },
      dataSrc: function (json) {
        const rows = json.data;
        return isSuperAdmin
          ? rows
          : rows.filter(r => r.Associazione !== 'GOD');
      }
    },
    columns: [
      { data: 'Associazione' },
      { data: 'email' },
      { data: 'provincia' },
      { data: 'citta' },
      { data: 'indirizzo' },
      { data: 'updated_by_name', defaultContent: '-' },
      {
        data: null,
        orderable: false,
        searchable: false,
        className: 'actions col-actions text-center',
        width: '80px',
        render(row) {
          let btns = `
            <a href="/associazioni/${row.IdAssociazione}/edit"
               class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
              <i class="fas fa-edit"></i>
            </a>
            <form action="/associazioni/${row.IdAssociazione}" method="POST" style="display:inline">
              <input name="_token" value="${csrf}" hidden>
              <input name="_method" value="DELETE" hidden>
              <button class="btn btn-sm btn-anpas-delete me-1 btn-icon" onclick="return confirm('Sei sicuro di voler eliminare questa associazione?')" title="Elimina">
                <i class="fas fa-trash-alt"></i>
              </button>
            </form>`;

          
          if (row.supervisor_user_id) {
            btns += `
              <form action="/impersonate/${row.supervisor_user_id}" method="POST" style="display:inline">
                <input name="_token" value="${csrf}" hidden>
                <button class="btn btn-sm btn-anpas-green btn-icon" title="Impersona utente">
                  <i class="fas fa-user-secret"></i>
                </button>
              </form>`;
          }
          return btns;
        }
      }
    ],
    stripeClasses: ['table-white', 'table-striped-anpas'],
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
      if (index % 2 === 0) {
        $(row).removeClass('even').removeClass('odd').addClass('even');
      } else {
        $(row).removeClass('even').removeClass('odd').addClass('odd');
      }
    }

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
