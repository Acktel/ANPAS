@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <h1>Modifica Associazione</h1>

    {{-- Mostra errori validazione --}}
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('associazioni.update', $associazione->IdAssociazione) }}" method="POST">
      @csrf
      @method('PUT')

      <div class="mb-3">
        <label for="Associazione" class="form-label">Nome Associazione</label>
        <input
          type="text"
          class="form-control"
          id="Associazione"
          name="Associazione"
          value="{{ old('Associazione', $associazione->Associazione) }}"
          required
        >
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input
          type="email"
          class="form-control"
          id="email"
          name="email"
          value="{{ old('email', $associazione->email) }}"
          required
        >
      </div>

      <div class="mb-3">
        <label for="provincia" class="form-label">Provincia</label>
        <input
          type="text"
          class="form-control"
          id="provincia"
          name="provincia"
          value="{{ old('provincia', $associazione->provincia) }}"
          required
        >
      </div>

      <div class="mb-3">
        <label for="citta" class="form-label">Città</label>
        <input
          type="text"
          class="form-control"
          id="citta"
          name="citta"
          value="{{ old('citta', $associazione->citta) }}"
          required
        >
      </div>

      {{-- OPTIONAL: Se vuoi mostrare i dati dell'admin user associato (readonly) --}}
      @isset($adminUser)
        <hr>
        <h5>Dati Amministratore Associazione</h5>

        <div class="mb-3">
          <label class="form-label">Nome Utente</label>
          <input type="text" class="form-control" value="{{ $adminUser->username }}" disabled>
        </div>

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="text" class="form-control" value="{{ $adminUser->email }}" disabled>
        </div>
      @endisset

      <button type="submit" class="btn btn-success">Aggiorna Associazione</button>
      <a href="{{ route('associazioni.index') }}" class="btn btn-secondary ms-2">Annulla</a>
    </form>
  </div>
@endsection
