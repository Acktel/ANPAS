@extends('layouts.app')

@section('title', 'Modifica Totale a Bilancio')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Modifica Totale a Bilancio - Anno {{ $anno }}</h1>

    <form method="POST" action="{{ route('imputazioni.ossigeno.updateTotale') }}">
        @csrf
        <div class="form-group">
            <label for="TotaleBilancio">Totale a Bilancio (€)</label>
            <input type="number" step="0.01" class="form-control" id="TotaleBilancio" name="TotaleBilancio" value="{{ $totale }}" required>
        </div>
        <button type="submit" class="btn btn-anpas-green mt-3">Salva</button>
    </form>
</div>
@endsection
