@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Modifica Automezzo #{{ $automezzo->idAutomezzo }}</h1>

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('automezzi.update', $automezzo->idAutomezzo) }}" method="POST">
    @csrf
    @method('PUT')
    {{-- Associazione --}}
    {{-- Primo gruppo: Associazione e Anno --}}
    <div class="row">
      {{-- Se sto impersonificando, mostro un campo readonly --}}
      @if(session()->has('impersonate'))
      @php
      $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione);
      @endphp
      <div class="col-md-4">
        <label class="form-label">Associazione</label>
        <input type="text"
          class="form-control"
          value="{{ $assocCorr->Associazione }}"
          readonly>
        <input type="hidden"
          name="idAssociazione"
          value="{{ Auth::user()->IdAssociazione }}">
      </div>
      @else
      <div class="col-md-4">
        <label for="idAssociazione" class="form-label">Associazione</label>
        <select name="idAssociazione"
          id="idAssociazione"
          class="form-select"
          required>
          <option value="">-- Seleziona Associazione --</option>
          @foreach($associazioni as $asso)
          <option value="{{ $asso->idAssociazione }}"
            {{ old('idAssociazione', $automezzo->idAssociazione) == $asso->idAssociazione ? 'selected' : '' }}>
            {{ $asso->Associazione }}
          </option>
          @endforeach
        </select>
      </div>
      @endif

      {{-- Seleziona Anno --}}
      <div class="col-md-4">
        <label for="idAnno" class="form-label">Anno</label>
        <select name="idAnno"
          id="idAnno"
          class="form-select"
          required>
          <option value="">-- Seleziona Anno --</option>
          @foreach($anni as $annoRecord)
          <option value="{{ $annoRecord->idAnno }}"
            {{ old('idAnno', $automezzo->idAnno) == $annoRecord->idAnno ? 'selected' : '' }}>
            {{ $annoRecord->anno }}
          </option>
          @endforeach
        </select>
      </div>
    </div>

    <br>

    <div class="row">
      {{-- Automezzo --}}
      <div class="col-md-3">
        <label for="Automezzo" class="form-label">Nome Automezzo</label>
        <input type="text" name="Automezzo" id="Automezzo"
          class="form-control" maxlength="255"
          value="{{ old('Automezzo', $automezzo->Automezzo) }}" required>
      </div>

      {{-- Targa --}}
      <div class="col-md-3">
        <label for="Targa" class="form-label">Targa</label>
        <input type="text" name="Targa" id="Targa"
          class="form-control" maxlength="50"
          value="{{ old('Targa', $automezzo->Targa) }}" required>
      </div>

      {{-- Codice Identificativo --}}
      <div class="col-md-3">
        <label for="CodiceIdentificativo" class="form-label">Codice Identificativo</label>
        <input type="text" name="CodiceIdentificativo" id="CodiceIdentificativo"
          class="form-control" maxlength="100"
          value="{{ old('CodiceIdentificativo', $automezzo->CodiceIdentificativo) }}" required>
      </div>
    </div>
    <br>

    <div class="row">
      {{-- Anno Prima Immatricolazione --}}
      <div class="col-md-3">
        <label for="AnnoPrimaImmatricolazione" class="form-label">Anno Prima Immatricolazione</label>
        <input type="number" name="AnnoPrimaImmatricolazione" id="AnnoPrimaImmatricolazione"
          class="form-control" min="1900" max="{{ date('Y') }}"
          value="{{ old('AnnoPrimaImmatricolazione', $automezzo->AnnoPrimaImmatricolazione) }}" required>
      </div>

      {{-- Modello --}}
      <div class="col-md-3">
        <label for="Modello" class="form-label">Modello</label>
        <input type="text" name="Modello" id="Modello"
          class="form-control" maxlength="255"
          value="{{ old('Modello', $automezzo->Modello) }}" required>
      </div>

      {{-- Tipo Veicolo --}}
      <div class="col-md-3">
        <label for="TipoVeicolo" class="form-label">Tipo Veicolo</label>
        <input type="text" name="TipoVeicolo" id="TipoVeicolo"
          class="form-control" maxlength="100"
          value="{{ old('TipoVeicolo', $automezzo->TipoVeicolo) }}" required>
      </div>

    </div>
    <br>
    <div class="row">
      {{-- Km Riferimento --}}
      <div class="col-md-3">
        <label for="KmRiferimento" class="form-label">KM PERCORSI IN ESERCIZIO DI RIFERIMENTO</label>
        <input type="number" name="KmRiferimento" id="KmRiferimento"
          class="form-control" step="0.01" min="0"
          value="{{ old('KmRiferimento', $automezzo->KmRiferimento) }}" required>
      </div>

      {{-- Km Totali --}}
      <div class="col-md-3">
        <label for="KmTotali" class="form-label">TOTALE KM. PERCORSI </label>
        <input type="number" name="KmTotali" id="KmTotali"
          class="form-control" step="0.01" min="0"
          value="{{ old('KmTotali', $automezzo->KmTotali) }}" required>
      </div>

      {{-- Tipo Carburante --}}
      <div class="col-md-3">
        <label for="TipoCarburante" class="form-label">TIPO DI CARBURANTE UTILIZZATO</label>
        <input type="text" name="TipoCarburante" id="TipoCarburante"
          class="form-control" maxlength="50"
          value="{{ old('TipoCarburante', $automezzo->TipoCarburante) }}" required>
      </div>
    </div>

    <br>
    <div class="row">
      <div class="col-md-4">
        <label for="DataUltimaAutorizzazioneSanitaria" class="form-label">
          Data Ultima Autorizzazione Sanitaria
        </label>
        <input type="date" name="DataUltimaAutorizzazioneSanitaria"
          id="DataUltimaAutorizzazioneSanitaria"
          class="form-control"
          value="{{ old('DataUltimaAutorizzazioneSanitaria', $automezzo->DataUltimaAutorizzazioneSanitaria) }}">
      </div>

      {{-- Data Ultimo Collaudo --}}
      <div class="col-md-4">
        <label for="DataUltimoCollaudo" class="form-label">DATA ULTIMA REVISIONE</label>
        <input type="date" name="DataUltimoCollaudo" id="DataUltimoCollaudo"
          class="form-control"
          value="{{ old('DataUltimoCollaudo', $automezzo->DataUltimoCollaudo) }}">
      </div>
    </div>
    {{-- Data Ultima Autorizzazione Sanitaria --}}


    <hr>
    <button type="submit" class="btn btn-primary">Aggiorna Automezzo</button>
  </form>
</div>
@endsection