{{-- resources/views/associazioni/users_index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    {{-- Intestazione con titolo e bottone "Aggiungi nuovo utente" --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Utenti</h3>
        @can('manage-own-association')
            <a href="{{ route('my-users.create') }}" class="btn btn-primary">
                + Aggiungi nuovo utente
            </a>
        @endcan
    </div>

    {{-- Tabella (DataTable) degli utenti della stessa associazione --}}
    <table id="allUsersTable"  class="table table-hover table-bordered dt-responsive nowrap">
        <thead>
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
        <tbody>
            {{-- Viene popolato via AJAX --}}
        </tbody>
    </table>
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
                  data: null, 
                  name: 'nome',
                  render: function (row) {
                    return row.firstname + ' ' + row.lastname;
                  }
                },
                { data: 'email', name: 'email' },
                { data: 'username', name: 'username' },
                { 
                  data: 'active', 
                  name: 'active',
                  render: function (val) {
                    return val ? 'Sì' : 'No';
                  }
                },
                { 
                  data: 'created_at', 
                  name: 'created_at',
                  render: function (val) {
                    // opzionale: formattazione preliminare
                    return new Date(val).toLocaleDateString('it-IT', {
                      year: 'numeric', month: '2-digit', day: '2-digit'
                    });
                  }
                },
                { 
                  data: null,
                  orderable: false,
                  searchable: false,
                  render: function (row) {
                    let html = '';
                    @can('manage-own-association')
                        html += `
                          <a href="/my-users/${row.id}/edit" 
                             class="btn btn-sm btn-warning me-1">
                            Modifica
                          </a>
                          <form action="/my-users/${row.id}" method="POST" 
                                style="display:inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">
                              Elimina
                            </button>
                          </form>`;
                    @endcan
                    return html;
                  }
                }
            ],
            language: {
              url: "https://cdn.datatables.net/plug-ins/1.11.3/i18n/Italian.json"
            },
            paging: true,
            searching: true,
            ordering: true
        });
    });
</script>
@endpush
