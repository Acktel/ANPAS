{{-- Distinta SERVIZI SVOLTI e percentuali per convenzione – PDF --}}
@php
  /* INPUT:
     $anno, $associazione->Associazione, $convenzioni (idConvenzione, Convenzione),
     $rows: ['Targa','CodiceIdentificativo','Automezzo','Totale','is_totale', "c<ID>_n","c<ID>_percent"...]
  */
  $num0 = fn($v) => number_format((float)$v, 0, ',', '.');
  $pct2 = fn($v) => number_format((float)$v, 2, ',', '.') . '%';
@endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Servizi svolti – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
<style>
  @page { size: A4 landscape; margin: 12mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  h1 { margin:0 0 6px 0; }
  .small { font-size:11px; color:#555; margin-bottom:8px; }

  table { width:100%; border-collapse:collapse; table-layout: fixed; }
  th, td { border:1px solid #ccc; padding:5px 6px; vertical-align:middle; }
  th { background:#f8fafc; text-align:center; }
  .text-end { text-align:right; }
  .row-total { background:#f6f6f6; font-weight:bold; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }

  .col-targa { width: 12%; }
  .col-cod   { width: 12%; }
  .col-name  { width: 15%; }
  .col-tot   { width: 10%; }
  .col-n     { width: 9%; }
  .col-pct   { width: 12%; }
  .wrap { word-wrap:break-word; overflow-wrap:anywhere; }
</style>
</head>
<body>
  <h1>Tabella calcolo percentuali (numero servizi svolti)</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio finanziario: <strong>{{ $anno }}</strong>
  </div>

  <table>
    <thead>
      <tr>
        <th class="col-targa" rowspan="2">Targa</th>
        <th class="col-cod"   rowspan="2">Codice identificativo</th>
        <th class="col-name"  rowspan="2">Automezzo</th>
        <th class="col-tot"   rowspan="2">Totale n. servizi<br>nell’anno</th>
        @foreach ($convenzioni as $c)
          <th colspan="2">{{ $c->Convenzione }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach ($convenzioni as $c)
          <th class="col-n">N. servizi</th>
          <th class="col-pct">%</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($rows as $r)
        <tr class="{{ ($r['is_totale'] ?? 0) === -1 ? 'row-total' : '' }}">
          <td class="wrap">{{ ($r['is_totale'] ?? 0) === -1 ? '' : ($r['Targa'] ?? '') }}</td>
          <td class="wrap">{{ ($r['is_totale'] ?? 0) === -1 ? '' : ($r['CodiceIdentificativo'] ?? '') }}</td>
          <td class="wrap">{{ ($r['is_totale'] ?? 0) === -1 ? 'TOTALE' : ($r['Automezzo'] ?? '') }}</td>
          <td class="text-end">{{ $num0($r['Totale'] ?? 0) }}</td>

          @foreach ($convenzioni as $c)
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
