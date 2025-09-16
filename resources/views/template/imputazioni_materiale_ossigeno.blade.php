{{-- resources/views/template/imputazioni_materiale_ossigeno.blade.php --}}
@php
  $fmt = fn($v) => number_format((float)$v, 2, ',', '.');
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Imputazioni materiale e ossigeno – {{ $associazione->Associazione ?? '' }} – {{ $anno }}</title>
  <style>
    @page { size: A4 portrait; margin: 8mm; }
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9px; color:#111; }

    h3 { margin: 0 0 4px 0; font-size: 12px; text-transform: uppercase; page-break-after: avoid; }
    .sub { margin: 0 0 6px 0; font-size: 9px; color: #555; }

    .section {
      margin-top: 8px;
      page-break-inside: avoid;           /* evita che inizi a fondo pagina */
      break-inside: avoid;
    }

    table { width:100%; border-collapse: collapse; table-layout: fixed; }
    thead { display: table-header-group; } /* ripeti l'intestazione se spezza */
    tfoot { display: table-row-group; }
    tr    { page-break-inside: avoid; }

    th, td { border:1px solid #999; padding: 3px 4px; font-size: 8px; line-height: 1.08; vertical-align: middle; }
    thead th { background:#f7f7f7; text-align:center; font-weight:700; }

    .right  { text-align: right; }
    .center { text-align: center; }
    .wrap   { white-space: normal; word-break: break-word; overflow: visible; }
    .nowrap { white-space: nowrap; }

    .tot td { background:#f3f3f3; font-weight:700; }

    /* larghezze colonne (strette) */
    .col-targa { width: 14%; }
    .col-n     { width: 18%; }
    .col-pct   { width: 18%; }
    .col-imp   { width: 14%; }
  </style>
</head>
<body>

  {{-- ====== BLOCCO: MATERIALI ====== --}}
  <div class="section">
    <h3>{{ $mat['titolo'] }}</h3>
    <div class="sub">{{ $associazione->Associazione ?? '' }} — Consuntivo {{ $anno }}</div>

    <table>
      <colgroup>
        <col class="col-targa">
        <col class="col-n">
        <col class="col-pct">
        <col class="col-imp">
      </colgroup>
      <thead>
        <tr>
          <th>Targa</th>
          <th>N. servizi singolo automezzo</th>
          <th>Percentuale di riparto</th>
          <th>Importo</th>
        </tr>
      </thead>
      <tbody>
        @foreach($mat['rows'] as $r)
          <tr class="{{ !empty($r['is_totale']) ? 'tot' : '' }}">
            <td class="nowrap">{{ $r['Targa'] }}</td>
            <td class="center">{{ $r['n_servizi'] }}</td>
            <td class="center">{{ is_numeric($r['percentuale'] ?? null) ? number_format($r['percentuale'], 2, ',', '.') . '%' : ($r['percentuale'] ?? '') }}</td>
            <td class="right">{{ $fmt($r['importo']) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td class="right" colspan="3"><strong>Totale a bilancio</strong></td>
          <td class="right"><strong>{{ $fmt($mat['totale_bilancio']) }}</strong></td>
        </tr>
      </tfoot>
    </table>
  </div>

  {{-- ====== BLOCCO: OSSIGENO ====== --}}
  <div class="section">
    <h3>{{ $oss['titolo'] }}</h3>
    <div class="sub">{{ $associazione->Associazione ?? '' }} — Consuntivo {{ $anno }}</div>

    <table>
      <colgroup>
        <col class="col-targa">
        <col class="col-n">
        <col class="col-pct">
        <col class="col-imp">
      </colgroup>
      <thead>
        <tr>
          <th>Targa</th>
          <th>N. servizi singolo automezzo</th>
          <th>Percentuale di riparto</th>
          <th>Importo</th>
        </tr>
      </thead>
      <tbody>
        @foreach($oss['rows'] as $r)
          <tr class="{{ !empty($r['is_totale']) ? 'tot' : '' }}">
            <td class="nowrap">{{ $r['Targa'] }}</td>
            <td class="center">{{ $r['n_servizi'] }}</td>
            <td class="center">{{ is_numeric($r['percentuale'] ?? null) ? number_format($r['percentuale'], 2, ',', '.') . '%' : ($r['percentuale'] ?? '') }}</td>
            <td class="right">{{ $fmt($r['importo']) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td class="right" colspan="3"><strong>Totale a bilancio</strong></td>
          <td class="right"><strong>{{ $fmt($oss['totale_bilancio']) }}</strong></td>
        </tr>
      </tfoot>
    </table>
  </div>

</body>
</html>
