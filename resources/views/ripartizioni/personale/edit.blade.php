{{-- resources/views/ripartizioni/personale/edit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">
    Modifica Ripartizione – Dipendente #{{ $idDipendente }} – Anno {{ $anno }}
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
                  class="form-control text-end"
                  value="{{ old("ore.{$idDipendente}.{$conv->idConvenzione}", $val) }}"
                >
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('ripartizioni.personale.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-save me-1"></i> Salva
      </button>
    </div>
  </form>
</div>
@endsection
