@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4">Modifica rimborsi volontari – Anno {{ $anno }}</h1>

  <form method="POST" action="{{ route('ripartizioni.volontari.update') }}">
    @csrf
    @method('PUT')

    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Convenzione</th>
          <th>Ore di Servizio</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($convenzioni as $conv)
          @php
            $valore = $record[$conv->idConvenzione]->OreServizio ?? 0;
          @endphp
          <tr>
            <td>{{ $conv->Convenzione }}</td>
            <td>
              <input type="number" name="ore[{{ $conv->idConvenzione }}]" class="form-control" value="{{ $valore }}" step="0.01" min="0">
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <button type="submit" class="btn btn-success">Salva</button>
    <a href="{{ route('ripartizioni.volontari.index') }}" class="btn btn-secondary">Annulla</a>
  </form>
</div>
@endsection
