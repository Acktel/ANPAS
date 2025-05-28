{{-- resources/views/admin/users/index.blade.php --}}
<h1>Elenco Utenti</h1>
@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<table>
    <thead>
        <tr><th>Nome</th><th>Email</th><th>Azioni</th></tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                <a href="{{ route('admin.users.roles.edit', $user->id) }}">
                    Gestisci Ruoli
                </a>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
