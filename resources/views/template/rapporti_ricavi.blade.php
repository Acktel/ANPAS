{{-- resources/views/template/rapporti_ricavi.blade.php --}}
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
    @page { size: A4 landscape; margin: 8mm; }        /* margini stretti */
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9.2px; color:#111; }

    h1 { margin: 0 0 6px 0; font-size:12px; }
    .small { font-size: 10px; color:#555; margin-bottom: 6px; }

    .section { margin: 6px 0 8px 0; page-break-inside: avoid; }

    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    thead { display: table-header-group; }            /* header ripetuto se spezza */
    tr { page-break-inside: avoid; }

    th, td { border:1px solid #999; padding:3px 4px; vertical-align:middle; font-size:9px; line-height:1.06; }
    thead th { background:#f2f6ff; text-align:center; font-weight:700; }

    .text-end { text-align:right; }
    .nowrap { white-space:nowrap; }
    .ellipsis { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    /* larghezze compatte: 1 col “totale esercizio”, poi (RIMBORSO,%)*N, poi “Totale %” */
    .col-tot  { width: 18%; }
    .col-100  { width: 8%; }
    .col-rimb { width: 7%; }
    .col-pct  { width: 6%; }

    /* titoli stretti */
    .tiny { font-size: 8.4px; line-height:1.05; }
  </style>
</head>
<body>

  <h1>TABELLA DI CALCOLO DELLE PERCENTUALI – RAPPORTO TRA RICAVI DELLE CONVENZIONI E TOTALE ESERCIZIO</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio: <strong>{{ $anno }}</strong>
  </div>

  <div class="section">
    <table>
      <colgroup>
        <col class="col-tot">
        @foreach ($convenzioni as $c)
          <col class="col-rimb">
          <col class="col-pct">
        @endforeach
        <col class="col-100">
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2" class="nowrap">TOTALE RICAVI DELL’ESERCIZIO</th>
          @foreach ($convenzioni as $c)
            <th colspan="2" class="ellipsis">{{ $c->Convenzione }}</th>
          @endforeach
          <th rowspan="2" class="nowrap">Totale %</th>
        </tr>
        <tr>
          @foreach ($convenzioni as $c)
            <th class="tiny nowrap">RIMBORSO</th>
            <th class="tiny nowrap">%</th>
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
  </div>

</body>
</html>
