@extends('layouts.app')

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="text-anpas-green fw-bolder">Utenti</h1>
    @can('manage-own-association')
      <a href="{{ route('my-users.create') }}" class="btn btn-anpas-green">
        + Aggiungi nuovo utente
      </a>
    @endcan
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <table id="allUsersTable"
             class="table table-hover table-bordered table-striped-anpas dt-responsive nowrap w-100">
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Username</th>
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
    $('#allUsersTable').DataTable({
      processing: true,
      serverSide: true,
      ajax: "{{ route('my-users.data') }}",
      columns: [
        { data: 'id', name: 'id' },
        {
          data: null, name: 'nome',
          render(row) { return row.firstname + ' ' + row.lastname; }
        },
        { data: 'email', name: 'email' },
        { data: 'username', name: 'username' },
        {
          data: 'active', name: 'active',
          render(val) { return val ? 'Sì' : 'No'; }
        },
        {
          data: 'created_at', name: 'created_at',
          render(val) {
            return new Date(val).toLocaleDateString('it-IT', {
              year:'numeric', month:'2-digit', day:'2-digit'
            });
          }
        },
        {
          data: null, orderable: false, searchable: false,
          render(row) {
            let html = '';
            @can('manage-own-association')
              html += `
                <a href="/my-users/${row.id}/edit"
                   class="btn btn-sm btn-anpas-edit me-1">
                  <i class="fas fa-edit"></i>
                </a>
                <form action="/my-users/${row.id}" method="POST"
                      style="display:inline-block">
                  @csrf
                  @method('DELETE')
                  <button type="submit"
                          class="btn btn-sm btn-anpas-delete me-1">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>`;
            @endcan
            @if(session()->has('impersonate_original_user'))
              html += `
                <form action="/impersonate/${row.id}" method="POST"
                      style="display:inline-block">
                  @csrf
                  <button class="btn btn-sm btn-anpas-impersonate">
                    <i class="fas fa-user-secret"></i>
                  </button>
                </form>`;
            @endif
            return html;
          }
        }
      ],
      language: {
        url: '/js/i18n/Italian.json'
      },
      paging:   true,
      searching:true,
      ordering: true
    });
  });
</script>
@endpush
