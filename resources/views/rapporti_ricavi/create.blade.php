@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">Nuovo Inserimento Ricavi Convenzioni – Anno {{ $anno }}</h1>

  <form method="POST" action="{{ route('rapporti-ricavi.store') }}">
    @csrf

    <div class="mb-4">
      <label for="idAssociazione" class="form-label">Associazione</label>
      <select name="idAssociazione" id="idAssociazione" class="form-select" required>
        <option value="">-- Seleziona Associazione --</option>
        @foreach($associazioni as $ass)
          <option value="{{ $ass->idAssociazione }}">{{ $ass->Associazione }}</option>
        @endforeach
      </select>
    </div>

    <div class="alert alert-info">
      Inserisci il rimborso per ciascuna convenzione. Il totale verrà calcolato automaticamente.
    </div>

    <div class="table-responsive">
      <table class="table table-bordered text-center align-middle">
        <thead class="table-light">
          <tr>
            <th>Convenzione</th>
            <th>Rimborso (€)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($convenzioni as $conv)
            <tr>
              <td>{{ $conv->Convenzione }}</td>
              <td>
                <input type="number"
                       step=1.00" min="0"
                       name="ricavi[{{ $conv->idConvenzione }}]"
                       class="form-control text-end">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('rapporti-ricavi.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-check me-1"></i> Salva
      </button>
    </div>
  </form>
</div>
@endsection
