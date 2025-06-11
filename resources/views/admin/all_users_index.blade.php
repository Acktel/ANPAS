@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <h1>Tutti gli Utenti (Admin/Supervisor)</h1>

    <table id="allUsersTable" class="table table-bordered table-striped table-hover">
      <thead>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  $('#allUsersTable').DataTable({
    ajax: "{{ route('all-users.data') }}", // richiama AdminAllUsersController@getData
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
        render: date => new Date(date).toLocaleDateString()
      },
      {
        data: 'id',
        orderable: false,
        searchable: false,
        render: function(id) {
          return `
            <form action="/all-users/${id}" method="POST" style="display:inline-block;">
              <input type="hidden" name="_token" value="${csrfToken}">
              <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
            </form>
          `;
        }
      }
    ],
    language: {
      url: "https://cdn.datatables.net/plug-ins/1.11.3/i18n/Italian.json"
    },
    paging:   true,
    searching:true,
    ordering: true
  });
});
</script>
@endpush
