@extends('layouts.app')
@php
$user = Auth::user();
$isImpersonating = session()->has('impersonate');
$annoCorr = session('anno_riferimento', now()->year);
$assoCorr = $associazioni->firstWhere('idAssociazione', $conv->idAssociazione);
@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Modifica Convenzione #{{ $conv->idConvenzione }}
  </h1>

  @if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $e)
      <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('convenzioni.update', $conv->idConvenzione) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
          {{-- Associazione --}}
          <div class="col-md-6 mb-3">
            <label class="form-label">Associazione</label>
            <input type="text" class="form-control" value="{{ $assoCorr->Associazione }}" readonly>
            <input type="hidden" name="idAssociazione" value="{{ $assoCorr->idAssociazione }}">
          </div>


          {{-- Anno --}}
          @if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
          <div class="col-md-6 mb-3">
            <label for="idAnno" class="form-label">Anno</label>
            <select name="idAnno" id="idAnno" class="form-select" required>
              @foreach($anni as $annoRec)
              <option value="{{ $annoRec->idAnno }}"
                {{ old('idAnno', $conv->idAnno) == $annoRec->idAnno ? 'selected' : '' }}>
                {{ $annoRec->anno }}
              </option>
              @endforeach
            </select>
          </div>
          @else
          <div class="col-md-6 mb-3">
            <label class="form-label">Anno</label>
            <input type="text" class="form-control" value="{{ $conv->idAnno }}" readonly>
            <input type="hidden" name="idAnno" value="{{ $conv->idAnno }}">
          </div>
          @endif
        </div>


        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Descrizione</label>
            <input type="text"
              style="text-transform: uppercase;"
              name="Convenzione"
              class="form-control"
              value="{{ old('Convenzione', $conv->Convenzione) }}"
              required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Lettera identificativa</label>
            <input type="text"
              style="text-transform: uppercase;"
              name="lettera_identificativa"
              class="form-control"
              value="{{ old('lettera_identificativa', $conv->lettera_identificativa) }}"
              maxlength="5"
              required>
          </div>
        </div>

        <div class="d-flex justify-content-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Aggiorna Convenzione
          </button>
          <a href="{{ route('convenzioni.index', [
                'idAssociazione' => old('idAssociazione', $conv->idAssociazione),
                'idAnno' => old('idAnno', $conv->idAnno) ]) }}" class="btn btn-secondary">
            Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection