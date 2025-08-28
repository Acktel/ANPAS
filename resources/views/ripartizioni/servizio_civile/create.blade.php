@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">Nuova Ripartizione Ore – Servizio Civile Nazionale – Anno {{ $anno }}</h1>

  <form method="POST" action="{{ route('ripartizioni.servizio_civile.store') }}">
    @csrf

    <div class="alert alert-info">
      Inserisci le ore di servizio prestate da ciascun partecipante al Servizio Civile per ogni convenzione.
    </div>

    <div class="table-responsive">
      <table class="table table-bordered text-center align-middle">
        <thead class="table-light">
          <tr>
            <th>Partecipante</th>
            @foreach($convenzioni as $conv)
              <th>{{ $conv->Convenzione }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @php
            // Recupera i partecipanti al servizio civile per associazione/anno
            $dipendenti = App\Models\Dipendente::getByQualifica(
              16, // ID qualifica Servizio Civile
              $anno,
              Auth::user()->hasAnyRole(['SuperAdmin','Admin','Supervisor'])
                ? null
                : Auth::user()->idAssociazione
            );
          @endphp

          @foreach($dipendenti as $d)
            <tr>
              <td>{{ $d->DipendenteCognome }} {{ $d->DipendenteNome }}</td>
              @foreach($convenzioni as $conv)
                <td>
                  <input
                    type="number"
                    step=1.00"
                    min="0"
                    name="ore[{{ $d->idDipendente }}][{{ $conv->idConvenzione }}]"
                    class="form-control text-end"
                    value="{{ old("ore.{$d->idDipendente}.{$conv->idConvenzione}", '') }}"
                  >
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('ripartizioni.servizio_civile.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-save me-1"></i> Salva
      </button>
    </div>
  </form>
</div>
@endsection
