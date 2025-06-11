@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Dettaglio Automezzo #{{ $automezzo->idAutomezzo }}</h1>

  <div class="mb-3">
    <strong>Associazione:</strong> {{ $automezzo->idAssociazione }}<br>
    <strong>Anno:</strong> {{ $automezzo->idAnno }}<br>
    <strong>Veicolo:</strong> {{ $automezzo->Automezzo }}<br>
    <strong>Targa:</strong> {{ $automezzo->Targa }}<br>
    <strong>Codice ID:</strong> {{ $automezzo->CodiceIdentificativo }}<br>
    <strong>Anno Prima Immatricolazione:</strong> {{ $automezzo->AnnoPrimaImmatricolazione }}<br>
    <strong>Modello:</strong> {{ $automezzo->Modello }}<br>
    <strong>Tipo Veicolo:</strong> {{ $automezzo->TipoVeicolo }}<br>
    <strong>Km Riferimento:</strong> {{ $automezzo->KmRiferimento }}<br>
    <strong>Km Totali:</strong> {{ $automezzo->KmTotali }}<br>
    <strong>Tipo Carburante:</strong> {{ $automezzo->TipoCarburante }}<br>
    <strong>Data Ultima Autorizzazione Sanitaria:</strong> 
      {{ optional(\Carbon\Carbon::parse($automezzo->DataUltimaAutorizzazioneSanitaria))->format('d/m/Y') }}<br>
    <strong>Data Ultimo Collaudo:</strong> 
      {{ optional(\Carbon\Carbon::parse($automezzo->DataUltimoCollaudo))->format('d/m/Y') }}<br>
    <strong>Creato il:</strong> 
      {{ \Carbon\Carbon::parse($automezzo->created_at)->format('d/m/Y H:i') }}<br>
  </div>

  <hr>

  <a href="{{ route('automezzi.index') }}" class="btn btn-secondary mt-3">← Torna all’elenco</a>
</div>
@endsection
