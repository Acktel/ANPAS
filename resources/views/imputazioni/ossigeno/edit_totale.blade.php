@extends('layouts.app')
@php
use App\Models\Associazione;

$user = Auth::user();
$isImpersonating = session()->has('impersonate');

$assocCorr = Associazione::getById($idAssociazione);
$annoCorr = session('annoCorrente') ?? ($automezzo->idAnno ?? now()->year);

@endphp
@section('title', 'Modifica Totale a Bilancio')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Modifica Totale a Bilancio </h1>
    <p class="text-muted mb-4">
    Associazione {{ $assocCorr->Associazione }} — Anno {{ $anno }}
    </p>
    <form method="POST" action="{{ route('imputazioni.ossigeno.updateTotale') }}">
        @csrf
        <div class="form-group">
            <label for="TotaleBilancio">Totale a Bilancio (€)</label>
            <input type="number" step="0.01" class="form-control" id="TotaleBilancio" name="TotaleBilancio" value="{{ $totale }}" required>
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
