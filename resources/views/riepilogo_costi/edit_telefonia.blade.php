@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-2">Modifica UTENZE TELEFONICHE</h1>
  <p class="text-muted mb-4">
    Anno <strong>{{ $anno }}</strong> — Associazione <strong>{{ $nomeAssociazione }}</strong>
    — Convenzione <strong>{{ $nomeConvenzione }}</strong>
  </p>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('riepilogo.costi.update.telefonia') }}" method="POST" class="row g-3">
        @csrf
        <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
        <input type="hidden" name="idConvenzione"  value="{{ $idConvenzione }}">
        <input type="hidden" name="idFissa"        value="{{ $idFissa }}">
        <input type="hidden" name="idMobile"       value="{{ $idMobile }}">

        <div class="col-md-6">
          <label class="form-label">Preventivo — Telefonia fissa</label>
          <input type="number" step="0.01" name="preventivo_fissa" class="form-control"
                 value="{{ old('preventivo_fissa', $prevFissa) }}">
          <small class="text-muted">Consuntivo: {{ number_format($consFissa, 2, ',', '.') }}</small>
        </div>

        <div class="col-md-6">
          <label class="form-label">Preventivo — Telefonia mobile</label>
          <input type="number" step="0.01" name="preventivo_mobile" class="form-control"
                 value="{{ old('preventivo_mobile', $prevMobile) }}">
          <small class="text-muted">Consuntivo: {{ number_format($consMobile, 2, ',', '.') }}</small>
        </div>

        <div class="col-12 mt-2">
          <button class="btn btn-anpas-green">
            <i class="fas fa-check me-1"></i> Salva
          </button>
          <a href="{{ route('riepilogo.costi', ['idAssociazione'=>$idAssociazione,'idConvenzione'=>$idConvenzione]) }}"
             class="btn btn-secondary ms-2">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
