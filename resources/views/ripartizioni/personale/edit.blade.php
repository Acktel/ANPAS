{{-- resources/views/ripartizioni/personale/edit.blade.php --}}
@extends('layouts.app')

<?php
$nomeCompleto = $dipendente->DipendenteNome . ' ' . $dipendente->DipendenteCognome;
?>

@section('content')
<div class="container">
  <h1 class="container-title mb-4">
    Modifica Ripartizione – Dipendente: {{ $nomeCompleto }} – Anno {{ $anno }}
  </h1>

  <form method="POST" action="{{ route('ripartizioni.personale.update', $idDipendente) }}">
    @csrf
    @method('PUT')

    <div class="alert alert-info">
      Aggiorna le ore di servizio per ciascuna convenzione.
    </div>

    <div class="table-responsive">
      <table class="table table-bordered text-center align-middle">
        <thead class="table-light">
          <tr>
            <th>Convenzione</th>
            <th>Ore Servizio</th>
          </tr>
        </thead>
        <tbody>
          @foreach($convenzioni as $conv)
            @php
              // Valore salvato, o 0 se non esiste ancora
              $existing = $record->get($conv->idConvenzione);
              $val = $existing ? $existing->OreServizio : 0;
            @endphp
            <tr>
              <td>{{ $conv->Convenzione }}</td>
              <td>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="ore[{{ $idDipendente }}][{{ $conv->idConvenzione }}]"
                  class="form-control text-start"
                  value="{{ old("ore.{$idDipendente}.{$conv->idConvenzione}", $existing ? $existing->OreServizio : '') }}"
                  placeholder="0">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
    <div class="mt-4 text-center">
            <button type="submit" class="btn btn-anpas-green">
        <i class="fas fa-check me-1"></i> Salva
      </button>

      <a href="{{ route('ripartizioni.personale.index', ['idAssociazione' => $idAssociazione]) }}" class="btn btn-secondary">
        <i class="fas fa-times me-1"></i> Annulla
      </a>
    </div>

  </form>
</div>
@endsection
