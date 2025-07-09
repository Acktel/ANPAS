@extends('layouts.app')

@php
  $user = Auth::user();
  $isImpersonating = session()->has('impersonate');
  $annoCorr = session('anno_riferimento', now()->year);
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
          @if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
            <div class="col-md-6 mb-3">
              <label for="idAssociazione" class="form-label">Associazione</label>
              <select name="idAssociazione" id="idAssociazione" class="form-select" required>
                <option value="">-- Seleziona Associazione --</option>
                @foreach($associazioni as $asso)
                  <option value="{{ $asso->idAssociazione }}"
                    {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}>
                    {{ $asso->Associazione }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label for="idAnno" class="form-label">Anno</label>
              <select name="idAnno" id="idAnno" class="form-select" required>
                <option value="">-- Seleziona Anno --</option>
                @foreach($anni as $annoRec)
                  <option value="{{ $annoRec->idAnno }}"
                    {{ old('idAnno', $annoCorr) == $annoRec->idAnno ? 'selected' : '' }}>
                    {{ $annoRec->anno }}
                  </option>
                @endforeach
              </select>
            </div>
          @else
            @php
              $assoCorr = $associazioni->firstWhere('idAssociazione', $user->IdAssociazione);
            @endphp
            <div class="col-md-6 mb-3">
              <label class="form-label">Associazione</label>
              <input type="text" class="form-control" value="{{ $assoCorr->Associazione }}" readonly>
              <input type="hidden" name="idAssociazione" value="{{ $assoCorr->idAssociazione }}">
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Anno</label>
              <input type="text" class="form-control" value="{{ $annoCorr }}" readonly>
              <input type="hidden" name="idAnno" value="{{ $annoCorr }}">
            </div>
          @endif
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
            <select id="QualificaSelect" class="form-select" multiple>
              @foreach($qualifiche as $q)
                <option value="{{ $q->nome }}" data-livello="{{ $q->livello_mansione }}"
                  {{ collect(explode(',', old('Qualifica')))->contains($q->nome) ? 'selected' : '' }}>
                  {{ $q->nome }}
                </option>
              @endforeach
            </select>
            <input type="hidden" name="Qualifica" id="QualificaHidden" value="{{ old('Qualifica') }}">
            <div class="form-text">
              Seleziona una o più qualifiche. Scrivi per cercare. Il livello si autocompila se selezioni una sola qualifica.
            </div>
          </div>

          {{-- Contratto --}}
          <div class="col-md-6 mb-3">
            <label for="ContrattoApplicato" class="form-label">Contratto Applicato</label>
            <input type="text" name="ContrattoApplicato" id="ContrattoApplicato" class="form-control" maxlength="100"
                   value="{{ old('ContrattoApplicato') }}" required>
          </div>
        </div>

        {{-- Livello Mansione --}}
        <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <label for="LivelloMansione" class="form-label">Livello Mansione</label>
            <input type="text" name="LivelloMansione" id="LivelloMansione" class="form-control" maxlength="100"
                   value="{{ old('LivelloMansione') }}" required>
          </div>
        </div>

        {{-- Pulsanti --}}
        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3">Salva Dipendente</button>
          <a href="{{ route('dipendenti.index') }}" class="btn btn-secondary">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const select = new TomSelect("#QualificaSelect", {
      plugins: ['remove_button'],
      maxOptions: null,
      create: false,
      persist: false,
      placeholder: "Seleziona una o più qualifiche",
    });

    const livelloInput = document.getElementById('LivelloMansione');
    const hiddenInput = document.getElementById('QualificaHidden');

    function aggiornaLivello() {
      const selected = select.getValue();
      hiddenInput.value = selected.join(',');

      if (selected.length === 1) {
        const option = select.input.querySelector(`option[value="${selected[0]}"]`);
        const livello = option?.dataset?.livello || '';
        livelloInput.value = livello;
        livelloInput.readOnly = true;
      } else {
        livelloInput.value = '';
        livelloInput.readOnly = false;
      }
    }

    select.on('change', aggiornaLivello);
    aggiornaLivello();
  });
</script>
@endpush
