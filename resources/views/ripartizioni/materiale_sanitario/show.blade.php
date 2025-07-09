@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">Ripartizione materiale sanitario – Anno {{ $anno }}</h1>

  <div class="mb-3">
    <a href="{{ route('ripartizioni.materiale_sanitario.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i> Torna all’elenco
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle">
      <thead class="table-light">
        <tr>
          <th rowspan="2">Automezzo</th>
          <th rowspan="2">Targa</th>
          <th rowspan="2">Incluso</th>
          @foreach($convenzioni as $conv)
            <th>{{ $conv->Convenzione }}</th>
          @endforeach
          <th rowspan="2">Totale</th>
        </tr>
      </thead>
      <tbody>
        @foreach($righe as $riga)
          <tr class="{{ $riga['incluso_riparto'] ? '' : 'table-secondary' }}">
            <td>{{ $riga['Automezzo'] }}</td>
            <td>{{ $riga['Targa'] }}</td>
            <td>{{ $riga['incluso_riparto'] ? 'SI' : 'NO' }}</td>
            @foreach($convenzioni as $conv)
              <td>{{ $riga['valori'][$conv->idConvenzione] ?? 0 }}</td>
            @endforeach
            <td>{{ $riga['totale'] }}</td>
          </tr>
        @endforeach

        {{-- Totale riga --}}
        <tr class="fw-bold">
          <td colspan="{{ 3 + count($convenzioni) }}">Totale incluso nel riparto</td>
          <td>{{ $totale_inclusi }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection
