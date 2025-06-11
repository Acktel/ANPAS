@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Nuovo Automezzo</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('automezzi.store') }}" method="POST">
    @csrf

{{-- Primo gruppo: Associazione e Anno --}}
    <div class="row">
      {{-- Se sto impersonificando, fisso l’associazione corrente --}}
      @if(session()->has('impersonate'))
        @php       
          // Trovo l’associazione dell’utente impersonato
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
                 value="{{ $assocCorr->IdAssociazione }}">
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
                {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}>
                {{ $asso->Associazione }}
              </option>
            @endforeach
          </select>
        </div>
      @endif

      {{-- Anno --}}
      <div class="col-md-4">
        <label for="idAnno" class="form-label">Anno</label>
        <select name="idAnno"
                id="idAnno"
                class="form-select"
                required>
          <option value="">-- Seleziona Anno --</option>
          @foreach($anni as $y)
            <option value="{{ $y->idAnno }}"
              {{ old('idAnno') == $y->idAnno ? 'selected' : '' }}>
              {{ $y->anno }}
            </option>
          @endforeach
        </select>
      </div>
    </div>


    
    <div class="row">
      {{-- Automezzo --}}
    <div class="col-md-3">
      <label for="Automezzo" class="form-label">Nome Automezzo</label>
      <input type="text" name="Automezzo" id="Automezzo"
             class="form-control" maxlength="255"
             value="{{ old('Automezzo') }}" required>
    </div>

    {{-- Targa --}}
    <div class="col-md-3">
      <label for="Targa" class="form-label">Targa</label>
      <input type="text" name="Targa" id="Targa"
             class="form-control" maxlength="50"
             value="{{ old('Targa') }}" required>
    </div>

    {{-- Codice Identificativo --}}
    <div class="col-md-3">
      <label for="CodiceIdentificativo" class="form-label">Codice Identificativo</label>
      <input type="text" name="CodiceIdentificativo" id="CodiceIdentificativo"
             class="form-control" maxlength="100"
             value="{{ old('CodiceIdentificativo') }}" required>
    </div>
    </div>
    <br>
    <div class="row">
{{-- Anno Prima Immatricolazione --}}
<div class="col-md-3">
  <label for="AnnoPrimaImmatricolazione" class="form-label">Anno Prima Immatricolazione</label>
  <input type="number" name="AnnoPrimaImmatricolazione" id="AnnoPrimaImmatricolazione"
         class="form-control" min="1900" max="{{ date('Y') }}"
         value="{{ old('AnnoPrimaImmatricolazione') }}" required>
</div>

{{-- Anno Acquisto (solo se ≠ prima immatricolazione) --}}
<div class="col-md-3">
  <label for="AnnoAcquisto" class="form-label">
    Anno Acquisto
    <small class="text-muted d-block">(in caso di acquisto mezzi non di prima immatricolazione)</small>
  </label>
  <input type="number" name="AnnoAcquisto" id="AnnoAcquisto"
         class="form-control" min="1900" max="{{ date('Y') }}"
         value="{{ old('AnnoAcquisto') }}">
</div>

    {{-- Modello --}}
    <div class="col-md-3">
      <label for="Modello" class="form-label">Modello</label>
      <input type="text" name="Modello" id="Modello"
             class="form-control" maxlength="255"
             value="{{ old('Modello') }}" required>
    </div>

    {{-- Tipo Veicolo --}}
    <div class="col-md-3">
      <label for="TipoVeicolo" class="form-label">Tipo Veicolo</label>
      <input type="text" name="TipoVeicolo" id="TipoVeicolo"
             class="form-control" maxlength="100"
             value="{{ old('TipoVeicolo') }}" required>
    </div>
    </div>
    <br>
    <div class="row">
{{-- Km Riferimento --}}
    <div class="col-md-3">
      <label for="KmRiferimento" class="form-label">Km di Riferimento</label>
      <input type="number" name="KmRiferimento" id="KmRiferimento"
             class="form-control" step="0.01" min="0"
             value="{{ old('KmRiferimento') }}" required>
    </div>

    {{-- Km Totali --}}
    <div class="col-md-3">
      <label for="KmTotali" class="form-label">Km Totali</label>
      <input type="number" name="KmTotali" id="KmTotali"
             class="form-control" step="0.01" min="0"
             value="{{ old('KmTotali') }}" required>
    </div>

    {{-- Tipo Carburante --}}
    <div class="col-md-3">
      <label for="TipoCarburante" class="form-label">Tipo Carburante</label>
      <input type="text" name="TipoCarburante" id="TipoCarburante"
             class="form-control" maxlength="50"
             value="{{ old('TipoCarburante') }}" required>
    </div>

    </div>
    <br>
    <div class="row">
{{-- Data Ultima Autorizzazione Sanitaria --}}
    <div class="col-md-4">
      <label for="DataUltimaAutorizzazioneSanitaria" class="form-label">
        Data Ultima Autorizzazione Sanitaria
      </label>
      <input type="date" name="DataUltimaAutorizzazioneSanitaria"
             id="DataUltimaAutorizzazioneSanitaria"
             class="form-control"
             value="{{ old('DataUltimaAutorizzazioneSanitaria') }}">
    </div>

    {{-- Data Ultimo Collaudo --}}
    <div class="col-md-4">
      <label for="DataUltimoCollaudo" class="form-label">
        DATA ULTIMA REVISIONE
      </label>
      <input type="date" name="DataUltimoCollaudo" id="DataUltimoCollaudo"
             class="form-control"
             value="{{ old('DataUltimoCollaudo') }}">
    </div>
    </div>
    

    
    

    <hr>
    <button type="submit" class="btn btn-primary">Salva Automezzo</button>
  </form>
</div>
@endsection
