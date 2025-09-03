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
@endpush
