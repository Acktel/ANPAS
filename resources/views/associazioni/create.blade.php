@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <h1>Crea una Nuova Associazione</h1>

    {{-- Mostra eventuali errori di validazione --}}
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('associazioni.store') }}" method="POST">
      @csrf

      <div class="mb-3">
        <label for="Associazione" class="form-label">Nome Associazione</label>
        <input
          type="text"
          class="form-control"
          id="Associazione"
          name="Associazione"
          value="{{ old('Associazione') }}"
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
          value="{{ old('email') }}"
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
          value="{{ old('provincia') }}"
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
          value="{{ old('citta') }}"
          required
        >
      </div>

      {{-- ATTENZIONE: qui il campo DEVE chiamarsi “adminuser_name”, non “supervisor_name” --}}
      <div class="mb-3">
        <label for="adminuser_name" class="form-label">Nome Admin</label>
        <input
          type="text"
          class="form-control"
          id="adminuser_name"
          name="adminuser_name"
          value="{{ old('adminuser_name') }}"
          required
        >
      </div>

      {{-- Anche qui DEVE essere “adminuser_email” --}}
      <div class="mb-3">
        <label for="adminuser_email" class="form-label">Email Admin</label>
        <input
          type="email"
          class="form-control"
          id="adminuser_email"
          name="adminuser_email"
          value="{{ old('adminuser_email') }}"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary">Crea Associazione</button>
    </form>
  </div>
@endsection
