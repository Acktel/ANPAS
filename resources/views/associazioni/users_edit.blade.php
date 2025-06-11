@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <h1>Modifica Utente</h1>

    <form action="{{ route('my-users.update', $user->id) }}" method="POST">
      @csrf
      @method('PUT')

      @if($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="mb-3">
        <label for="firstname" class="form-label">Nome</label>
        <input type="text" class="form-control" id="firstname" name="firstname"
               value="{{ old('firstname', $user->firstname) }}" required>
      </div>

      <div class="mb-3">
        <label for="lastname" class="form-label">Cognome</label>
        <input type="text" class="form-control" id="lastname" name="lastname"
               value="{{ old('lastname', $user->lastname) }}">
      </div>

      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username"
               value="{{ old('username', $user->username) }}" required>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email"
               value="{{ old('email', $user->email) }}" required>
      </div>
      <button type="submit" class="btn btn-warning">Salva Modifiche</button>
      <a href="{{ route('my-users.index') }}" class="btn btn-secondary ms-2">Annulla</a>
    </form>
  </div>
@endsection
