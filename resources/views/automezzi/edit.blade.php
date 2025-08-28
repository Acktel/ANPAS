@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Automezzo #{{ $automezzo->idAutomezzo }}</h1>

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

        @php
          use App\Models\Associazione;

          $user = Auth::user();
          $isImpersonating = session()->has('impersonate');
          $selectedAssociazione = old('idAssociazione', $automezzo->idAssociazione);
          $assocCorr = Associazione::getById($selectedAssociazione);

          $annoCorr = old('idAnno', $automezzo->idAnno ?? session('anno_riferimento', now()->year));
        @endphp

        {{-- RIGA 1: Associazione | Anno --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Associazione</label>
            <input type="text" class="form-control" value="{{ $assocCorr->Associazione }}" readonly>
            <input type="hidden" name="idAssociazione" value="{{ $assocCorr->IdAssociazione }}">
          </div>

          <div class="col-md-6">
            @if($isImpersonating || $user->role_id == 4)
              <label class="form-label">Anno</label>
              <input type="text" class="form-control" value="{{ $annoCorr }}" readonly>
              <input type="hidden" name="idAnno" value="{{ $annoCorr }}">
            @else
              <label for="idAnno" class="form-label">Anno</label>
              <select name="idAnno" id="idAnno" class="form-select" required>
                <option value="">-- Seleziona Anno --</option>
                @foreach($anni as $y)
                  <option value="{{ $y->idAnno }}" {{ $annoCorr == $y->idAnno ? 'selected' : '' }}>
                    {{ $y->anno }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>
        </div>

        {{-- RIGA 2: Nome Automezzo | Targa --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="Automezzo" class="form-label">Nome Automezzo</label>
            <input type="text" name="Automezzo" id="Automezzo" class="form-control" style="text-transform: uppercase;"
                   value="{{ old('Automezzo', $automezzo->Automezzo) }}" required>
          </div>
          <div class="col-md-6">
            <label for="Targa" class="form-label">Targa</label>
            <input type="text" name="Targa" id="Targa" class="form-control" style="text-transform: uppercase;"
                   value="{{ old('Targa', $automezzo->Targa) }}" required>
          </div>
        </div>

        {{-- RIGA 3: Codice Identificativo | Anno Prima Immatricolazione --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="CodiceIdentificativo" class="form-label">Codice Identificativo</label>
            <input type="text" name="CodiceIdentificativo" id="CodiceIdentificativo" class="form-control" style="text-transform: uppercase;"
                   value="{{ old('CodiceIdentificativo', $automezzo->CodiceIdentificativo) }}" required>
          </div>
          <div class="col-md-6">
            <label for="AnnoPrimaImmatricolazione" class="form-label">Anno Prima Immatricolazione</label>
            <input type="number" name="AnnoPrimaImmatricolazione" id="AnnoPrimaImmatricolazione"
                   class="form-control" min="1900" max="{{ date('Y') }}"
                   value="{{ old('AnnoPrimaImmatricolazione', $automezzo->AnnoPrimaImmatricolazione) }}" required>
          </div>
        </div>

        {{-- RIGA 4: Anno Acquisto | Modello --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="AnnoAcquisto" class="form-label">Anno Acquisto <small class="text-muted">(opzionale)</small></label>
            <input type="number" name="AnnoAcquisto" id="AnnoAcquisto" class="form-control"
                   min="1900" max="{{ date('Y') }}"
                   value="{{ old('AnnoAcquisto', $automezzo->AnnoAcquisto) }}">
          </div>
          <div class="col-md-6">
            <label for="Modello" class="form-label">Modello</label>
            <input type="text" name="Modello" id="Modello" class="form-control" style="text-transform: uppercase;"
                   value="{{ old('Modello', $automezzo->Modello) }}" required>
          </div>
        </div>

        {{-- RIGA 5: Tipo Veicolo | Km di Riferimento --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="idTipoVeicolo" class="form-label">Tipo Veicolo</label>
            <select name="idTipoVeicolo" id="idTipoVeicolo" class="form-select" required>
              <option value="">-- Seleziona Tipo Veicolo --</option>
              @foreach($vehicleTypes as $tipo)
                <option value="{{ $tipo->id }}" {{ old('idTipoVeicolo', $automezzo->idTipoVeicolo) == $tipo->id ? 'selected' : '' }}>
                  {{ $tipo->nome }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label for="KmRiferimento" class="form-label">Km di Riferimento</label>
            <input   type="number"
                     name="KmRiferimento"
                     id="KmRiferimento"
                     class="form-control"
                     step=1.00"
                     value="{{ old('KmRiferimento', $automezzo->KmRiferimento) }}"
                     required
                     inputmode="decimal">
          </div>
        </div>

        {{-- RIGA 6: Km Totali | Tipo Carburante --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="KmTotali" class="form-label">Km Totali</label>
            <input type="number" name="KmTotali" id="KmTotali" class="form-control"
                   min="0" step=1.00"
                   value="{{ old('KmTotali', $automezzo->KmTotali) }}" required>
          </div>
          <div class="col-md-6">
            <label for="idTipoCarburante" class="form-label">Tipo Carburante</label>
            <select name="idTipoCarburante" id="idTipoCarburante" class="form-select" required>
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

        {{-- RIGA 8: Incluso in Riparto --}}
        <div class="row mb-4">
          <div class="col-md-6">
            <label for="incluso_riparto" class="form-label">Incluso nel riparto materiale sanitario?</label>
            <select name="incluso_riparto" id="incluso_riparto" class="form-select">
              <option value="1" {{ old('incluso_riparto', $automezzo->incluso_riparto) == 1 ? 'selected' : '' }}>Sì</option>
              <option value="0" {{ old('incluso_riparto', $automezzo->incluso_riparto) == 0 ? 'selected' : '' }}>No</option>
            </select>
          </div>
        </div>

        {{-- PULSANTI --}}
        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Aggiorna
          </button>
          <a href="{{ route('automezzi.index', [
              'idAssociazione' => $selectedAssociazione,
              'idAnno' => $annoCorr
          ]) }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Annulla
          </a>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection