@extends('layouts.app')

@section('title', 'Modifica Totale a Bilancio')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Modifica Totale a Bilancio - Anno {{ $anno }}</h1>

    <form method="POST" action="{{ route('imputazioni.materiale_sanitario.updateTotale') }}">
        @csrf
        <div class="form-group mb-3">
            <label for="TotaleBilancio">Totale a Bilancio (€)</label>
            <input 
                type="number" 
                step="0.01" 
                min="0"
                class="form-control" 
                id="TotaleBilancio" 
                name="TotaleBilancio" 
                value="{{ old('TotaleBilancio', $totale) }}" 
                required>
        </div>

        <div class="d-flex align-items-center my-3 myborder-button">
            <button type="submit" class="btn btn-anpas-green me-2">
                <i class="fas fa-check me-1"></i> Salva
            </button>
            <a href="{{ route('imputazioni.materiale_sanitario.index') }}" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i> Annulla
            </a>
        </div>
    </form>
</div>
@endsection
