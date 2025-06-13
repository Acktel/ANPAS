@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="text-anpas-green fw-bold mb-4">
    Dettaglio Automezzo #{{ $automezzo->idAutomezzo }}
  </h1>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <dl class="row mb-0">
        <dt class="col-sm-3">Associazione:</dt>
        <dd class="col-sm-9">{{ $automezzo->idAssociazione }}</dd>

        <dt class="col-sm-3">Anno:</dt>
        <dd class="col-sm-9">{{ $automezzo->idAnno }}</dd>

        <dt class="col-sm-3">Veicolo:</dt>
        <dd class="col-sm-9">{{ $automezzo->Automezzo }}</dd>

        <dt class="col-sm-3">Targa:</dt>
        <dd class="col-sm-9">{{ $automezzo->Targa }}</dd>

        <dt class="col-sm-3">Codice ID:</dt>
        <dd class="col-sm-9">{{ $automezzo->CodiceIdentificativo }}</dd>

        <dt class="col-sm-3">Prima Immatricolazione:</dt>
        <dd class="col-sm-9">{{ $automezzo->AnnoPrimaImmatricolazione }}</dd>

        <dt class="col-sm-3">Modello:</dt>
        <dd class="col-sm-9">{{ $automezzo->Modello }}</dd>

        <dt class="col-sm-3">Tipo Veicolo:</dt>
        <dd class="col-sm-9">{{ $automezzo->TipoVeicolo }}</dd>

        <dt class="col-sm-3">Km Rif.:</dt>
        <dd class="col-sm-9">{{ $automezzo->KmRiferimento }}</dd>

        <dt class="col-sm-3">Km Totali:</dt>
        <dd class="col-sm-9">{{ $automezzo->KmTotali }}</dd>

        <dt class="col-sm-3">Carburante:</dt>
        <dd class="col-sm-9">{{ $automezzo->TipoCarburante }}</dd>

        <dt class="col-sm-3">Ult. Aut. Sanitaria:</dt>
        <dd class="col-sm-9">
          {{ optional(\Carbon\Carbon::parse($automezzo->DataUltimaAutorizzazioneSanitaria))->format('d/m/Y') }}
        </dd>

        <dt class="col-sm-3">Ult. Revisione:</dt>
        <dd class="col-sm-9">
          {{ optional(\Carbon\Carbon::parse($automezzo->DataUltimoCollaudo))->format('d/m/Y') }}
        </dd>

        <dt class="col-sm-3">Creato il:</dt>
        <dd class="col-sm-9">
          {{ \Carbon\Carbon::parse($automezzo->created_at)->format('d/m/Y H:i') }}
        </dd>
      </dl>

      <a href="{{ route('automezzi.index') }}" class="btn btn-anpas-red mt-3">
        ← Torna all’elenco
      </a>
    </div>
  </div>
</div>
@endsection
