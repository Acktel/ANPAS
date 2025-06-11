@extends('layouts.app')

@php
    $user = Auth::user();
    $isImpersonating = session()->has('impersonate');
@endphp

@section('content')
<div class="container-fluid">
  <h1>Modifica Dipendente #{{ $dipendente->idDipendente }}</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <a href="{{ route('dipendenti.index') }}" class="btn btn-secondary mb-3">
    ← Torna all’elenco Dipendenti
  </a>

  <form action="{{ route('dipendenti.update', $dipendente->idDipendente) }}" method="POST">
    @csrf
    @method('PUT')

    {{-- Seleziona Associazione o mostra readonly per AdminUser/impersonazione --}}
    @if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
      <div class="mb-3">
        <label for="idAssociazione" class="form-label">Associazione</label>
        <select name="idAssociazione" id="idAssociazione" class="form-select" required>
          <option value="">-- Seleziona Associazione --</option>
          @foreach($associazioni as $asso)
            <option value="{{ $asso->idAssociazione }}"
              {{ old('idAssociazione', $dipendente->idAssociazione) == $asso->idAssociazione ? 'selected' : '' }}>
              {{ $asso->Associazione }}
            </option>
          @endforeach
        </select>
      </div>
    @else
      @php
        $assoCorr = $associazioni->firstWhere('idAssociazione', $dipendente->idAssociazione);
      @endphp
      <div class="mb-3">
        <label class="form-label">Associazione</label>
        <input type="text"
               class="form-control"
               value="{{ $assoCorr->Associazione }}"
               readonly>
        <input type="hidden" name="idAssociazione" value="{{ $assoCorr->idAssociazione }}">
      </div>
    @endif

    {{-- Seleziona Anno --}}
    <div class="mb-3">
      <label for="idAnno" class="form-label">Anno</label>
      <select name="idAnno" id="idAnno" class="form-select" required>
        @foreach($anni as $annoRec)
          <option value="{{ $annoRec->idAnno }}"
            {{ old('idAnno', $dipendente->idAnno) == $annoRec->idAnno ? 'selected' : '' }}>
            {{ $annoRec->anno }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="row">
      {{-- Nome --}}
      <div class="col-md-4 mb-3">
        <label for="DipendenteNome" class="form-label">Nome</label>
        <input type="text"
               name="DipendenteNome"
               id="DipendenteNome"
               class="form-control"
               maxlength="100"
               value="{{ old('DipendenteNome', $dipendente->DipendenteNome) }}"
               required>
      </div>

      {{-- Cognome --}}
      <div class="col-md-4 mb-3">
        <label for="DipendenteCognome" class="form-label">Cognome</label>
        <input type="text"
               name="DipendenteCognome"
               id="DipendenteCognome"
               class="form-control"
               maxlength="100"
               value="{{ old('DipendenteCognome', $dipendente->DipendenteCognome) }}"
               required>
      </div>
    </div>

    <div class="row">
      {{-- Qualifica (stringa CSV) --}}
      <div class="col-md-4 mb-3">
        <label for="Qualifica" class="form-label">Qualifica</label>
        <input type="text"
               name="Qualifica"
               id="Qualifica"
               class="form-control"
               maxlength="255"
               value="{{ old('Qualifica', $dipendente->Qualifica) }}"
               placeholder="Es. AUTISTA,SOCCORRITORE"
               required>
        <div class="form-text">
          Separa più qualifiche con la virgola, es. “AUTISTA,SOCCORRITORE”.
        </div>
      </div>

      {{-- Contratto Applicato --}}
      <div class="col-md-4 mb-3">
        <label for="ContrattoApplicato" class="form-label">Contratto Applicato</label>
        <input type="text"
               name="ContrattoApplicato"
               id="ContrattoApplicato"
               class="form-control"
               maxlength="100"
               value="{{ old('ContrattoApplicato', $dipendente->ContrattoApplicato) }}"
               required>
      </div>

      {{-- Livello Mansione --}}
      <div class="col-md-4 mb-3">
        <label for="LivelloMansione" class="form-label">Livello Mansione</label>
        <input type="text"
               name="LivelloMansione"
               id="LivelloMansione"
               class="form-control"
               maxlength="10"
               value="{{ old('LivelloMansione', $dipendente->LivelloMansione) }}"
               required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Aggiorna Dipendente</button>
  </form>
</div>
@endsection
