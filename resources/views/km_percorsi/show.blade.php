@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">
    Dettaglio KM percorsi – {{ $automezzo->Targa }} – Anno {{ session('anno_riferimento') }}
  </h1>

  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center">
      <thead class="table-light">
        <tr>
          <th>Convenzione</th>
          <th>KM Percorsi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($convenzioni as $conv)
          @php
            $km = $kmEsistenti[$conv->idConvenzione]->KMPercorsi ?? 0;
          @endphp
          <tr>
            <td>{{ $conv->Convenzione }}</td>
            <td>{{ number_format($km, 2, ',', '.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="mt-4">
    <a href="{{ route('km-percorsi.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i> Indietro
    </a>
  </div>
</div>
@endsection
