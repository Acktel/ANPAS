{{-- Volontari + Servizio Civile Nazionale – PDF --}}
@php
  $num0 = fn($v) => number_format((float)$v, 0, ',', '.');
  $pct2 = fn($v) => number_format((float)$v, 2, ',', '.') . '%';
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Volontari & SCN – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
    h1,h2 { margin:0 0 6px 0; }
    .small { color:#444; margin-bottom:8px; }
    table { width:100%; border-collapse:collapse; table-layout: fixed; margin-bottom:18px; }
    th, td { border:1px solid #ccc; padding:5px 6px; vertical-align: middle; }
    th { background:#f8fafc; text-align:center; }
    .text-end { text-align:right; }
    .row-total { background:#fff7c6; font-weight:600; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .col-label { width: 26%; }
    .col-tot   { width: 10%; }
    .col-ore   { width: 8%; }
    .col-pct   { width: 6.5%; }
    .section { border-top: 6px solid #00c0d1; padding-top:8px; margin-top:10px; }
    .band { background:#ff36d8; height:8px; margin:8px 0 6px; }
  </style>
</head>
<body>
  {{-- ====== VOLONTARI ====== --}}
  <div class="section">
    <h1>TABELLA DI CALCOLO PER LA RIPARTIZIONE DEI COSTI DA PERSONALE VOLONTARIO</h1>
    <div class="small">
      Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> — Esercizio finanziario: <strong>{{ $anno }}</strong>
    </div>
    <div class="band"></div>
    <table>
      <thead>
        <tr>
          <th class="col-label" rowspan="2">{{ $volontari['label'] }}</th>
          <th class="col-tot"   rowspan="2">ORE DI SERVIZIO</th>
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
        <tr class="row-total">
          <td></td>
          <td class="text-end">{{ $num0($volontari['OreTotali'] ?? 0) }}</td>
          @foreach($convenzioni as $c)
            @php $k = 'c'.$c->idConvenzione; @endphp
            <td class="text-end">{{ $num0($volontari[$k.'_ore'] ?? 0) }}</td>
            <td class="text-end">{{ $pct2($volontari[$k.'_percent'] ?? 0) }}</td>
          @endforeach
        </tr>
      </tbody>
    </table>
  </div>

  {{-- ====== SERVIZIO CIVILE NAZIONALE ====== --}}
  <div class="section">
    <h1>TABELLA DI CALCOLO PER LA RIPARTIZIONE DEI COSTI SERVIZIO CIVILE NAZIONALE</h1>
    <div class="small">
      Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> — Esercizio finanziario: <strong>{{ $anno }}</strong>
    </div>
    <div class="band"></div>
    <table>
      <thead>
        <tr>
          <th class="col-label" rowspan="2">{{ $scn['label'] }}</th>
          <th class="col-tot"   rowspan="2">ORE DI SERVIZIO</th>
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
        <tr class="row-total">
          <td></td>
          <td class="text-end">{{ $num0($scn['OreTotali'] ?? 0) }}</td>
          @foreach($convenzioni as $c)
            @php $k = 'c'.$c->idConvenzione; @endphp
            <td class="text-end">{{ $num0($scn[$k.'_ore'] ?? 0) }}</td>
            <td class="text-end">{{ $pct2($scn[$k.'_percent'] ?? 0) }}</td>
          @endforeach
        </tr>
      </tbody>
    </table>
  </div>
</body>
</html>
