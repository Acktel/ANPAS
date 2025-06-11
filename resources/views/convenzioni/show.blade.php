{{-- resources/views/convenzioni/show.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Dettagli Convenzione #{{ $conv->idConvenzione }}</h1>

  <a href="{{ route('convenzioni.index') }}" class="btn btn-secondary mb-3">
    ← Torna all’elenco Convenzioni
  </a>

  <div class="card">
    <div class="card-body">
      <table class="table table-borderless mb-0">
        <tr>
          <th scope="row" style="width: 200px;">ID Convenzione</th>
          <td>{{ $conv->idConvenzione }}</td>
        </tr>
        <tr>
          <th scope="row">Associazione</th>
          <td>{{ $conv->Associazione }}</td>
        </tr>
        <tr>
          <th scope="row">Anno</th>
          <td>{{ $conv->idAnno }}</td>
        </tr>
        <tr>
          <th scope="row">Descrizione</th>
          <td>{{ $conv->Convenzione }}</td>
        </tr>
        <tr>
          <th scope="row">Lettera identificativa</th>
          <td>{{ $conv->lettera_identificativa }}</td>
        </tr>
        <tr>
          <th scope="row">Creato il</th>
          <td>{{ \Carbon\Carbon::parse($conv->created_at)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
          <th scope="row">Ultima modifica</th>
          <td>{{ \Carbon\Carbon::parse($conv->updated_at)->format('d/m/Y H:i') }}</td>
        </tr>
      </table>
    </div>
    <div class="card-footer d-flex">
      <a href="{{ route('convenzioni.edit', $conv->idConvenzione) }}" 
         class="btn btn-warning me-2">
        Modifica
      </a>
      <form action="{{ route('convenzioni.destroy', $conv->idConvenzione) }}" 
            method="POST" 
            onsubmit="return confirm('Sei sicuro di voler eliminare questa convenzione?');">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger">Elimina</button>
      </form>
    </div>
  </div>
</div>
@endsection
