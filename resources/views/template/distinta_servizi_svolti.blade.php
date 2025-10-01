{{-- resources/views/template/distinta_servizi_svolti.blade.php --}}
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
  @page { size: A4 landscape; margin: 8mm; }
  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9px; color:#111; }

  h1 { margin:0 0 4px 0; font-size: 12px; }
  .small { font-size: 9px; color:#555; margin:0 0 6px 0; }

  table { width:100%; border-collapse:collapse; table-layout: fixed; }
  thead { display: table-header-group; }   /* ripeti header su nuove pagine */
  tfoot { display: table-row-group; }
  tr    { page-break-inside: avoid; }

  th, td { border:1px solid #999; padding: 2px 3px; font-size: 8px; line-height: 1.08; vertical-align: middle; }
  thead th { background:#f7f7f7; text-align:center; font-weight:700; }

  .text-end   { text-align:right; }
  .wrap       { white-space: normal; word-break: break-word; overflow: visible; }
  .row-total  { background:#f3f3f3; font-weight:700; }

  /* larghezze iniziali (strette) */
  .col-targa  { width: 9%; }
  .col-cod    { width: 9%; }
  .col-name   { width: 14%; }
  .col-tot    { width: 9%; }
  .col-n      { width: 7%; }
  .col-pct    { width: 5.5%; }

  .tbl { margin-top: 4px; }
</style>
</head>
<body>
  <h1>Tabella calcolo percentuali (numero servizi svolti)</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio: <strong>{{ $anno }}</strong>
  </div>

  <table class="tbl">
    <colgroup>
      <col class="col-targa">
      <col class="col-cod">
      <col class="col-name">
      <col class="col-tot">
      @foreach ($convenzioni as $c)
        <col class="col-n">
        <col class="col-pct">
      @endforeach
    </colgroup>

    <thead>
      <tr>
        <th rowspan="2">Targa</th>
        <th rowspan="2">Codice identificativo</th>
        <th rowspan="2">Totale n. servizi<br>nell’anno</th>
        @foreach ($convenzioni as $c)
          <th colspan="2">{{ $c->Convenzione }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach ($convenzioni as $c)
          <th>N. servizi</th>
          <th>%</th>
        @endforeach
      </tr>
    </thead>

    <tbody>
      @foreach ($rows as $r)
        @php $isTot = (int)($r['is_totale'] ?? 0) === -1; @endphp
        <tr class="{{ $isTot ? 'row-total' : '' }}">
          <td class="wrap">{{ $isTot ? '' : ($r['Targa'] ?? '') }}</td>
          <td class="wrap">{{ $isTot ? '' : ($r['CodiceIdentificativo'] ?? '') }}</td>
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
