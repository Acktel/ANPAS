@extends('layouts.app')
@php
use App\Models\Associazione;

$user = Auth::user();
$isImpersonating = session()->has('impersonate');

$selectedAssociazione = session('selectedAssociazione') ?? $automezzo->idAssociazione;
$assocCorr = Associazione::getById($selectedAssociazione);
$annoCorr = session('annoCorrente') ?? ($automezzo->idAnno ?? now()->year);
@endphp


@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Automezzo #{{ $automezzo->idAutomezzo }}</h1>
  <p class="text-muted mb-4">
    Associazione #{{ $assocCorr->Associazione }} — Anno {{ $automezzo->idAnno }}
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
      <form action="{{ route('automezzi.update', $automezzo->idAutomezzo) }}" method="POST">
        @csrf
        @method('PUT')


        {{-- RIGA 1: Associazione | Anno --}}
        <div class="row mb-3">
          <input type="hidden" name="idAssociazione" value="{{ $selectedAssociazione }}">
          <input type="hidden" name="idAnno" value="{{ $annoCorr }}">
        </div>

        {{-- RIGA 2: Targa | (spazio) --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="Targa" class="form-label">Targa</label>
            <input type="text" name="Targa" id="Targa" class="form-control" style="text-transform: uppercase;"
              value="{{ old('Targa', $automezzo->Targa) }}" required>
          </div>
          <div class="col-md-6">
            <label for="CodiceIdentificativo" class="form-label">Codice Identificativo</label>
            <input type="text" name="CodiceIdentificativo" id="CodiceIdentificativo" class="form-control" style="text-transform: uppercase;"
              value="{{ old('CodiceIdentificativo', $automezzo->CodiceIdentificativo) }}" required>
          </div>
        </div>

        {{-- RIGA 3: Codice Identificativo | Anno Prima Immatricolazione --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="AnnoPrimaImmatricolazione" class="form-label">Anno Prima Immatricolazione</label>
            <input type="number" name="AnnoPrimaImmatricolazione" id="AnnoPrimaImmatricolazione"
              class="form-control" min="1900" max="{{ date('Y') }}"
              value="{{ old('AnnoPrimaImmatricolazione', $automezzo->AnnoPrimaImmatricolazione) }}">
          </div>
          <div class="col-md-6">
            <label for="AnnoAcquisto" class="form-label">Anno Acquisto <small class="text-muted">(opzionale)</small></label>
            <input type="number" name="AnnoAcquisto" id="AnnoAcquisto" class="form-control"
              min="1900" max="{{ date('Y') }}"
              value="{{ old('AnnoAcquisto', $automezzo->AnnoAcquisto) }}">
          </div>
        </div>

        {{-- RIGA 4: Anno Acquisto | Modello --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="Modello" class="form-label">Modello</label>
            <input type="text" name="Modello" id="Modello" class="form-control" style="text-transform: uppercase;"
              value="{{ old('Modello', $automezzo->Modello) }}">
          </div>
          <div class="col-md-6">
            <label for="idTipoVeicolo" class="form-label">Tipo Veicolo</label>
            <select name="idTipoVeicolo" id="idTipoVeicolo" class="form-select">
              <option value="">-- Seleziona Tipo Veicolo --</option>
              @foreach($vehicleTypes as $tipo)
              <option value="{{ $tipo->id }}" {{ old('idTipoVeicolo', $automezzo->idTipoVeicolo) == $tipo->id ? 'selected' : '' }}>
                {{ $tipo->nome }}
              </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- RIGA 5: Tipo Veicolo | Km di Riferimento --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="KmRiferimento" class="form-label">Km di Riferimento</label>
            <input type="number"
              name="KmRiferimento"
              id="KmRiferimento"
              class="form-control js-int-only"
              min="0"
              step="1"
              inputmode="numeric"
              pattern="\d*"
              value="{{ old('KmRiferimento', $automezzo->KmRiferimento) }}"
              inputmode="numeric">
          </div>
          <div class="col-md-6">
            <label for="KmTotali" class="form-label">Km Totali</label>
            <input type="number" name="KmTotali" id="KmTotali" class="form-control js-int-only"
              min="0"
              step="1"
              inputmode="numeric"
              pattern="\d*"
              value="{{ old('KmTotali', $automezzo->KmTotali) }}" readonly>
          </div>
        </div>

        {{-- RIGA 6: Km Totali | Tipo Carburante --}}
        <div class="row mb-3">

          <div class="col-md-6">
            <label for="idTipoCarburante" class="form-label">Tipo Carburante</label>
            <select name="idTipoCarburante" id="idTipoCarburante" class="form-select">
              <option value="">-- Seleziona Tipo Carburante --</option>
              @foreach($fuelTypes as $carb)
              <option value="{{ $carb->id }}" {{ old('idTipoCarburante', $automezzo->idTipoCarburante) == $carb->id ? 'selected' : '' }}>
                {{ $carb->nome }}
              </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- RIGA 7: Date Sanitarie | Revisione --}}
        <div class="row mb-4">
          <div class="col-md-6">
            <label for="DataUltimaAutorizzazioneSanitaria" class="form-label">Data Ultima Aut. Sanitaria</label>
            <input type="date" name="DataUltimaAutorizzazioneSanitaria" id="DataUltimaAutorizzazioneSanitaria"
              class="form-control"
              value="{{ old('DataUltimaAutorizzazioneSanitaria', $automezzo->DataUltimaAutorizzazioneSanitaria) }}">
          </div>
          <div class="col-md-6">
            <label for="DataUltimoCollaudo" class="form-label">Data Ultima Revisione</label>
            <input type="date" name="DataUltimoCollaudo" id="DataUltimoCollaudo" class="form-control"
              value="{{ old('DataUltimoCollaudo', $automezzo->DataUltimoCollaudo) }}">
          </div>
        </div>

        {{-- RIGA 8: Incluso in Riparto | (spazio) --}}
        <div class="row mb-4">
          <div class="col-md-6">
            <label for="incluso_riparto" class="form-label">Incluso nel riparto materiale sanitario?</label>
            <select name="incluso_riparto" id="incluso_riparto" class="form-select">
              <option value="1" {{ old('incluso_riparto', $automezzo->incluso_riparto) == 1 ? 'selected' : '' }}>Sì</option>
              <option value="0" {{ old('incluso_riparto', $automezzo->incluso_riparto) == 0 ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-6"><!-- spacer per allineamento --></div>
        </div>

        {{-- RIGA 9: Note | Informazioni aggiuntive --}}
        <div class="row mb-4">
          <div class="col-md-6">
            <label for="note" class="form-label">Note</label>
            <textarea name="note" id="note" class="form-control" rows="3">{{ old('note', $automezzo->note) }}</textarea>
          </div>
          <div class="col-md-6">
            <label for="informazioniAggiuntive" class="form-label">Informazioni aggiuntive</label>
            <textarea name="informazioniAggiuntive" id="informazioniAggiuntive" class="form-control" rows="3">{{ old('informazioniAggiuntive', $automezzo->informazioniAggiuntive) }}</textarea>
          </div>
        </div>

        {{-- PULSANTI --}}
        <div class="text-center myborder-button">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Aggiorna
          </button>
          <a href="{{ route('automezzi.index', ['idAssociazione' => $selectedAssociazione, 'idAnno' => $annoCorr]) }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Annulla
          </a>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection