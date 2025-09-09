@php
  $num2 = fn($v) => number_format((float)$v, 2, ',', '.');
  $pct2 = fn($v) => number_format((float)$v, 2, ',', '.') . '%';
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Rapporti ricavi – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
    h1 { margin: 0 0 6px 0; }
    .small { font-size: 11px; color:#555; margin-bottom: 8px; }

    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    th, td { border:1px solid #ccc; padding:6px 7px; vertical-align:middle; }
    thead th { background:#f8fafc; text-align:center; }
    .text-end { text-align:right; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }

    .col-tot { width: 30%; }
    .col-rimb { width: 20%; }
    .col-pct { width: 20%; }
    .col-100 { width: 30%; }
  </style>
</head>
<body>
  <h1>TABELLA DI CALCOLO DELLE PERCENTUALI – RAPPORTO TRA RICAVI DELLE CONVENZIONI E TOTALE ESERCIZIO</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio: <strong>{{ $anno }}</strong>
  </div>

  <table>
    <thead>
      <tr>
        <th class="col-tot" rowspan="2">TOTALE RICAVI DELL’ESERCIZIO</th>
        @foreach ($convenzioni as $c)
          <th colspan="2">{{ $c->Convenzione }}</th>
        @endforeach
        <th class="col-100" rowspan="2">Totale %</th>
      </tr>
      <tr>
        @foreach ($convenzioni as $c)
          <th class="col-rimb">RIMBORSO</th>
          <th class="col-pct">%</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-end">{{ $num2($row['TotaleEsercizio'] ?? 0) }}</td>
        @foreach ($convenzioni as $c)
          @php $k = 'c'.$c->idConvenzione; @endphp
          <td class="text-end">{{ $num2($row[$k.'_rimborso'] ?? 0) }}</td>
          <td class="text-end">{{ $pct2($row[$k.'_percent'] ?? 0) }}</td>
        @endforeach
        <td class="text-end">100,00%</td>
      </tr>
    </tbody>
  </table>
</body>
</html>
