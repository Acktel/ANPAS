@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="text-anpas-green fw-bold mb-4">
    Crea Nuovo Utente
  </h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('my-users.store') }}" method="POST">
        @csrf

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="firstname" class="form-label">Nome</label>
            <input type="text" id="firstname" name="firstname"
                   class="form-control" value="{{ old('firstname') }}" required>
          </div>
          <div class="col-md-6">
            <label for="lastname" class="form-label">Cognome</label>
            <input type="text" id="lastname" name="lastname"
                   class="form-control" value="{{ old('lastname') }}">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username"
                   class="form-control" value="{{ old('username') }}" required>
          </div>
          <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email"
                   class="form-control" value="{{ old('email') }}" required>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-6">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control" required>
          </div>
          <div class="col-md-6">
            <label for="password_confirmation" class="form-label">Conferma Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" required>
          </div>
        </div>

        <button type="submit" class="btn btn-anpas-red">Crea Utente</button>
        <a href="{{ route('my-users.index') }}" class="btn btn-secondary ms-2">Annulla</a>
      </form>
    </div>
  </div>
</div>
@endsection
