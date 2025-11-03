@extends('layouts.app')

@php
  $user = Auth::user();
  $isImpersonating = session()->has('impersonate');
$assoCorr = $associazioni->firstWhere('idAssociazione', $dipendente->idAssociazione);
  // Valori selezionati
  $qualificheSelezionate = old('Qualifica', $qualificheAttuali ?? []);
  $livelliSelezionati = old('LivelloMansione', \App\Models\Dipendente::getLivelliMansioneByDipendente($dipendente->idDipendente ) ?? []);
  $annoCorr = session('anno_riferimento', now()->year);
@endphp

@section('content')
<div class="container-fluid">
  
  <h1 class="container-title mb-4">Modifica Dipendente #{{ $dipendente->idDipendente }}</h1>
    <p class="text-muted mb-4">
    Associazione #{{ $assoCorr->Associazione }} — Anno {{ $annoCorr}}
  </p>
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
      <form action="{{ route('dipendenti.update', $dipendente->idDipendente) }}" method="POST">
        @csrf
        @method('PUT')

        </div>
        {{-- RIGA 1: Associazione | Anno --}}
        <div class="row mb-3">
          <input type="hidden" name="idAssociazione" value="{{ $dipendente->idAssociazione }}">
          <input type="hidden" name="idAnno" value="{{ $annoCorr }}">
        </div>
        {{-- Nome / Cognome --}}
        <div class="row mb-3">
          <div class="col-md-6 mb-3">
            <label for="DipendenteNome" class="form-label">Nome</label>
            <input type="text" name="DipendenteNome" id="DipendenteNome" class="form-control"
                   maxlength="100" value="{{ old('DipendenteNome', $dipendente->DipendenteNome) }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="DipendenteCognome" class="form-label">Cognome</label>
            <input type="text" name="DipendenteCognome" id="DipendenteCognome" class="form-control"
                   maxlength="100" value="{{ old('DipendenteCognome', $dipendente->DipendenteCognome) }}" required>
          </div>
        </div>

        {{-- Qualifica multipla --}}
        <div class="row mb-3">
          <div class="col-md-6 mb-3">
            <label for="Qualifica" class="form-label">Qualifica</label>
            <select name="Qualifica[]" id="Qualifica" class="form-select" multiple required>
              @foreach ($qualifiche as $q)
                <option value="{{ $q->id }}"
                  {{ in_array($q->id, $qualificheSelezionate) ? 'selected' : '' }}>
                  {{ $q->nome }}
                </option>
              @endforeach
            </select>
            <div class="form-text">Seleziona una o più qualifiche. (CTRL/CMD per selezione multipla)</div>
          </div>

          {{-- Contratto --}}
          <div class="col-md-6 mb-3">
            <label for="ContrattoApplicato" class="form-label">Contratto Applicato</label>
            <select name="ContrattoApplicato" id="ContrattoApplicato" class="form-select" required>
              <option value="">-- Seleziona contratto --</option>
              @foreach ($contratti as $c)
                <option value="{{ $c->nome }}"
                  {{ old('ContrattoApplicato', $dipendente->ContrattoApplicato) == $c->nome ? 'selected' : '' }}>
                  {{ $c->nome }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Livello Mansione multiplo --}}
        <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <label for="LivelloMansione" class="form-label">Livello Mansione</label>
            <input type="text" name="LivelloMansione" id="LivelloMansione" class="form-control"
                   value="{{ old('LivelloMansione', $dipendente->LivelloMansione ?? '') }}" required />
            <div class="form-text">
              Inserisci il livello mansione del dipendente.
            </div>
          </div>



          <div class="col-md-6">
            <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="3">{{ old('note', $dipendente->note) }}</textarea>
          </div>
        </div>

        {{-- Pulsanti --}}
        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3"><i class="fas fa-check me-1"></i>Salva Dipendente</button>
          <a href="{{ route('dipendenti.index') }}" class="btn btn-secondary"> <i class="fas fa-times me-1"></i>Annulla</a>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection
