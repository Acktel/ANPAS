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
      dataSrc(json) {
        const rows = Array.isArray(json?.data) ? json.data : [];
        return isSuperAdmin 
          ? rows 
          : rows.filter(r => r.Associazione !== 'GOD' && r.Associazione !== 'Associazione GOD');
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
});
</script>
@endpush
