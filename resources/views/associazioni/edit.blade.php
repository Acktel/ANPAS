@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="text-anpas-green fw-bolder mb-4">Modifica Associazione</h1>

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
      <form action="{{ route('associazioni.update', $associazione->IdAssociazione) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="Associazione" class="form-label">Nome Associazione</label>
            <input type="text" class="form-control" id="Associazione" name="Associazione"
                   value="{{ old('Associazione', $associazione->Associazione) }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email', $associazione->email) }}" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="provincia" class="form-label">Provincia</label>
            <input type="text" class="form-control" id="provincia" name="provincia"
                   value="{{ old('provincia', $associazione->provincia) }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="citta" class="form-label">Città</label>
            <input type="text" class="form-control" id="citta" name="citta"
                   value="{{ old('citta', $associazione->citta) }}" required>
          </div>
        </div>

        @isset($adminUser)
          <hr>
          <h5 class="text-anpas-green mb-3">Dati Amministratore</h5>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="{{ $adminUser->username }}" disabled>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Email</label>
              <input type="text" class="form-control" value="{{ $adminUser->email }}" disabled>
            </div>
          </div>
        @endisset

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">Aggiorna Associazione</button>
          <a href="{{ route('associazioni.index') }}" class="btn btn-outline-secondary">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
