@extends('layouts.app')

@section('content')
<div class="container-fluid">
  {{-- Titolo --}}
  <h1 class="text-anpas-green fw-bold mb-4">Nuovo Automezzo</h1>

  {{-- Errori di validazione --}}
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
      <form action="{{ route('automezzi.store') }}" method="POST">
        @csrf

        <div class="row mb-3">
          {{-- Associazione --}}
          @if(session()->has('impersonate'))
            @php
              $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione);
            @endphp
            <div class="col-md-4">
              <label class="form-label">Associazione</label>
              <input 
                type="text" 
                class="form-control" 
                value="{{ $assocCorr->Associazione }}" 
                readonly
              >
              <input 
                type="hidden" 
                name="idAssociazione" 
                value="{{ $assocCorr->IdAssociazione }}"
              >
            </div>
          @else
            <div class="col-md-4">
              <label for="idAssociazione" class="form-label">Associazione</label>
              <select 
                name="idAssociazione" 
                id="idAssociazione" 
                class="form-select" 
                required
              >
                <option value="">-- Seleziona Associazione --</option>
                @foreach($associazioni as $asso)
                  <option 
                    value="{{ $asso->idAssociazione }}"
                    {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}
                  >
                    {{ $asso->Associazione }}
                  </option>
                @endforeach
              </select>
            </div>
          @endif

          {{-- Anno --}}
          <div class="col-md-4">
            <label for="idAnno" class="form-label">Anno</label>
            <select 
              name="idAnno" 
              id="idAnno" 
              class="form-select" 
              required
            >
              <option value="">-- Seleziona Anno --</option>
              @foreach($anni as $y)
                <option 
                  value="{{ $y->idAnno }}"
                  {{ old('idAnno') == $y->idAnno ? 'selected' : '' }}
                >
                  {{ $y->anno }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Seconda riga --}}
        <div class="row mb-3">
          <div class="col-md-3">
            <label for="Automezzo" class="form-label">Nome Automezzo</label>
            <input 
              type="text" 
              name="Automezzo" 
              id="Automezzo" 
              class="form-control" 
              value="{{ old('Automezzo') }}" 
              required
            >
          </div>
          <div class="col-md-3">
            <label for="Targa" class="form-label">Targa</label>
            <input 
              type="text" 
              name="Targa" 
              id="Targa" 
              class="form-control" 
              value="{{ old('Targa') }}" 
              required
            >
          </div>
          <div class="col-md-3">
            <label for="CodiceIdentificativo" class="form-label">Codice Identificativo</label>
            <input 
              type="text" 
              name="CodiceIdentificativo" 
              id="CodiceIdentificativo" 
              class="form-control" 
              value="{{ old('CodiceIdentificativo') }}" 
              required
            >
          </div>
        </div>

        {{-- Terza riga --}}
        <div class="row mb-3">
          <div class="col-md-3">
            <label for="AnnoPrimaImmatricolazione" class="form-label">Anno Prima Immatricolazione</label>
            <input 
              type="number" 
              name="AnnoPrimaImmatricolazione" 
              id="AnnoPrimaImmatricolazione" 
              class="form-control" 
              min="1900" 
              max="{{ date('Y') }}" 
              value="{{ old('AnnoPrimaImmatricolazione') }}" 
              required
            >
          </div>
          <div class="col-md-3">
            <label for="AnnoAcquisto" class="form-label">
              Anno Acquisto
              <small class="text-muted d-block">(opzionale)</small>
            </label>
            <input 
              type="number" 
              name="AnnoAcquisto" 
              id="AnnoAcquisto" 
              class="form-control" 
              min="1900" 
              max="{{ date('Y') }}" 
              value="{{ old('AnnoAcquisto') }}"
            >
          </div>
          <div class="col-md-3">
            <label for="Modello" class="form-label">Modello</label>
            <input 
              type="text" 
              name="Modello" 
              id="Modello" 
              class="form-control" 
              value="{{ old('Modello') }}" 
              required
            >
          </div>
          <div class="col-md-3">
            <label for="TipoVeicolo" class="form-label">Tipo Veicolo</label>
            <input 
              type="text" 
              name="TipoVeicolo" 
              id="TipoVeicolo" 
              class="form-control" 
              value="{{ old('TipoVeicolo') }}" 
              required
            >
          </div>
        </div>

        {{-- Quarta riga --}}
        <div class="row mb-3">
          <div class="col-md-3">
            <label for="KmRiferimento" class="form-label">Km di Riferimento</label>
            <input 
              type="number" 
              name="KmRiferimento" 
              id="KmRiferimento" 
              class="form-control" 
              min="0" 
              step="0.01" 
              value="{{ old('KmRiferimento') }}" 
              required
            >
          </div>
          <div class="col-md-3">
            <label for="KmTotali" class="form-label">Km Totali</label>
            <input 
              type="number" 
              name="KmTotali" 
              id="KmTotali" 
              class="form-control" 
              min="0" 
              step="0.01" 
              value="{{ old('KmTotali') }}" 
              required
            >
          </div>
          <div class="col-md-3">
            <label for="TipoCarburante" class="form-label">Tipo Carburante</label>
            <input 
              type="text" 
              name="TipoCarburante" 
              id="TipoCarburante" 
              class="form-control" 
              value="{{ old('TipoCarburante') }}" 
              required
            >
          </div>
        </div>

        {{-- Quinta riga --}}
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="DataUltimaAutorizzazioneSanitaria" class="form-label">Data Ultima Aut. Sanitaria</label>
            <input 
              type="date" 
              name="DataUltimaAutorizzazioneSanitaria" 
              id="DataUltimaAutorizzazioneSanitaria" 
              class="form-control" 
              value="{{ old('DataUltimaAutorizzazioneSanitaria') }}"
            >
          </div>
          <div class="col-md-4">
            <label for="DataUltimoCollaudo" class="form-label">Data Ultima Revisione</label>
            <input 
              type="date" 
              name="DataUltimoCollaudo" 
              id="DataUltimoCollaudo" 
              class="form-control" 
              value="{{ old('DataUltimoCollaudo') }}"
            >
          </div>
        </div>

        <hr>

        <button type="submit" class="btn btn-anpas-red">
          Salva Automezzo
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
