<form action="{{ route('admin.users.update', $user->id) }}" method="POST">
    @csrf
    @method('PUT')

    <h2>Gestione Ruoli per {{ $user->name }}</h2>

    @foreach($roles as $role)
        <div>
            <label>
                <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                    {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                {{ ucfirst($role->name) }}
            </label>
        </div>
    @endforeach

    <button type="submit">Salva Ruoli</button>
</form>
