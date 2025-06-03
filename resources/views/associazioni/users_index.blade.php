{{-- resources/views/associazioni/users_index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    {{-- Intestazione con titolo e bottone "Aggiungi nuovo utente" --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Utenti</h3>
        @can('manage-own-association')
            <a href="{{ route('my-users.create') }}" class="btn btn-primary">
                + Aggiungi nuovo utente
            </a>
        @endcan
    </div>

    {{-- Tabella (DataTable) dei “tutti gli utenti” --}}
    <table id="allUsersTable" class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Associazione</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
                <tr>
                    <td>{{ $u->id }}</td>
                    <td>{{ $u->firstname }} {{ $u->lastname }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->association_name }}</td>
                    <td>
                        {{-- Qui puoi mettere eventuali pulsanti Modifica/Elimina, es:
                            <a href="{{ route('all-users.edit', $u->id) }}" class="btn btn-sm btn-warning">Modifica</a>
                            <form action="{{ route('all-users.destroy', $u->id) }}" method="POST" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Elimina</button>
                            </form>
                        --}}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
    // Se usi DataTables lato JS, qui puoi inizializzare la tabella:
    $(document).ready(function() {
        $('#allUsersTable').DataTable({
            // se vuoi caricare via AJAX, sostituisci la sezione <tbody> con:
            // ajax: "{{ route('all-users.data') }}",
            // columns: [
            //     { data: 'id' },
            //     { data: 'firstname', render: data => data + ' ' + row.lastname },
            //     { data: 'email' },
            //     { data: 'association_name' },
            //     { data: 'actions', orderable: false, searchable: false }
            // ]
        });
    });
</script>
@endpush
