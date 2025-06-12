@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h2 class="mb-4">➕ Aggiungi Voce {{ $sezione }} - Anno {{ $anno }}</h2>

  <form action="{{ route('riepilogo.costi.store', $idTipologia) }}" method="POST">
    @csrf
    <input type="hidden" name="idTipologiaRiepilogo" value="{{ $idTipologia }}">

    <div class="mb-3">
      <label for="descrizione" class="form-label">Descrizione</label>
      <input type="text" class="form-control" name="descrizione" required>
    </div>

    <div class="mb-3">
      <label for="preventivo" class="form-label">Preventivo</label>
      <input type="number" class="form-control" name="preventivo" step="0.01" required>
    </div>

    <div class="mb-3">
      <label for="consuntivo" class="form-label">Consuntivo</label>
      <input type="number" class="form-control" name="consuntivo" step="0.01" required>
    </div>

    <button type="submit" class="btn btn-success">Salva</button>
    <a href="{{ route('riepilogo.costi') }}" class="btn btn-secondary">Annulla</a>
  </form>
</div>
@endsection
