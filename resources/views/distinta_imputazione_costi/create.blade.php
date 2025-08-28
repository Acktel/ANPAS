{{-- resources/views/distinta_imputazione_costi/create.blade.php --}}
@extends('layouts.app')

@php
  $anno = session('anno_riferimento', now()->year);
  // priorità: old -> querystring -> session
  $preselectConvenzione = old('idConvenzione')
      ?? request('idConvenzione')
      ?? session('convenzione_selezionata');
@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Aggiungi Costo Diretto / Bilancio Consuntivo</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('distinta.imputazione.store') }}" method="POST" novalidate>
        @csrf

        <input type="hidden" name="idSezione" value="{{ $sezione }}">

        {{-- Associazione e Anno --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Associazione</label>
            <input type="text" class="form-control" value="{{ $associazione }}" disabled>
            <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anno</label>
            <input type="text" class="form-control" value="{{ $anno }}" disabled>
            <input type="hidden" name="idAnno" value="{{ $anno }}">
          </div>
        </div>

        {{-- Convenzione --}}
        <div class="mb-3">
          <label for="idConvenzione" class="form-label">Convenzione</label>
          <select
            name="idConvenzione"
            id="idConvenzione"
            class="form-select @error('idConvenzione') is-invalid @enderror"
            required
          >
            <option value="">-- Seleziona --</option>
            @foreach($convenzioni as $conv)
              <option
                value="{{ $conv->idConvenzione }}"
                {{ (string)$preselectConvenzione === (string)$conv->idConvenzione ? 'selected' : '' }}
              >
                {{ $conv->Convenzione }}
              </option>
            @endforeach
          </select>
          @error('idConvenzione')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Voce (da riepilogo_voci_config) --}}
        <div class="mb-3">
          <label for="idVoceConfig" class="form-label">Voce</label>
          <select
            name="idVoceConfig"
            id="idVoceConfig"
            class="form-select @error('idVoceConfig') is-invalid @enderror"
            required
          >
            <option value="">-- Seleziona --</option>
            @foreach($vociDisponibili as $voce)
              <option
                value="{{ $voce->id }}"
                {{ (string)old('idVoceConfig') === (string)$voce->id ? 'selected' : '' }}
              >
                {{ $voce->descrizione }}
              </option>
            @endforeach
          </select>
          @error('idVoceConfig')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Importi --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="costo" class="form-label">Importo Costo Diretto (€)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control @error('costo') is-invalid @enderror"
              name="costo"
              id="costo"
              value="{{ old('costo') }}"
              placeholder="0,00"
            >
            @error('costo')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label for="bilancio_consuntivo" class="form-label">Importo Bilancio Consuntivo (€)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control @error('bilancio_consuntivo') is-invalid @enderror"
              name="bilancio_consuntivo"
              id="bilancio_consuntivo"
              value="{{ old('bilancio_consuntivo') }}"
              placeholder="0,00"
            >
            @error('bilancio_consuntivo')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">
              Selezionando una <strong>Voce</strong>, questo campo si auto-compila se è disponibile un totale calcolato.
            </small>
          </div>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3">
            <i class="fas fa-check me-1"></i> Salva
          </button>
          <a href="{{ route('distinta.imputazione.index') }}" class="btn btn-secondary">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- JS: auto-popola bilancio (chiave = idVoceConfig) --}}
<script>
  const bilanci = @json($bilancioPerVoce ?? []);
  document.getElementById('idVoceConfig')?.addEventListener('change', function () {
    const id = this.value;
    if (id && bilanci[id] !== undefined) {
      document.getElementById('bilancio_consuntivo').value = bilanci[id];
    }
  });
</script>
@endsection
