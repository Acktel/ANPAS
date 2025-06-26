@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Tutti gli Utenti (Admin/Supervisor)</h1>
<div class="mb-3 text-end">
  <a href="{{ route('all-users.create') }}" class="btn btn-anpas-green">
    <i class="fas fa-user-plus me-1"></i> Crea Utente
  </a>
</div>
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
            <th>Azioni</th>
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
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  $('#allUsersTable').DataTable({
    ajax: "{{ route('all-users.data') }}",
    columns: [
      { data: 'firstname' },
      { data: 'lastname' },
      { data: 'username' },
      { data: 'email' },
      { data: 'association_name' },
      {
        data: 'active',
        render: val => val ? 'Sì' : 'No'
      },
      {
        data: 'created_at',
        render: date => new Date(date).toLocaleDateString('it-IT', {
          year:'numeric', month:'2-digit', day:'2-digit'
        })
      },
      {
        data: 'id',
        orderable: false,
        searchable: false,
        render(id) {
          return `
            <a href="/all-users/${id}/edit" class="btn btn-sm btn-anpas-primary me-1">
              <i class="fas fa-edit"></i>
            </a>
            <form action="/all-users/${id}" method="POST" style="display:inline-block" onsubmit="return confirm('Confermi la cancellazione dell’utente?');">
              <input type="hidden" name="_token" value="${csrfToken}">
              <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="btn btn-sm btn-anpas-delete">
                <i class="fas fa-trash-alt"></i>
              </button>
            </form>
          `;
        }
      }
    ],
    language: {
      url: '/js/i18n/Italian.json'
    },
    paging:    true,
    searching: true,
    ordering:  true
  });
});
</script>
@endpush
