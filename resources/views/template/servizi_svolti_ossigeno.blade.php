{{-- Servizi svolti per ripartizione ossigeno/materiale sanitario – PDF --}}
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
    @page { size: A4 landscape; margin: 12mm; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
    h1 { margin:0 0 6px 0; }
    .small { color:#444; margin-bottom:8px; }
    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    th,td { border:1px solid #ccc; padding:5px 6px; vertical-align: middle; }
    th { background:#f8fafc; text-align:center; }
    .text-end { text-align:right; }
    .row-total { background:#f6f6f6; font-weight:700; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .col-targa { width: 10%; }
    .col-cod   { width: 12%; }
    .col-flag  { width: 14%; background:#fff59d; }  /* colonna gialla */
    .col-tot   { width: 12%; }
    .col-n     { width: 7%; }
    .col-pct   { width: 6%; }
    .band { background:#00c0d1; height:6px; margin:8px 0 10px; }
    .magenta { background:#ff36d8; height:8px; margin:8px 0 6px; }
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
          <th class="col-n">N. SERVIZI SVOLTI</th>
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
