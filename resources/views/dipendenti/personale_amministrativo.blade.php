{{-- resources/views/dipendenti/personale_amministrativo.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4">Personale Amministrativo</h1>

  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle">
      <thead class="table-light">
        <tr>
          <th rowspan="2">Associazione</th>
          <th rowspan="2"></th>
          <th rowspan="2"></th>
          <th colspan="4">Totale Consuntivo</th>
          <th rowspan="2">Totale Finale</th>
        </tr>
        <tr>
          <th>Retribuzioni</th>
          <th>Oneri sociali</th>
          <th>TFR</th>
          <th>Consulenze</th>
        </tr>
      </thead>
      <tbody>
        {{-- Riga di esempio per “CIRRINCIONE” --}}
        <tr>
          <td>CIRRINCIONE</td>
          <td></td>
          <td></td>
          <td>{{ number_format($totRetribuzioni, 2, ',', '.') }}</td>
          <td>{{ number_format($totOneri, 2, ',', '.') }}</td>
          <td>{{ number_format($totTfr,      2, ',', '.') }}</td>
          <td>{{ number_format($totConsul,  2, ',', '.') }}</td>
          <td>{{ number_format($totFinale,  2, ',', '.') }}</td>
        </tr>

        {{-- Totale Ore / Ore Servizi / % --}}
        <tr>
          <td></td>
          <td><strong>TOT. ORE</strong></td>
          <td><strong>ORE SERVIZI</strong></td>
          <td><strong>%</strong></td>
          <td colspan="3"></td>
          <td></td>
        </tr>
        <tr>
          <td></td>
          <td>{{ number_format($totOre, 2, ',', '.') }}</td>
          <td>{{ number_format($oreServizi, 0, ',', '.') }}</td>
          <td>{{ number_format($percServizi, 2, ',', '.') }}%</td>
          <td>{{ number_format($retribServizi, 2, ',', '.') }}</td>
          <td>{{ number_format($oneriServizi,    2, ',', '.') }}</td>
          <td>{{ number_format($tfrServizi,      2, ',', '.') }}</td>
          <td>{{ number_format($consulServizi,   2, ',', '.') }}</td>
        </tr>

        {{-- Totale Ore / Ore Coord / % --}}
        <tr>
          <td></td>
          <td><strong>TOT. ORE</strong></td>
          <td><strong>ORE COORD</strong></td>
          <td><strong>%</strong></td>
          <td colspan="3"></td>
          <td></td>
        </tr>
        <tr>
          <td></td>
          <td>{{ number_format($totOre, 2, ',', '.') }}</td>
          <td>{{ number_format($oreCoord, 0, ',', '.') }}</td>
          <td>{{ number_format($percCoord, 2, ',', '.') }}%</td>
          <td>{{ number_format($retribCoord, 2, ',', '.') }}</td>
          <td>{{ number_format($oneriCoord,    2, ',', '.') }}</td>
          <td>{{ number_format($tfrCoord,      2, ',', '.') }}</td>
          <td>{{ number_format($consulCoord,   2, ',', '.') }}</td>
        </tr>

        {{-- Riga di verifica --}}
        <tr>
          <td><strong>verifica</strong></td>
          <td>{{ number_format($verificaOre, 0, ',', '.') }}</td>
          <td><strong>100,00%</strong></td>
          <td>{{ number_format($verTotRetribuzioni, 2, ',', '.') }}</td>
          <td>{{ number_format($verTotOneri,        2, ',', '.') }}</td>
          <td>{{ number_format($verTotTfr,          2, ',', '.') }}</td>
          <td>{{ number_format($verTotConsul,       2, ',', '.') }}</td>
          <td>{{ number_format($verTotFinale,       2, ',', '.') }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection
