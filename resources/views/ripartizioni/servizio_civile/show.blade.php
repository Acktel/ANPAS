@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">
    Dettaglio Ripartizione – Servizio Civile – Anno {{ $anno }}
  </h1>

  <div class="mb-4">
    <a href="{{ route('ripartizioni.servizio_civile.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i> Torna all’elenco
    </a>
    <a href="{{ route('ripartizioni.servizio_civile.edit') }}" class="btn btn-warning">
      <i class="fas fa-edit me-1"></i> Modifica
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle">
      <thead class="table-light">
        <tr>
          <th>Convenzione</th>
          <th>Ore Servizio</th>
          <th>% Sul Totale</th>
        </tr>
      </thead>
      <tbody>
        @foreach($convenzioni as $conv)
          @php
            $ore = $record->has($conv->idConvenzione)
                 ? $record->get($conv->idConvenzione)->OreServizio
                 : 0;
            $percent = $totOre > 0 ? round($ore / $totOre * 100, 2) : 0;
          @endphp
          <tr>
            <td>{{ $conv->Convenzione }}</td>
            <td>{{ number_format($ore, 2, ',', '.') }}</td>
            <td>{{ number_format($percent, 2, ',', '.') }}%</td>
          </tr>
        @endforeach

        {{-- riga Totale --}}
        <tr class="fw-bold">
          <td>TOTALE</td>
          <td>{{ number_format($totOre, 2, ',', '.') }}</td>
          <td>100,00%</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection
