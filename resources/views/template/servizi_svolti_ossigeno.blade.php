{{-- Servizi svolti per ripartizione ossigeno/materiale sanitario – PDF (compatto) --}}
@php
  $num0 = fn($v)=>number_format((float)$v,0,',','.');
  $pct2 = fn($v)=>number_format((float)$v,2,',','.').'%';
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Servizi svolti (ossigeno) – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
  <style>
    @page { size: A4 landscape; margin: 8mm; } /* margini più stretti */
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 8.6px; color:#111; } /* font fitto */
    h1 { margin:0 0 6px 0; font-size: 12px; }
    .small { color:#444; margin-bottom:6px; font-size: 8.2px; }

    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    thead { display: table-header-group; }  /* ripete head quando spezza */
    tr { page-break-inside: avoid; }

    th,td { border:1px solid #ccc; padding:2px 3px; vertical-align: middle; line-height: 1.15; }
    th { background:#f8fafc; text-align:center; font-weight:700; font-size: 8.1px; }
    td { font-size: 8.1px; }
    .text-end { text-align:right; }
    .row-total { background:#f6f6f6; font-weight:700; }

    /* colonne ottimizzate */
    .col-targa { width: 9%; }
    .col-cod   { width: 11%; }
    .col-flag  { width: 14%; background:#fff59d; }  /* colonna gialla */
    .col-tot   { width: 11%; }
    .col-n     { width: 6.5%; }
    .col-pct   { width: 5.5%; }

    /* banda separatrice più sottile per non sprecare spazio */
    .magenta { background:#ff36d8; height:4px; margin:6px 0 6px; }
  </style>
</head>
<body>
  <h1>TABELLA DI CALCOLO DELLE PERCENTUALI INERENTI IL NUMERO DEI SERVIZI SVOLTI AL FINE DELLA RIPARTIZIONE DEI COSTI DI OSSIGENO E MATERIALE SANITARIO</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio finanziario: <strong>{{ $anno }}</strong>
  </div>
  <div class="magenta"></div>

  <table>
    <thead>
      <tr>
        <th class="col-targa" rowspan="2">TARGA</th>
        <th class="col-cod"   rowspan="2">CODICE IDENTIFICATIVO</th>
        <th class="col-flag"  rowspan="2">CONTEGGIO SERVIZI PER RIPARTIZIONE OSSIGENO E MATERIALE SANITARIO<br><em>SI/NO</em></th>
        <th class="col-tot"   rowspan="2">TOTALI NUMERO<br>SERVIZI SVOLTI NELL’ANNO</th>
        @foreach($convenzioni as $c)
          <th colspan="2">{{ $c->Convenzione }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach($convenzioni as $c)
          <th class="col-n">N. SERVIZI</th>
          <th class="col-pct">%</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr class="{{ ($r['is_totale']??0)===-1 ? 'row-total' : '' }}">
          <td>{{ ($r['is_totale']??0)===-1 ? '' : ($r['Targa'] ?? '') }}</td>
          <td>{{ ($r['is_totale']??0)===-1 ? '' : ($r['CodiceIdentificativo'] ?? '') }}</td>
          <td style="background:#fff59d">{{ ($r['is_totale']??0)===-1 ? '' : ($r['RipartoOssigeno'] ?? '') }}</td>
          <td class="text-end">{{ $num0($r['Totale'] ?? 0) }}</td>
          @foreach($convenzioni as $c)
            @php $k = 'c'.$c->idConvenzione; @endphp
            <td class="text-end">{{ $num0($r[$k.'_n'] ?? 0) }}</td>
            <td class="text-end">{{ $pct2($r[$k.'_percent'] ?? 0) }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
