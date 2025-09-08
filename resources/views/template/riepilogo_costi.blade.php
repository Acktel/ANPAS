{{-- resources/views/riepilogo_costi/pdf.blade.php --}}
@php
  $eur = fn($v) => number_format((float)$v, 2, ',', '.');
  $pct = function(float $prev, float $cons) {
    if ($prev == 0.0) return '—';
    $val = (($cons - $prev) / $prev) * 100;
    return number_format($val, 2, ',', '.') . '%';
  };
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Riepilogo Costi – {{ $anno }} – {{ $associazione->Associazione ?? ('#'.$idAssociazione) }}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color:#111; }
    h1,h2,h3 { margin: 0 0 6px 0; }
    .header { margin-bottom: 10px; }
    .small { font-size: 11px; color:#555; }

    .totale-box { margin: 8px 0 16px 0; }
    .grid-3 { width:100%; border-collapse: collapse; table-layout: fixed; }
    .grid-3 th, .grid-3 td { border:1px solid #ccc; padding:6px 8px; }
    .grid-3 th { background:#f1f5f9; text-align:left; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }

    .section { margin: 8px 0 14px 0; }
    .section-title { background:#e9f7ef; border:1px solid #b7e2c8; padding:6px 10px; font-weight:bold; }
    .tbl { width:100%; border-collapse: collapse; }
    .tbl th, .tbl td { border:1px solid #ccc; padding:5px 7px; }
    .tbl th { background:#f8fafc; }
    .row-sum { background:#f6f6f6; font-weight:bold; }
    .page-break { page-break-before: always; }

    /* ripeti thead se la tabella spezza pagina (supportato da dompdf) */
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }
  </style>
</head>
<body>
  {{-- HEADER --}}
  <div class="header">
    <h1>Riepilogo Costi</h1>
    <div class="small">
      Anno: <strong>{{ $anno }}</strong> —
      Associazione: <strong>{{ $associazione->Associazione ?? ('#'.$idAssociazione) }}</strong>
    </div>
  </div>

  {{-- RIEPILOGO GENERALE (TOTALE) --}}
  <div class="totale-box">
    <h2>Totale generale (TOTALE)</h2>
    <table class="grid-3">
      <thead>
        <tr>
          <th>Descrizione</th>
          <th class="text-end">Preventivo</th>
          <th class="text-end">Consuntivo</th>
          <th class="text-end">% Scostamento</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Totale complessivo</strong></td>
          <td class="text-end">{{ $eur($totaleTot['prev']) }}</td>
          <td class="text-end">{{ $eur($totaleTot['cons']) }}</td>
          <td class="text-end">{{ $pct($totaleTot['prev'], $totaleTot['cons']) }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- DETTAGLIO PER SEZIONE (TOTALE) --}}
  @foreach ($blocks[0]['sezioni'] as $sid => $sez)
    <div class="section">
      <div class="section-title">{{ $sid }} — {{ $sez['titolo'] }}</div>
      <table class="tbl">
        <thead>
          <tr>
            <th style="width:55%">Descrizione</th>
            <th class="text-end" style="width:15%">Preventivo</th>
            <th class="text-end" style="width:15%">Consuntivo</th>
            <th class="text-end" style="width:15%">% Scostamento</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($sez['rows'] as $r)
            <tr>
              <td>{{ $r->descrizione }}</td>
              <td class="text-end">{{ $eur($r->preventivo) }}</td>
              <td class="text-end">{{ $eur($r->consuntivo) }}</td>
              <td class="text-end">{{ $pct($r->preventivo, $r->consuntivo) }}</td>
            </tr>
          @endforeach
          <tr class="row-sum">
            <td>Totale </td>
            <td class="text-end">{{ $eur($sez['sumPrev']) }}</td>
            <td class="text-end">{{ $eur($sez['sumCons']) }}</td>
            <td class="text-end">{{ $pct($sez['sumPrev'], $sez['sumCons']) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  @endforeach

  {{-- UNA PAGINA PER OGNI CONVENZIONE --}}
  @foreach (array_slice($blocks, 1) as $block)
    <div class="page-break"></div>
    <h2>Convenzione: {{ $block['nome'] }}</h2>

    <table class="grid-3" style="margin-bottom:10px">
      <thead>
        <tr>
          <th>Descrizione</th>
          <th class="text-end">Preventivo</th>
          <th class="text-end">Consuntivo</th>
          <th class="text-end">% Scostamento</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Totale convenzione</strong></td>
          <td class="text-end">{{ $eur($block['totPrev']) }}</td>
          <td class="text-end">{{ $eur($block['totCons']) }}</td>
          <td class="text-end">{{ $pct($block['totPrev'], $block['totCons']) }}</td>
        </tr>
      </tbody>
    </table>

    @foreach ($block['sezioni'] as $sid => $sez)
      <div class="section">
        <div class="section-title">{{ $sid }} — {{ $sez['titolo'] }}</div>
        <table class="tbl">
          <thead>
            <tr>
              <th style="width:55%">Descrizione</th>
              <th class="text-end" style="width:15%">Preventivo</th>
              <th class="text-end" style="width:15%">Consuntivo</th>
              <th class="text-end" style="width:15%">% Scostamento</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($sez['rows'] as $r)
              <tr>
                <td>{{ $r->descrizione }}</td>
                <td class="text-end">{{ $eur($r->preventivo) }}</td>
                <td class="text-end">{{ $eur($r->consuntivo) }}</td>
                <td class="text-end">{{ $pct($r->preventivo, $r->consuntivo) }}</td>
              </tr>
            @endforeach
            <tr class="row-sum">
              <td>Totale sezione</td>
              <td class="text-end">{{ $eur($sez['sumPrev']) }}</td>
              <td class="text-end">{{ $eur($sez['sumCons']) }}</td>
              <td class="text-end">{{ $pct($sez['sumPrev'], $sez['sumCons']) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    @endforeach
  @endforeach
</body>
</html>
