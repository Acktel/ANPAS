{{-- resources/views/automezzi/show.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Dettaglio Automezzo #{{ $automezzo->idAutomezzo }}
  </h1>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <dl class="row gx-0 gy-2 mb-0">
        <dt class="col-md-4 text-anpas-green fw-semibold">Associazione</dt>
        <dd class="col-md-8">{{ $automezzo->idAssociazione }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Anno</dt>
        <dd class="col-md-8">{{ $automezzo->idAnno }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Targa</dt>
        <dd class="col-md-8">{{ $automezzo->Targa }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Codice ID</dt>
        <dd class="col-md-8">{{ $automezzo->CodiceIdentificativo }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Prima Immatricolazione</dt>
        <dd class="col-md-8">{{ $automezzo->AnnoPrimaImmatricolazione }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Modello</dt>
        <dd class="col-md-8">{{ $automezzo->Modello }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Tipo Veicolo</dt>
        <dd class="col-md-8">{{ $automezzo->TipoVeicolo }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Km Rif.</dt>
        <dd class="col-md-8">{{ $automezzo->KmRiferimento }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Km Totali</dt>
        <dd class="col-md-8">{{ $automezzo->KmTotali }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Carburante</dt>
        <dd class="col-md-8">{{ $automezzo->TipoCarburante }}</dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Ult. Aut. Sanitaria</dt>
        <dd class="col-md-8">
          {{ optional(\Carbon\Carbon::parse($automezzo->DataUltimaAutorizzazioneSanitaria))->format('d/m/Y') }}
        </dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Ult. Revisione</dt>
        <dd class="col-md-8">
          {{ optional(\Carbon\Carbon::parse($automezzo->DataUltimoCollaudo))->format('d/m/Y') }}
        </dd>

        <dt class="col-md-4 text-anpas-green fw-semibold">Creato il</dt>
        <dd class="col-md-8">
          {{ \Carbon\Carbon::parse($automezzo->created_at)->format('d/m/Y H:i') }}
        </dd>
      </dl>

      <div class="text-left mt-4">
        <a href="{{ route('automezzi.index') }}" class="btn btn-anpas-green me-2">
          <i class="fas fa-arrow-left me-1"></i> Torna all’elenco
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
