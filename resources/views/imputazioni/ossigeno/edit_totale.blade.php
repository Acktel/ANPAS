@extends('layouts.app')

@section('title', 'Modifica Totale a Bilancio')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Modifica Totale a Bilancio - Anno {{ $anno }}</h1>

    <form method="POST" action="{{ route('imputazioni.ossigeno.updateTotale') }}">
        @csrf
        <div class="form-group">
            <label for="TotaleBilancio">Totale a Bilancio (€)</label>
            <input type="number" step=1.00" class="form-control" id="TotaleBilancio" name="TotaleBilancio" value="{{ $totale }}" required>
        </div>
<div class="d-flex align-items-center my-3">
    <button type="submit" class="btn btn-anpas-green me-2">
        <i class="fas fa-check me-1"></i>Salva
    </button>
    <a href="{{ route('imputazioni.materiale_sanitario.index') }}" class="btn btn-secondary">
        <i class="fas fa-times me-1"></i>Annulla
    </a>
</div>
    </form>
</div>
@endsection
