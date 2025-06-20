@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="text-anpas-green fw-bolder mb-4">Crea Nuova Associazione</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('associazioni.store') }}" method="POST">
        @csrf

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="Associazione" class="form-label">Nome Associazione</label>
            <input type="text" class="form-control" id="Associazione" name="Associazione"
                   value="{{ old('Associazione') }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email') }}" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="provincia" class="form-label">Provincia</label>
            <input type="text" class="form-control" id="provincia" name="provincia"
                   value="{{ old('provincia') }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="citta" class="form-label">Città</label>
            <input type="text" class="form-control" id="citta" name="citta"
                   value="{{ old('citta') }}" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="adminuser_name" class="form-label">Nome Admin</label>
            <input type="text" class="form-control" id="adminuser_name" name="adminuser_name"
                   value="{{ old('adminuser_name') }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="adminuser_email" class="form-label">Email Admin</label>
            <input type="email" class="form-control" id="adminuser_email" name="adminuser_email"
                   value="{{ old('adminuser_email') }}" required>
          </div>
        </div>

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">Crea Associazione</button>
          <a href="{{ route('associazioni.index') }}" class="btn btn-outline-secondary">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
