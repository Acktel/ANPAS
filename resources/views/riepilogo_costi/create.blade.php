@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    <i class="fas fa-plus me-1"></i>
    Aggiungi Voce {{ $sezione }} − Anno {{ $anno }}
  </h1>

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('riepilogo.costi.store', $idTipologia) }}" method="POST">
        @csrf
        <input type="hidden" name="idTipologiaRiepilogo" value="{{ $idTipologia }}">

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="descrizione" class="form-label">Descrizione</label>
            <input type="text" class="form-control" id="descrizione" name="descrizione" required>
          </div>
          <div class="col-md-3 mb-3">
            <label for="preventivo" class="form-label">Preventivo</label>
            <input type="number" class="form-control" id="preventivo" name="preventivo" step=1.00" required>
          </div>
          <div class="col-md-3 mb-3">
            <label for="consuntivo" class="form-label">Consuntivo</label>
            <input type="number" class="form-control" id="consuntivo" name="consuntivo" step=1.00" required>
          </div>
        </div>

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-save me-1"></i> Salva
          </button>
          <a href="{{ route('riepilogo.costi') }}" class="btn btn-secondary">
            Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
