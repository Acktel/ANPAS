@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    <i class="fas fa-edit me-1"></i>
    Modifica Voce {{ $sezione }} − Anno {{ $anno }}
  </h1>

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('riepilogo.costi.update', $voce->id) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="idTipologiaRiepilogo" value="{{ $voce->idTipologiaRiepilogo }}">

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="descrizione" class="form-label">Descrizione</label>
            <input type="text"
                   id="descrizione"
                   name="descrizione"
                   class="form-control"
                   value="{{ old('descrizione', $voce->descrizione) }}"
                   required>
          </div>
          <div class="col-md-3 mb-3">
            <label for="preventivo" class="form-label">Preventivo</label>
            <input type="number"
                   id="preventivo"
                   name="preventivo"
                   step="0.01"
                   class="form-control"
                   value="{{ old('preventivo', $voce->preventivo) }}"
                   required>
          </div>
          <div class="col-md-3 mb-3">
            <label for="consuntivo" class="form-label">Consuntivo</label>
            <input type="number"
                   id="consuntivo"
                   name="consuntivo"
                   step="0.01"
                   class="form-control"
                   value="{{ old('consuntivo', $voce->consuntivo) }}"
                   required>
          </div>
        </div>

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-red me-2">
            <i class="fas fa-check me-1"></i> Aggiorna
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
