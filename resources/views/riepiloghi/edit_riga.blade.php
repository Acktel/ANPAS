{{-- resources/views/riepiloghi/edit_riga.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Modifica voce #{{ $riga->id }} — Riepilogo #{{ $riga->idRiepilogo }}
  </h1>

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
      {{-- Info contesto --}}
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Voce di riepilogo</label>
          <input type="text" class="form-control" value="{{ $riga->voce_descrizione }}" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Anno</label>
          <input type="text" class="form-control" value="{{ $riga->idAnno }}" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Convenzione</label>
          <input type="text" class="form-control" value="{{ $riga->convenzione_descrizione }}" readonly>
        </div>
      </div>

      <form action="{{ route('riepiloghi.riga.update', $riga->id) }}" method="POST" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-md-4">
          <label class="form-label">Preventivo</label>
          <input
            type="number"
            step=1.00"
            min="0"
            name="preventivo"
            class="form-control"
            value="{{ old('preventivo', $riga->preventivo) }}"
            required
          >
        </div>

        {{-- Se/Quando vorrai abilitare anche il consuntivo
        <div class="col-md-4">
          <label class="form-label">Consuntivo</label>
          <input
            type="number"
            step=1.00"
            min="0"
            name="consuntivo"
            class="form-control"
            value="{{ old('consuntivo', $riga->consuntivo) }}"
          >
        </div>
        --}}

        <div class="col-12">
          <button class="btn btn-anpas-green">
            <i class="fas fa-check me-1"></i> Salva
          </button>
          <a href="{{ route('riepiloghi.index') }}" class="btn btn-secondary ms-2">
            Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
