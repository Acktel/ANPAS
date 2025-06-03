@extends('layouts.app')

@section('content')
  <div class="container-xl">
    <h1>Crea Nuovo Utente per la Mia Associazione</h1>

    <form action="{{ route('my-users.store') }}" method="POST">
      @csrf

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
        <input type="text" class="form-control" id="firstname" name="firstname" value="{{ old('firstname') }}" required>
      </div>

      <div class="mb-3">
        <label for="lastname" class="form-label">Cognome</label>
        <input type="text" class="form-control" id="lastname" name="lastname" value="{{ old('lastname') }}">
      </div>

      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" required>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>

      <div class="mb-3">
        <label for="password_confirmation" class="form-label">Conferma Password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
      </div>

      <button type="submit" class="btn btn-primary">Crea Utente</button>
    </form>
  </div>
@endsection
