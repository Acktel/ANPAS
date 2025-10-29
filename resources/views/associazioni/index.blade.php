@extends('layouts.app')

@php
  $user = Auth::user();
  $isSuperAdmin = $user->hasRole('SuperAdmin');
@endphp

@section('content')
<div class="container-fluid container-margin">
  <h1 class="text-anpas-green mb-4 container-title">Associazioni</h1>



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
        <th>CAP</th>
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

  const $table = $('#associazioniTable');

  $table.DataTable({
    processing: true,
    stateSave: true,
    stateDuration: -1,
    deferRender: true,
    responsive: true,
    ajax: {
      url: "{{ route('associazioni.data') }}",
      data(d) {
        d.idAssociazione = document.getElementById('assocSelectHidden')?.value || '';
      },
      dataSrc(json) {
        // protezione: se manca data o non è array, ritorno array vuoto
        const rows = Array.isArray(json?.data) ? json.data : [];
        const filtered = isSuperAdmin ? rows : rows.filter(r => r.Associazione !== 'GOD' && r.Associazione !== 'Associazione GOD');
        return filtered;
      }
    },
    columns: [
      { data: 'Associazione', defaultContent: '-' },
      { data: 'email',        defaultContent: '-' },
      { data: 'provincia',    defaultContent: '-' },
      { data: 'citta',        defaultContent: '-' },
      { data: 'cap',          defaultContent: '-' },            // <-- NUOVO CAP
      { data: 'indirizzo',    defaultContent: '-' },
      { data: 'updated_by_name', defaultContent: '-' },
      {
        data: null,
        orderable: false,
        searchable: false,
        className: 'actions col-actions text-center',
        width: '110px',
        render(row) {
          const id = row.IdAssociazione;
          let html = `
            <a href="/associazioni/${id}/edit"
               class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
              <i class="fas fa-edit"></i>
            </a>
            <form action="/associazioni/${id}" method="POST" style="display:inline">
              <input name="_token" value="${csrf}" type="hidden">
              <input name="_method" value="DELETE" type="hidden">
              <button class="btn btn-sm btn-anpas-delete me-1 btn-icon"
                      onclick="return confirm('Sei sicuro di voler eliminare questa associazione?')"
                      title="Elimina">
                <i class="fas fa-trash-alt"></i>
              </button>
            </form>
          `;

          if (row.supervisor_user_id) {
            html += `
              <form action="/impersonate/${row.supervisor_user_id}" method="POST" style="display:inline">
                <input name="_token" value="${csrf}" type="hidden">
                <button class="btn btn-sm btn-anpas-green btn-icon" title="Impersona utente">
                  <i class="fas fa-user-secret"></i>
                </button>
              </form>
            `;
          }
          return html;
        }
      }
    ],
    order: [[0, 'asc']],
    language: {
      url: '/js/i18n/Italian.json',
      paginate: {
        first: '<i class="fas fa-angle-double-left"></i>',
        last: '<i class="fas fa-angle-double-right"></i>',
        next: '<i class="fas fa-angle-right"></i>',
        previous: '<i class="fas fa-angle-left"></i>'
      },
      emptyTable: 'Nessuna associazione trovata',
      zeroRecords: 'Nessun risultato corrisponde ai filtri'
    },
    stripeClasses: ['table-white', 'table-striped-anpas'],
    rowCallback(row, data, index) {
      $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    }
  });

  // --- Select personalizzata Associazione ---
  function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
    const form      = document.getElementById(formId);
    const input     = document.getElementById(inputId);
    const dropdown  = document.getElementById(dropdownId);
    const toggleBtn = document.getElementById(toggleBtnId);
    const hidden    = document.getElementById(hiddenId);
    if (!form || !input || !dropdown || !toggleBtn || !hidden) return;

    function showDropdown(){ dropdown.style.display = 'block';  toggleBtn.setAttribute('aria-expanded','true'); }
    function hideDropdown(){ dropdown.style.display = 'none';   toggleBtn.setAttribute('aria-expanded','false'); }

    function filterDropdown(term) {
      const t = (term || '').toLowerCase();
      dropdown.querySelectorAll('.assoc-item').forEach(li => {
        const txt = (li.textContent || '').toLowerCase();
        li.style.display = txt.includes(t) ? '' : 'none';
      });
    }

    function setSelection(id, name) {
      hidden.value = id ?? '';
      input.value  = name ?? '';
      form.submit();
    }

    dropdown.querySelectorAll('.assoc-item').forEach(li => {
      li.style.cursor = 'pointer';
      li.addEventListener('click', function () {
        setSelection(this.dataset.id, (this.textContent || '').trim());
      });
    });

    input.addEventListener('input', () => filterDropdown(input.value));
    toggleBtn.addEventListener('click', () => {
      (dropdown.style.display === 'block') ? hideDropdown() : (filterDropdown(input.value), showDropdown());
    });

    document.addEventListener('click', (e) => {
      if (!form.contains(e.target)) hideDropdown();
    });
  }

  setupCustomSelect('assocSelectForm', 'assocSelect', 'assocSelectDropdown', 'assocSelectToggleBtn', 'assocSelectHidden');
});
</script>
@endpush
