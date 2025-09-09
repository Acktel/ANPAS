{{-- Ripartizione costi personale dipendente – PDF --}}
@php
  // helpers formattazione
  $num0 = fn($v) => number_format((float)$v, 0, ',', '.');
  $pct2 = fn($v) => number_format((float)$v, 2, ',', '.') . '%';
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Ripartizione personale – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
    h1 { margin:0 0 8px 0; font-size: 16px; }
    .small { color:#444; }

    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    th, td { border:1px solid #ccc; padding:5px 6px; vertical-align: middle; }
    th { background:#f8fafc; text-align:center; }
    .text-end { text-align:right; }
    .row-total { background:#f6f6f6; font-weight:bold; }

    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }

    .col-name { width: 18%; }
    .col-tot  { width: 9%; }
    .col-ore  { width: 9%; }
    .col-pct  { width: 12%; }
  </style>
</head>
<body>
  <h1>TABELLA DI CALCOLO PER LA RIPARTIZIONE DEI COSTI DA PERSONALE DIPENDENTE</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio finanziario: <strong>{{ $anno }}</strong>
  </div>

  <table>
    <thead>
      <tr>
        <th class="col-name" rowspan="2">COGNOME E NOME DIPENDENTE</th>
        <th class="col-tot"  rowspan="2">ORE TOTALI<br>ANNUE DI SERVIZIO</th>
        @foreach($convenzioni as $c)
          <th colspan="2">{{ $c->Convenzione }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach($convenzioni as $c)
          <th class="col-ore">ORE DI SERVIZIO</th>
          <th class="col-pct">%</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr class="{{ ($r['is_totale'] ?? 0) === -1 ? 'row-total' : '' }}">
          <td>{{ $r['FullName'] ?? '' }}</td>
          <td class="text-end">{{ $num0($r['OreTotali'] ?? 0) }}</td>
          @foreach($convenzioni as $c)
            @php $k = 'c'.$c->idConvenzione; @endphp
            <td class="text-end">{{ $num0($r[$k.'_ore'] ?? 0) }}</td>
            <td class="text-end">{{ $pct2($r[$k.'_percent'] ?? 0) }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
