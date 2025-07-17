@extends('layouts.app')
@section('content')
<div class="container-fluid">
  {{-- Titolo --}}
  <h1 class="container-title mb-4">
    Modifica Costi Dipendente:

  </h1>

  {{-- Errori di validazione --}}
  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('ripartizioni.personale.costi.update', $record->idDipendente) }}" method="POST">
        @csrf
        @method('PUT')

        <input type="hidden" name="idDipendente" value="{{ $record->idDipendente }}">

        <div class="row mb-3">
          {{-- Cognome --}}
          <div class="col-md-6">
            <label class="form-label">Dipendente</label>
            <input type="text" class="form-control" value="{{ $record->DipendenteCognome }} {{ $record->DipendenteNome }}" disabled>
          </div>
          {{-- Anno --}}
          <div class="col-md-6">
            <label class="form-label">Anno</label>
            <input type="text" class="form-control" value="{{ $anno }}" disabled>
          </div>
        </div>

        <hr>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Retribuzioni</label>
            <input type="number" name="Retribuzioni" step="0.01" class="form-control"
              value="{{ old('Retribuzioni', $record->Retribuzioni) }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">Oneri Sociali</label>
            <input type="number" name="OneriSociali" step="0.01" class="form-control"
              value="{{ old('OneriSociali', $record->OneriSociali) }}">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">TFR</label>
            <input type="number" name="TFR" step="0.01" class="form-control"
              value="{{ old('TFR', $record->TFR) }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">Consulenze</label>
            <input type="number" name="Consulenze" step="0.01" class="form-control"
              value="{{ old('Consulenze', $record->Consulenze) }}">
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-6">
            <label class="form-label">Totale</label>
            <input type="number" name="Totale" step="0.01" class="form-control"
              value="{{ old('Totale', $record->Totale) }}">
          </div>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-save me-1"></i> Salva Modifiche
          </button>
          <a href="{{ route('ripartizioni.personale.costi.index') }}" class="btn btn-secondary">
            Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

 