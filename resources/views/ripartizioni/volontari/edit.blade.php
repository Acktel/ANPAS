@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4 container-title">Modifica rimborsi volontari – Anno {{ $anno }}</h1>

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
              <input type="number" name="ore[{{ $conv->idConvenzione }}]" class="form-control" value="{{ ($valore !== null && $valore != 0) ? $valore : '' }}" step=1.00" min="0" placeholder="0">
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

        <div class="text-center mt-4">
    <button type="submit" class="btn btn-anpas-green"><i class="fas fa-check me-1"></i>Salva</button>
    <a href="{{ route('ripartizioni.volontari.index') }}" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annulla</a>
    </div>

  </form>
</div>
@endsection
