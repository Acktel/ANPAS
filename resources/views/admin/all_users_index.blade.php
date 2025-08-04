@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Tutti gli Utenti (Admin/Supervisor)</h1>

  <div class="d-flex mb-3">
    {{-- Filter per associazione solo per chi ha privilegi elevati --}}
    @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
      <form id="assocFilterForm" method="GET" class="me-3">
        <label for="assocSelect" class="visually-hidden">Associazione</label>
        <select id="assocSelect" name="idAssociazione" class="form-select" onchange="this.form.submit()">
          @foreach($associazioni as $assoc)
            <option value="{{ $assoc->IdAssociazione }}" {{ $assoc->IdAssociazione == $selectedAssoc ? 'selected' : '' }}>
              {{ $assoc->Associazione }}
            </option>
          @endforeach
        </select>
      </form>
    @endif

    <div class="ms-auto">
      <a href="{{ route('all-users.create') }}" class="btn btn-anpas-green">
        <i class="fas fa-user-plus me-1"></i> Crea Utente
      </a>
    </div>
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
            <th class="col-actions">Azioni</th>
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
  document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const selectedAssoc = document.getElementById('assocSelect')?.value || null;

    $('#allUsersTable').DataTable({
      ajax: {
        url: "{{ route('all-users.data') }}",
        data: function(d) {
          if (selectedAssoc) {
            d.idAssociazione = selectedAssoc;
          }
        }
      },
      columns: [
        { data: 'firstname' },
        { data: 'lastname' },
        { data: 'username' },
        { data: 'email' },
        { data: 'association_name' },
        { data: 'active', render: val => val ? 'Sì' : 'No' },
        { data: 'created_at', render: date => new Date(date).toLocaleDateString('it-IT', {
            year: 'numeric', month: '2-digit', day: '2-digit'
          })
        },
        {
          data: 'id', orderable: false, searchable: false, className: 'col-actions text-center',
          render(id) {
            return `
              <a href="/all-users/${id}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
                <i class="fas fa-edit"></i>
              </a>
              <button class="btn btn-sm btn-anpas-delete btn-icon" data-id="${id}" title="Elimina">
                <i class="fas fa-trash-alt"></i>
              </button>
            `;
          }
        }
      ],
      language: { url: '/js/i18n/Italian.json' },
      rowCallback: function(row, data, index) {
        $(row).toggleClass('even odd', false)
                .addClass(index % 2 === 0 ? 'even' : 'odd');
      },
      stripeClasses: ['table-white', 'table-striped-anpas'],
      paging: true,
      searching: true,
      ordering: true
    });
  });

  // Delete handler
  $(document).on('click', '.btn-anpas-delete', function() {
    const id = $(this).data('id');
    if (!confirm('Confermi la cancellazione dell’utente?')) return;

    fetch(`/all-users/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    })
    .then(res => {
      if (!res.ok) throw new Error('Errore nella cancellazione');
      $('#allUsersTable').DataTable().ajax.reload();
    })
    .catch(err => alert(err.message));
  });
</script>
@endpush
