{{-- Ripartizione costi personale dipendente – PDF (compatto) --}}
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
    @page { size: A4 landscape; margin: 8mm; } /* margine stretto */
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 8.6px; color:#111; } /* font più fitto */
    h1 { margin:0 0 6px 0; font-size: 12px; }
    .small { color:#444; margin: 0 0 6px 0; font-size: 8.2px; }

    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    thead { display: table-header-group; } /* ripete header quando spezza pagina */
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }

    th, td { border:1px solid #ccc; padding: 2px 3px; vertical-align: middle; line-height: 1.15; }
    th { background:#f8fafc; text-align:center; font-weight:700; font-size: 8.1px; }
    td { font-size: 8.1px; }

    .text-end { text-align:right; }
    .row-total { background:#f6f6f6; font-weight:700; }

    /* colonne più strette per farci stare tante convenzioni */
    .col-name { width: 20%; }  /* nome un po’ più largo ma non enorme */
    .col-tot  { width: 9%; }   /* ore totali annue */
    .col-ore  { width: 7%; }
    .col-pct  { width: 6%; }

    /* se ci sono tante convenzioni, riduciamo appena la dimensione nelle celle dati per stringere ancora */
    .tight td { font-size: 7.9px; padding: 2px; }
  </style>
</head>
<body>
  <h1>TABELLA DI CALCOLO PER LA RIPARTIZIONE DEI COSTI DA PERSONALE DIPENDENTE</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio finanziario: <strong>{{ $anno }}</strong>
  </div>

  @php
    // se le convenzioni sono tante (>=8 per esempio), attiva stile "tight"
    $convCount = is_countable($convenzioni) ? count($convenzioni) : 0;
    $tableClass = $convCount >= 8 ? 'tight' : '';
  @endphp

  <table class="{{ $tableClass }}">
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
          <th class="col-ore">ORE</th>
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
