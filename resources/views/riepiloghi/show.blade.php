@extends('layouts.app')

@section('content')
<div class="container-fluid">
  {{-- Titolo --}}
  <h1 class="container-title mb-4">
    Riepilogo #{{ $riepilogo->idRiepilogo }} − Anno {{ $riepilogo->idAnno }}
  </h1>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <table class="common-css-dataTable table table-hover table-striped-anpas table-bordered mb-0 w-100">
        <thead class="thead-anpas">
          <tr>
            <th>Descrizione</th>
            <th>Preventivo</th>
            <th>Consuntivo</th>
          </tr>
        </thead>
        <tbody>
          @foreach($dati as $d)
            <tr>
              <td>{{ $d->descrizione }}</td>
              <td>{{ number_format($d->preventivo, 2, ',', '.') }}</td>
              <td>{{ number_format($d->consuntivo, 2, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div class="text-center mt-4">
        <a href="{{ route('riepiloghi.index') }}" class="btn btn-anpas-green me-2">
          <i class="fas fa-arrow-left me-1"></i> Torna ai Riepiloghi
        </a>
        <a href="{{ route('riepiloghi.edit', $riepilogo->idRiepilogo) }}" class="btn btn-anpas-edit">
          <i class="fas fa-edit me-1"></i> Modifica
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
