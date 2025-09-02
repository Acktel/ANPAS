@extends('layouts.app')
@php
$user = Auth::user();
$isImpersonating = session()->has('impersonate');
@endphp
@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Nuova Convenzione
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
      <form action="{{ route('convenzioni.store') }}" method="POST">
        @csrf

        <div class="row">
          {{-- Associazione --}}
          <div class="col-md-6 mb-3">
            <label for="idAssociazione" class="form-label">Associazione</label>
            <select name="idAssociazione" id="idAssociazione" class="form-select" required>
              @foreach($associazioni as $assoc)
              <option value="{{ $assoc->idAssociazione }}"
                {{ old('idAssociazione') == $assoc->idAssociazione ? 'selected' : '' }}>
                {{ $assoc->Associazione }}
              </option>
              @endforeach
            </select>
          </div>

          {{-- Anno --}}
          <div class="col-md-6 mb-3">
            <label for="idAnno" class="form-label">Anno</label>
            <select name="idAnno" id="idAnno" class="form-select" required>
              @foreach($anni as $anno)
              <option value="{{ $anno->idAnno }}"
                {{ old('idAnno', session('anno_riferimento')) == $anno->idAnno ? 'selected' : '' }}>
                {{ $anno->Anno }}
              </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row">
          {{-- Descrizione --}}
          <div class="col-md-6 mb-3">
            <label for="Convenzione" class="form-label">Descrizione</label>
            <input type="text" name="Convenzione" class="form-control" value="{{ old('Convenzione') }}" required>
          </div>

          {{-- Lettera identificativa --}}
          <div class="col-md-6 mb-3">
            <label for="lettera_identificativa" class="form-label">Lettera identificativa</label>
            <input type="text" name="lettera_identificativa" class="form-control"
                   value="{{ old('lettera_identificativa') }}" maxlength="5" required>
          </div>
        </div>

        {{-- Aziende Sanitarie associate --}}
        <div class="row">
          <div class="col-md-2 mb-3">
            <label for="aziende_sanitarie" class="form-label">Aziende Sanitarie associate</label>
          <select name="aziende_sanitarie[]" id="aziende_sanitarie" class="form-select" multiple size="6">
            @foreach($aziendeSanitarie as $az)
              <option value="{{ $az->idAziendaSanitaria }}">{{ $az->Nome }}</option>
            @endforeach
          </select>

            <small class="form-text text-muted">Puoi selezionare una o più aziende sanitarie</small>
          </div>


          <div class="col-md-4"></div>
          

          {{-- Materiale sanitario di consumo --}}
        <div class="col-md-2 mb-3">
            <label for="Qualifica" class="form-label">Materiale sanitario</label>
            <select name="materiali[]" id="materiali" class="form-select" multiple size="6">
              @foreach($materiali as $materiale)
                  <option value="{{ $materiale->id }}"
                      @if(!empty($materialiSelezionati) && in_array($materiale->id, $materialiSelezionati)) selected @endif>
                      {{ $materiale->descrizione }}
                  </option>
              @endforeach
            </select>
            <div class="form-text">Seleziona uno o più materiali sanitari. (CTRL/CMD per selezione multipla)</div>
          </div>

          {{-- RIGA 9: Note --}}
          <div class="row">
              <div class="col-md-6">
                  <label for="note" class="form-label">Note</label>
                  <textarea name="note" id="note" class="form-control" rows="3">{{ old('note') }}</textarea>
              </div>
          </div>

        <div class="d-flex justify-content-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Crea Convenzione
          </button>
          <a href="{{ route('convenzioni.index', [
                'idAssociazione' => $selectedAssoc,
                'idAnno' => $selectedAnno
            ]) }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i>Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
