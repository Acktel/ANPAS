{{-- resources/views/riepilogo_costi/edit_carburante_additivi.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-2">Modifica CARBURANTI E ADDITIVI</h1>

    <p class="text-muted mb-4">
        Anno <strong>{{ $anno }}</strong> — Associazione <strong>{{ $nomeAssociazione }}</strong>
        — Convenzione <strong>{{ $nomeConvenzione }}</strong>
    </p>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="card-anpas">
        <div class="card-body bg-anpas-white">
            <form action="{{ route('riepilogo.costi.update.carburante_additivi') }}"
                method="POST"
                class="row g-3">
                @csrf
                @method('PUT')

                <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
                <input type="hidden" name="idConvenzione" value="{{ $idConvenzione }}">
                <input type="hidden" name="idCarb" value="{{ $idCarb }}">
                <input type="hidden" name="idAdd" value="{{ $idAdd}}">

                <div class="col-md-6">
                    <label class="form-label">
                        Preventivo {{ strtoupper($descCarb ?? 'Carburanti') }}
                    </label>
                    <input type="number"
                        step="0.01"
                        min="0"
                        name="preventivo_carb"
                        class="form-control @error('preventivo_carb') is-invalid @enderror"
                        value="{{ old('preventivo_carb', $prevCarb) }}">
                    @error('preventivo_carb') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-muted">
                        Consuntivo: {{ number_format($consCarb, 2, ',', '.') }}
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Preventivo {{ strtoupper($descAdd ?? 'Additivi') }}
                    </label>
                    <input type="number"
                        step="0.01"
                        min="0"
                        name="preventivo_add"
                        class="form-control @error('preventivo_add') is-invalid @enderror"
                        value="{{ old('preventivo_add', $prevAdd) }}">
                    @error('preventivo_add') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-muted">
                        Consuntivo: {{ number_format($consAdd, 2, ',', '.') }}
                    </small>
                </div>

                <div class="col-12 mt-2">
                    <button class="btn btn-anpas-green">
                        <i class="fas fa-check me-1"></i> Salva
                    </button>
                    <a href="{{ route('riepilogo.costi', ['idAssociazione'=>$idAssociazione,'idConvenzione'=>$idConvenzione]) }}"
                        class="btn btn-secondary ms-2">Annulla</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection