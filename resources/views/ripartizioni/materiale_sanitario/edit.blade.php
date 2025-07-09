@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4">Seleziona Automezzi da includere nel riparto – Anno {{ $anno }}</h1>

  <form action="{{ route('ripartizioni.materiale_sanitario.update') }}" method="POST">
    @csrf
    @method('PUT')

    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Automezzo</th>
          <th>Targa</th>
          <th>Codice ID</th>
          <th>Includi nel Riparto?</th>
        </tr>
      </thead>
      <tbody>
        @foreach($dati as $a)
          <tr>
            <td>{{ $a->Automezzo }}</td>
            <td>{{ $a->Targa }}</td>
            <td>{{ $a->CodiceIdentificativo ?? '' }}</td>
            <td>
              <input type="checkbox" name="inclusi[]" value="{{ $a->idAutomezzo }}" {{ $a->incluso_riparto ? 'checked' : '' }}>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <button type="submit" class="btn btn-success">Salva</button>
    <a href="{{ route('ripartizioni.materiale_sanitario.index') }}" class="btn btn-secondary">Annulla</a>
  </form>
</div>
@endsection
