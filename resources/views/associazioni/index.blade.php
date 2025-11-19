@extends('layouts.app')

@php
    $user = Auth::user();
    $isSuperAdmin = $user->hasRole('SuperAdmin');
@endphp

@section('content')
<div class="container-fluid container-margin">

  {{-- TITOLO + BOTTONE --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="container-title">Associazioni</h1>

      <a href="{{ route('associazioni.create') }}" class="btn btn-anpas-green">
          <i class="fas fa-plus me-1"></i> Nuova Associazione
      </a>
  </div>

  {{-- FLASH MESSAGE --}}
  @if (session('success'))
      <div id="flash-message" class="alert alert-success">
          {{ session('success') }}
      </div>
  @endif

  {{-- CARD TABELLA --}}
  <div class="card-anpas">
    <div class="card-body bg-anpas-white p-0">

      <table id="associazioniTable"
             class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap mb-0 w-100">
        <thead class="thead-anpas">
          <tr>
            <th>Associazione</th>
            <th>Email</th>
            <th>Provincia</th>
            <th>Città</th>
            <th>CAP</th>
            <th>Indirizzo</th>
            <th>Aggiornato da</th>
            <th class="col-actions text-center">Azioni</th>
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
document.addEventListener('DOMContentLoaded', function () {

    const csrf = document.head.querySelector('meta[name="csrf-token"]').content;
    const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};

    // 🔥 importante: rimuove stati DataTables che causano righe mancanti
    try {
        Object.keys(localStorage).forEach(k => {
            if (k.includes('DataTables_associazioniTable')) {
                localStorage.removeItem(k);
            }
        });
    } catch (_) {}

    const table = $('#associazioniTable').DataTable({
        processing: true,
        serverSide: false,
        stateSave: false,
        deferRender: true,
        responsive: true,

        ajax: {
            url: "{{ route('associazioni.data') }}",
            dataSrc: function(json) {

                if (!json || !Array.isArray(json.data)) return [];

                // 🔥 FILTRO → i NON-SUPERADMIN NON devono vedere GOD o ANPAS
                if (!isSuperAdmin) {
                    return json.data.filter(r =>
                        r.Associazione !== 'Associazione GOD' &&
                        r.Associazione !== 'GOD' &&
                        r.Associazione !== 'Anpas Nazionale'
                    );
                }

                return json.data;
            }
        },

        columns: [
            { data: 'Associazione', defaultContent: '-' },
            { data: 'email',        defaultContent: '-' },
            { data: 'provincia',    defaultContent: '-' },
            { data: 'citta',        defaultContent: '-' },
            { data: 'cap',          defaultContent: '-' },
            { data: 'indirizzo',    defaultContent: '-' },
            { data: 'updated_by_name', defaultContent: '-' },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center col-actions',

                render: function(row) {

                    if (!row) return '';

                    const forbidden = ['Associazione GOD','GOD','Anpas Nazionale'];

                    // 🔥 gli utenti NON-superadmin NON vedono nessuna azione per GOD/ANPAS
                    if (!isSuperAdmin && forbidden.includes(row.Associazione)) {
                        return '';
                    }

                    const id = row.IdAssociazione;

                    let html = `
                        <a href="/associazioni/${id}/edit"
                           class="btn btn-sm btn-anpas-edit me-1 btn-icon"
                           title="Modifica">
                            <i class="fas fa-edit"></i>
                        </a>

                        <form action="/associazioni/${id}" method="POST" class="d-inline">
                            <input type="hidden" name="_token" value="${csrf}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="btn btn-sm btn-anpas-delete btn-icon"
                                onclick="return confirm('Eliminare questa associazione?')"
                                title="Elimina">
                                    <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    `;

                    if (row.supervisor_user_id) {
                        html += `
                            <form action="/impersonate/${row.supervisor_user_id}"
                                  method="POST"
                                  class="d-inline">
                                <input type="hidden" name="_token" value="${csrf}">
                                <button class="btn btn-sm btn-anpas-green btn-icon" title="Impersona">
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
            }
        },

        stripeClasses: ['table-white', 'table-striped-anpas'],

        rowCallback: function(row, data, index) {
            $(row).removeClass('even odd')
                  .addClass(index % 2 === 0 ? 'even' : 'odd');
        }

    });

});
</script>


{{-- AUTO-HIDE FLASH MESSAGE --}}
<script>
(function () {
    const flash = document.getElementById('flash-message');
    if (!flash) return;

    setTimeout(() => {
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
        flash.style.opacity = '0';
        flash.style.maxHeight = '0';
        setTimeout(() => flash.remove(), 600);
    }, 3500);
})();
</script>
@endpush
