@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">
        Aziende Sanitarie
    </h1>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

      @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" action="{{ route('all-users.index') }}" id="assocSelectForm" class="w-100" style="max-width:400px">
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
            <ul id="assocSelectDropdown" class="list-group" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
                   background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
              @foreach($associazioni as $assoc)
                <li class="list-group-item assoc-item" data-id="{{ $assoc->IdAssociazione }}">
                  {{ $assoc->Associazione }}
                </li>
              @endforeach
            </ul>
      </form>
    </div>
  @endif

    <div class="d-flex mb-3">
        <div class="ms-auto">
            @can('manage-all-associations')
            @if(!session()->has('impersonate'))
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
                        <td>{{ $a->mail }}</td>
                        <td>
                            @if(!empty($a->Convenzioni))
                            {{ implode(', ', $a->Convenzioni) }}
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
                                method="POST"
                                class="d-inline"
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
                        <td colspan="6" class="text-center py-3">Nessuna azienda sanitaria trovata.</td>
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
    document.addEventListener('DOMContentLoaded', function() {
        $('#aziendeSanitarieTable').DataTable({
            ajax: '{{ route("aziende-sanitarie.data") }}',
            columns: [{
                    data: 'idAziendaSanitaria'
                },
                {
                    data: 'Nome'
                },
                {
                    data: 'Indirizzo'
                },
                {
                    data: 'mail'
                },
                {
                    data: 'Convenzioni',
                    render: function(data, type, row) {
                        if (Array.isArray(data)) {
                            return data.length ? data.join(', ') : '<span class="text-muted">—</span>';
                        }
                        return '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'Lotti', // AGGIUNTO
                    render: function(data, type, row) {
                        if (Array.isArray(data)) {
                            return data.length ? data.join(', ') : '<span class="text-muted">—</span>';
                        }
                        return '<span class="text-muted">—</span>';
                    }
                },
                {
                    data: 'idAziendaSanitaria',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `
          <a href="/aziende-sanitarie/${data}/edit"
             class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
            <i class="fas fa-edit"></i>
          </a>
          <form action="/aziende-sanitarie/${data}" method="POST"
                class="d-inline"
                onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
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