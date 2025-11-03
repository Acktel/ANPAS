@extends('layouts.app')

@php
$user = Auth::user();
$isImpersonating = session()->has('impersonate');
$annoCorr = session('anno_riferimento', now()->year);
$assoCorr = $associazioni->firstWhere('IdAssociazione', $user->IdAssociazione);

@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Nuovo Dipendente</h1>

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('dipendenti.store') }}" method="POST">
        @csrf

        {{-- Associazione e Anno --}}
        <div class="row mb-3">
            <p class="text-muted mb-4">
            Associazione #{{ $assoCorr->Associazione }} — Anno {{ $annoCorr}}
          </p>
        </div>
        {{-- RIGA 1: Associazione | Anno --}}
        <div class="row mb-3">
          <input type="hidden" name="IdAssociazione" value="{{ session('associazione_selezionata') }}">
          <input type="hidden" name="idAnno" value="{{ $annoCorr }}">
        </div>
        {{-- Nome / Cognome --}}
        <div class="row mb-3">          
          <div class="col-md-6 mb-3">
            <label for="DipendenteNome" class="form-label">Nome</label>
            <input type="text" name="DipendenteNome" id="DipendenteNome" class="form-control" maxlength="100"
              value="{{ old('DipendenteNome') }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="DipendenteCognome" class="form-label">Cognome</label>
            <input type="text" name="DipendenteCognome" id="DipendenteCognome" class="form-control" maxlength="100"
              value="{{ old('DipendenteCognome') }}" required>
          </div>
        </div>

        {{-- Qualifica multipla --}}
        <div class="row mb-3">
          <div class="col-md-6 mb-3">
            <label for="QualificaSelect" class="form-label">Qualifica</label>
            <select id="QualificaSelect" class="form-select" name="Qualifica[]" multiple required>
              @foreach($qualifiche as $q)
              <option value="{{ $q->id }}"
                {{ collect(old('Qualifica'))->contains($q->id) ? 'selected' : '' }}>
                {{ $q->nome }}
              </option>
              @endforeach
            </select>
            <div class="form-text">
              Seleziona una o più qualifiche. Scrivi per cercare.
            </div>
          </div>

          {{-- Contratto --}}
          <div class="col-md-6 mb-3">
            <label for="ContrattoApplicato" class="form-label">Contratto Applicato</label>
            <select name="ContrattoApplicato" id="ContrattoApplicato" class="form-select" required>
              @foreach($contratti as $c)
              <option value="{{ $c->id }}" {{ old('ContrattoApplicato') == $c->id ? 'selected' : '' }}>
                {{ $c->nome }}
              </option>
              @endforeach
            </select>
          </div>

          {{-- Livello Mansione --}}
          <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <label for="LivelloMansione" class="form-label">Livello Mansione</label>
            <input type="text" name="LivelloMansione" id="LivelloMansione" class="form-control" required/>
            <div class="form-text">
              Inserisci il livello mansione del dipendente.
            </div>
          </div>

          <div class="col-md-6">
            <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="3">{{ old('note') }}</textarea>
          </div>
        </div>

          {{-- Pulsanti --}}
          <div class="text-center">
            <button type="submit" class="btn btn-anpas-green me-3"><i class="fas fa-check me-1"></i>Salva Dipendente</button>
            <a href="{{ route('dipendenti.index') }}" class="btn btn-secondary">Annulla</a>
          </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const select = new TomSelect("#QualificaSelect", {
      plugins: ['remove_button'],
      maxOptions: null,
      create: false,
      persist: false,
      placeholder: "Seleziona una o più qualifiche",
    });

    // Se vuoi ancora mostrare un suggerimento automatico del livello mansione
    // puoi loggare o mostrare in un div separato, ma non usare readOnly su <select>
    select.on('change', function() {
      const selected = select.getValue();
      if (selected.length === 1) {
        const option = select.input.querySelector(`option[value="${selected[0]}"]`);
        const livello = option?.dataset?.livello || '';
        console.log("Suggerito livello:", livello);
        // potresti anche visualizzarlo in un div tipo: document.getElementById('livelloSuggerito').textContent = livello;
      }
    });
  });
</script>
@endpush