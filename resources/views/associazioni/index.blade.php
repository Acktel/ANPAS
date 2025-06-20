@extends('layouts.app')

@section('content')
<div class="container-fluid container-margin">
  <h1 class="text-anpas-green mb-4 container-title">Associazioni</h1>

  <a href="{{ route('associazioni.create') }}" class="btn btn-anpas-green mb-3">
    <i class="fas fa-plus me-1"></i> Aggiungi Associazione
  </a>

  <table id="associazioniTable"
    class="common-css-dataTable table table-bordered table-hover dt-responsive nowrap w-100">
    <thead class="thead-anpas">
      <tr>
        <th>Associazione</th>
        <th>Email</th>
        <th>Provincia</th>
        <th>Città</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.head.querySelector('meta[name="csrf-token"]').content;

    $('#associazioniTable').DataTable({
      ajax: "{{ route('associazioni.data') }}",
      columns: [{
          data: 'Associazione'
        },
        {
          data: 'email'
        },
        {
          data: 'provincia'
        },
        {
          data: 'citta'
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render(row) {
            const csrf = document.head.querySelector('meta[name="csrf-token"]').content;

            let btns = `
    <a href="/associazioni/${row.IdAssociazione}/edit"
       class="btn btn-sm btn-anpas-edit me-1">
      <i class="fas fa-edit"></i>
    </a>
    <form action="/associazioni/${row.IdAssociazione}" method="POST" style="display:inline">
      <input name="_token" value="${csrf}" hidden>
      <input name="_method" value="DELETE" hidden>
      <button class="btn btn-sm btn-anpas-delete me-1">
        <i class="fas fa-trash-alt"></i>
      </button>
    </form>`;

            if (row.supervisor_user_id) {
              btns += `
      <form action="/impersonate/${row.supervisor_user_id}" method="POST" style="display:inline">
        <input name="_token" value="${csrf}" hidden>
        <button class="btn btn-sm btn-anpas-impersonate">
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
        url: '/js/i18n/Italian.json'
      }
    });
  });
</script>
@endpush