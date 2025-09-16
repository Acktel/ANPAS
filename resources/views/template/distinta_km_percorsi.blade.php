{{-- resources/views/template/distinta_km_percorsi.blade.php --}}
@php
  /** INPUT attesi:
   * $anno
   * $associazione (obj con ->Associazione)
   * $convenzioni (Collection con ->idConvenzione, ->Convenzione)
   * $rows (array):
   *   [
   *     'idAutomezzo' => int|null,
   *     'Targa' => string, 'CodiceIdentificativo' => string, 'Automezzo' => string,
   *     'Totale' => float, 'is_totale' => 0|-1,
   *     "c<ID>_km" => float, "c<ID>_percent" => float, ...
   *   ]
   */
  $num0 = fn($v) => number_format((float)$v, 0, ',', '.');          // km senza decimali
  $pct2 = fn($v) => number_format((float)$v, 2, ',', '.') . '%';    // percentuale 2 decimali
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Distinta KM percorsi – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
  <style>
    @page { size: A4 landscape; margin: 8mm; }
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9px; color:#111; }

    h1 { margin: 0 0 4px 0; font-size: 12px; }
    .small { font-size: 9px; color:#555; margin: 0 0 6px 0; }

    /* Tabella unica, molto compressa */
    table { width:100%; border-collapse:collapse; table-layout: fixed; }
    thead { display: table-header-group; }   /* ripeti header su nuove pagine */
    tfoot { display: table-row-group; }
    tr    { page-break-inside: avoid; }

    th, td { border:1px solid #999; padding: 2px 3px; font-size: 8px; line-height: 1.08; }
    thead th { background:#f7f7f7; text-align:center; font-weight:700; }

    .text-end { text-align:right; }
    .text-center { text-align:center; }
    .wrap { white-space: normal; word-break: break-word; overflow: visible; }
    .row-total { background:#f3f3f3; font-weight:700; }

    /* Larghezze iniziali: anagrafiche strette, dati stretti.
       Le coppie KM/% per convenzione si adattano automaticamente. */
    .col-targa   { width: 9%; }
    .col-codice  { width: 9%; }
    .col-nome    { width: 14%; }
    .col-tot     { width: 9%; }
    .col-km      { width: 7%; }
    .col-pct     { width: 5.5%; }

    /* Evita che una nuova tabella inizi a fondo pagina: nessuna tabella nuova qui,
       ma assicuriamo un piccolo margine sopra per non “attaccarla” al fondo. */
    .tbl { margin-top: 4px; }
  </style>
</head>
<body>
  <h1>Tabella calcolo percentuali chilometriche</h1>
  <div class="small">
    Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
    Esercizio: <strong>{{ $anno }}</strong>
  </div>

  <table class="tbl">
    <colgroup>
      <col class="col-targa">
      <col class="col-codice">
      <col class="col-nome">
      <col class="col-tot">
      @foreach ($convenzioni as $conv)
        <col class="col-km">
        <col class="col-pct">
      @endforeach
    </colgroup>

    <thead>
      <tr>
        <th rowspan="2">Targa</th>
        <th rowspan="2">Codice identificativo</th>
        <th rowspan="2">KM totali<br>nell’anno</th>
        @foreach ($convenzioni as $conv)
          <th colspan="2">{{ $conv->Convenzione }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach ($convenzioni as $conv)
          <th>KM</th>
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

          @foreach ($convenzioni as $conv)
            @php $k = 'c'.$conv->idConvenzione; @endphp
            <td class="text-end">{{ $num0($r[$k.'_km'] ?? 0) }}</td>
            <td class="text-end">{{ $pct2($r[$k.'_percent'] ?? 0) }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
