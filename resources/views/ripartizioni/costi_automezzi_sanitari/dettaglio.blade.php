@extends('layouts.app')

@section('title', $titolo)
@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">{{ $titolo }} — Anno {{ $anno }}</h1>

  <table class="table table-striped-anpas table-bordered w-100 text-center align-middle">
    <thead class="thead-anpas">
      <tr>
        @foreach($colonne as $c)
          <th>{{ strtoupper($c) }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($righe as $r)
        <tr>
          @foreach($colonne as $c)
            @php $v = $r[$c] ?? ($c==='voce' ? '' : 0); @endphp
            @if($c==='voce')
              <td class="text-start">{{ $r['voce'] }}</td>
            @elseif($c==='totale')
              <td class="text-end">€ {{ number_format((float)$v,2,',','.') }}</td>
            @else
              <td class="text-end">€ {{ number_format((float)$v,2,',','.') }}</td>
            @endif
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($colonne) }}">Nessun dato.</td></tr>
      @endforelse
    </tbody>
  </table>
  <a href="{{ route('ripartizioni.costi_automezzi_sanitari.index')}}" class="btn btn-secondary ms-2">
    <i class="fas fa-times me-1"></i>Annulla
  </a>
</div>
@endsection
