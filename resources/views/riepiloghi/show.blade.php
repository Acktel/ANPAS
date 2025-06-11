@extends('layouts.app')

@section('content')
<div class="container">
  <h1>Riepilogo #{{ $riepilogo->idRiepilogo }} (Anno {{ $riepilogo->idAnno }})</h1>

  <table class="table">
    <thead>
      <tr><th>Descrizione</th><th>Preventivo</th><th>Consuntivo</th></tr>
    </thead>
    <tbody>
      @foreach($dati as $d)
      <tr>
        <td>{{ $d->descrizione }}</td>
        <td>{{ number_format($d->preventivo,2,',','.') }}</td>
        <td>{{ number_format($d->consuntivo,2,',','.') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
