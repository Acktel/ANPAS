@extends('layouts.app')

@php
    $user = Auth::user();
@endphp

@section('content')
<div class="container-fluid">
  <h1>Elenco Automezzi - Anno {{ $anno }}</h1>


  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('automezzi.create') }}" class="btn btn-primary mb-3">
    + Nuovo Automezzo
  </a>

  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>Associazione</th>
        <th>Anno</th>
        <th>Veicolo</th>
        <th>Targa</th>
        <th>Codice ID</th>
        <th>Immatricolazione</th>
        <th>Modello</th>
        <th>Tipo Veicolo</th>
        <th>Km Riferimento</th>
        <th>Km Totali</th>
        <th>Carburante</th>
        <th>Ult. Aut. Sanitaria</th>
        <th>Ult. Collaudo</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
    
      @forelse($automezzi as $a)
        <tr>
          <td>{{ $a->idAutomezzo }}</td>
          <td>{{ $a->Associazione }}</td>
          <td>{{ $a->anno ?? $a->idAnno }}</td>
          <td>{{ $a->Automezzo }}</td>
          <td>{{ $a->Targa }}</td>
          <td>{{ $a->CodiceIdentificativo }}</td>
          <td>{{ $a->AnnoPrimaImmatricolazione }}</td>
          <td>{{ $a->Modello }}</td>
          <td>{{ $a->TipoVeicolo }}</td>
          <td>{{ $a->KmRiferimento }}</td>
          <td>{{ $a->KmTotali }}</td>
          <td>{{ $a->TipoCarburante }}</td>
          <td>{{ optional(\Carbon\Carbon::parse($a->DataUltimaAutorizzazioneSanitaria))->format('d/m/Y') }}</td>
          <td>{{ optional(\Carbon\Carbon::parse($a->DataUltimoCollaudo))->format('d/m/Y') }}</td>
          <td>
            <a href="{{ route('automezzi.show', $a->idAutomezzo) }}"
               class="btn btn-sm btn-info" title="Dettagli">
              <i class="bi bi-eye"></i>
              Dettagli
            </a>
            <a href="{{ route('automezzi.edit', $a->idAutomezzo) }}"
               class="btn btn-sm btn-warning" title="Modifica">
               Modifica
              <i class="bi bi-pencil-square"></i>
            </a>
            <form action="{{ route('automezzi.destroy', $a->idAutomezzo) }}"
                  method="POST"
                  style="display:inline;"
                  onsubmit="return confirm('Sei sicuro di voler eliminare questo automezzo?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger" title="Elimina">
                <i class="bi bi-trash"></i>
                Elimina
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="15" class="text-center">Nessun automezzo trovato.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
