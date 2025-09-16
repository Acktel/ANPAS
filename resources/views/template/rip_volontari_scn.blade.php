{{-- Volontari + Servizio Civile Nazionale – PDF (compatto) --}}
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
    @page { size: A4 landscape; margin: 8mm; } /* margini più stretti */
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9px; color:#111; } /* font più piccolo */

    h1,h2 { margin:0 0 4px 0; font-size: 11px; }
    .small { color:#444; margin:0 0 6px 0; font-size: 8.2px; }
    .band { height:6px; margin:6px 0 4px; background:#ff36d8; } /* più sottile */

    table { width:100%; border-collapse:collapse; table-layout: fixed; margin:0 0 10px 0; }
    thead { display: table-header-group; }  /* ripete head a ogni pagina */
    tr { page-break-inside: avoid; }

    th, td { border:1px solid #cfc; border-color:#ccc; padding: 2px 3px; vertical-align: middle; line-height: 1.15; }
    th { background:#f8fafc; text-align:center; font-weight:700; font-size: 8.2px; }
    td { font-size: 8.2px; }

    .text-end { text-align:right; }
    .row-total { background:#fff7c6; font-weight:600; }

    /* colonne più strette per far entrare tutto */
    .col-label { width: 24%; }
    .col-tot   { width: 9%; }
    .col-ore   { width: 7%; }
    .col-pct   { width: 6%; }

    /* compatta le sezioni */
    .section { border-top: 4px solid #00c0d1; padding-top:6px; margin-top:8px; }
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
            <th class="col-ore">ORE</th>
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
            <th class="col-ore">ORE</th>
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
