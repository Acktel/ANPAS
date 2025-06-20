@extends('layouts.app')

@php
  $user = Auth::user();
  $isImpersonating = session()->has('impersonate');
@endphp

@section('content')
<div class="container-fluid">
  {{-- Titolo --}}
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

  {{-- Card contenitore form --}}
  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      
      <form action="{{ route('dipendenti.store') }}" method="POST">
        @csrf

        {{-- Associazione --}}
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
          @else
            @php
              $assoCorr = $associazioni->firstWhere('idAssociazione', $user->IdAssociazione);
            @endphp
            <div class="col-md-6 mb-3">
              <label class="form-label">Associazione</label>
              <input type="text"
                     class="form-control"
                     value="{{ $assoCorr->Associazione }}"
                     readonly>
              <input type="hidden" name="idAssociazione" value="{{ $assoCorr->idAssociazione }}">
            </div>
          @endif

          {{-- Anno --}}
          @php $annoCorr = session('anno_riferimento', now()->year); @endphp
          @if (! $isImpersonating && $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']))
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
            <input type="text"
                   name="DipendenteNome"
                   id="DipendenteNome"
                   class="form-control"
                   maxlength="100"
                   value="{{ old('DipendenteNome') }}"
                   required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="DipendenteCognome" class="form-label">Cognome</label>
            <input type="text"
                   name="DipendenteCognome"
                   id="DipendenteCognome"
                   class="form-control"
                   maxlength="100"
                   value="{{ old('DipendenteCognome') }}"
                   required>
          </div>
        </div>

        {{-- Qualifica / Contratto --}}
        <div class="row mb-3">
          <div class="col-md-6 mb-3">
            <label for="Qualifica" class="form-label">Qualifica</label>
            <input type="text"
                   name="Qualifica"
                   id="Qualifica"
                   class="form-control"
                   maxlength="255"
                   value="{{ old('Qualifica') }}"
                   placeholder="Es. AUTISTA,SOCCORRITORE"
                   required>
            <div class="form-text">
              Separa più qualifiche con la virgola, es. “AUTISTA,SOCCORRITORE”.
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="ContrattoApplicato" class="form-label">Contratto Applicato</label>
            <input type="text"
                   name="ContrattoApplicato"
                   id="ContrattoApplicato"
                   class="form-control"
                   maxlength="100"
                   value="{{ old('ContrattoApplicato') }}"
                   required>
          </div>
        </div>

        {{-- Livello Mansione --}}
        <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <label for="LivelloMansione" class="form-label">Livello Mansione</label>
            <input type="text"
                   name="LivelloMansione"
                   id="LivelloMansione"
                   class="form-control"
                   maxlength="100"
                   value="{{ old('LivelloMansione') }}"
                   required>
          </div>
        </div>

        {{-- Pulsanti Salva / Annulla --}}
        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3">
            Salva Dipendente
          </button>
          <a href="{{ route('dipendenti.index') }}" class="btn btn-secondary">
            Annulla
          </a>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection
