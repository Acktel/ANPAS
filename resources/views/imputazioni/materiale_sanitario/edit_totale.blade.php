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
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="note">Note</label>
                <textarea
                    id="note"
                    name="note"
                    class="form-control"
                    rows="3"
                    maxlength="2000"
                    placeholder="Note...">{{ old('note', $note ?? '') }}</textarea>
            </div>
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