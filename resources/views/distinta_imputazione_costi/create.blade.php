@extends('layouts.app')

@php
  $user = Auth::user();
  $anno = session('anno_riferimento');
@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Aggiungi Costo Diretto o Bilancio Consuntivo</h1>

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
      <form action="{{ route('distinta.imputazione.store') }}" method="POST">
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
          <select name="idConvenzione" id="idConvenzione" class="form-select" required>
            <option value="">-- Seleziona --</option>
            @foreach($convenzioni as $conv)
              <option value="{{ $conv->idConvenzione }}">{{ $conv->Convenzione }}</option>
            @endforeach
          </select>
        </div>

        {{-- Voce --}}
        <div class="mb-3">
          <label for="voce" class="form-label">Voce</label>
          <select name="voce" id="voce" class="form-select" required>
            <option value="">-- Seleziona --</option>
            @foreach($vociDisponibili as $voce)
              <option value="{{ $voce }}">{{ $voce }}</option>
            @endforeach
          </select>
        </div>

        {{-- Importi --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="costo" class="form-label">Importo Costo Diretto (€)</label>
            <input type="number" name="costo" id="costo" step="0.01" class="form-control">
          </div>

          <div class="col-md-6 mb-3">
            <label for="bilancio_consuntivo" class="form-label">
              Importo da Bilancio Consuntivo (€)
            </label>
            <input type="number" step="0.01" class="form-control" name="bilancio_consuntivo" id="bilancio_consuntivo">
          </div>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3">Salva</button>
          <a href="{{ route('distinta.imputazione.index') }}" class="btn btn-secondary">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- JS: se esiste valore calcolato, lo autopopola ma resta editabile --}}
<script>
  const bilanci = @json($bilancioPerVoce);

  document.getElementById('voce').addEventListener('change', function () {
    const voce = this.value;
    const importo = bilanci[voce] ?? 0;
    document.getElementById('bilancio_consuntivo').value = importo;
  });
</script>
@endsection
