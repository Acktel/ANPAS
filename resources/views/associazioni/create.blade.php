@extends('layouts.app')

@section('content')
  <div class="container-xl">
    <h1>Crea una Nuova Associazione</h1>

    <form action="{{ route('associazioni.store') }}" method="POST">
    @csrf

    <div class="mb-3">
      <label for="Associazione" class="form-label">Nome Associazione</label>
      <input type="text" class="form-control" id="Associazione" name="Associazione" required>
    </div>

    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input type="email" class="form-control" id="email" name="email" required>
    </div>

    <div class="mb-3">
      <label for="provincia" class="form-label">Provincia</label>
      <input type="text" class="form-control" id="provincia" name="provincia" required>
    </div>

    <div class="mb-3">
      <label for="citta" class="form-label">Città</label>
      <input type="text" class="form-control" id="citta" name="citta" required>
    </div>

    <div class="mb-3">
      <label for="supervisor_name" class="form-label">Nome Admin</label>
      <input type="text" name="supervisor_name" id="supervisor_name" class="form-control" required>
    </div>
    

    <div class="mb-3">
      <label for="supervisor_email" class="form-label">Email Admin</label>
      <input type="email" name="supervisor_email" id="supervisor_email" class="form-control" required>
    </div>


    <button type="submit" class="btn btn-primary">Crea Associazione</button>
    </form>
  </div>
@endsection