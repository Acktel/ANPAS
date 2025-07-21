@extends('layouts.app')
@section('content')
<div class="container-fluid">
  <h1>Dettaglio Dipendente #{{ $dipendente->idDipendente }}</h1>
  <dl class="row">
    <dt class="col-sm-3">Associazione</dt>
    <dd class="col-sm-9">{{ \App\Models\Associazione::getById($dipendente->idAssociazione)->Associazione }}</dd>

    <dt class="col-sm-3">Anno</dt>
    <dd class="col-sm-9">{{ $dipendente->idAnno }}</dd>

    <dt class="col-sm-3">Nome</dt>
    <dd class="col-sm-9">{{ $dipendente->DipendenteNome }}</dd>

    <dt class="col-sm-3">Cognome</dt>
    <dd class="col-sm-9">{{ $dipendente->DipendenteCognome }}</dd>

    <dt class="col-sm-3">Qualifiche</dt>
    <dd class="col-sm-9">{{ $qualifiche ?: 'Nessuna' }}</dd>

    <dt class="col-sm-3">Contratto Applicato</dt>
    <dd class="col-sm-9">{{ $dipendente->ContrattoApplicato }}</dd>

    <dt class="col-sm-3">Livello Mansione</dt>
    <dd class="col-sm-9">
      @if(!empty($livelliMansione))
        {{ collect($livelliMansione)->pluck('nome')->implode(', ') }}
      @else
        Nessuno
      @endif
    </dd>

    <dt class="col-sm-3">Creato il</dt>
    <dd class="col-sm-9">{{ \Carbon\Carbon::parse($dipendente->created_at)->format('d/m/Y H:i') }}</dd>

    <dt class="col-sm-3">Aggiornato il</dt>
    <dd class="col-sm-9">{{ \Carbon\Carbon::parse($dipendente->updated_at)->format('d/m/Y H:i') }}</dd>
  </dl>

  <a href="{{ route('dipendenti.index') }}" class="btn btn-secondary">
    ← Torna all’elenco
  </a>
  <a href="{{ route('dipendenti.edit', $dipendente->idDipendente) }}" class="btn btn-warning">
    Modifica
  </a>
  <form action="{{ route('dipendenti.destroy', $dipendente->idDipendente) }}"
        method="POST"
        style="display:inline;"
        onsubmit="return confirm('Sei sicuro di voler eliminare questo dipendente?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger">Elimina</button>
  </form>
</div>
@endsection
