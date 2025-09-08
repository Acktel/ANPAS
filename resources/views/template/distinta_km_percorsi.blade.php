{{-- Distinta KM percorsi e percentuali per convenzione – PDF --}}
@php
  /** INPUT attesi:
   * $anno
   * $associazione (obj con ->Associazione)
   * $convenzioni (Collection con ->idConvenzione, ->Convenzione)
   * $rows (array di righe):
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
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
    h1,h2,h3 { margin: 0 0 6px 0; }
    .header { margin-bottom: 10px; }
    .small { font-size: 11px; color:#555; }

    table { width: 100%; border-collapse: collapse; }
    .tbl { table-layout: fixed; }
    .tbl th, .tbl td { border: 1px solid #ccc; padding: 5px 6px; vertical-align: middle; }
    .tbl th { background: #f8fafc; text-align: center; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .row-total { background: #f6f6f6; font-weight: bold; }

    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }

    /* Larghezze colonna anagrafiche */
    .col-targa   { width: 12%; }
    .col-codice  { width: 9%; }
    .col-nome    { width: 15%; }

    /* Larghezze dati */
    .col-tot     { width: 15%; }
    .col-km      { width: 15%; }
    .col-pct     { width: 12%; }

    /* Evita overflow dei testi lunghi in anagrafiche */
    .wrap { word-wrap: break-word; overflow-wrap: anywhere; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Tabella calcolo percentuali chilometriche</h1>
    <div class="small">
      Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong> —
      Esercizio finanziario: <strong>{{ $anno }}</strong>
    </div>
  </div>

  <table class="tbl">
    <thead>
      <tr>
        <th class="col-targa"  rowspan="2">Targa</th>
        <th class="col-codice" rowspan="2">Codice identificativo</th>
        <th class="col-nome"   rowspan="2">Automezzo</th>
        <th class="col-tot"    rowspan="2">KM totali<br>nell’anno</th>
        @foreach ($convenzioni as $conv)
          <th colspan="2">{{ $conv->Convenzione }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach ($convenzioni as $conv)
          <th class="col-km">KM percorsi</th>
          <th class="col-pct">%</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($rows as $r)
        <tr class="{{ ($r['is_totale'] ?? 0) === -1 ? 'row-total' : '' }}">
          {{-- Anagrafiche: vuote per la riga totale --}}
          <td class="wrap">{{ ($r['is_totale'] ?? 0) === -1 ? '' : ($r['Targa'] ?? '') }}</td>
          <td class="wrap">{{ ($r['is_totale'] ?? 0) === -1 ? '' : ($r['CodiceIdentificativo'] ?? '') }}</td>
          <td class="wrap">{{ ($r['is_totale'] ?? 0) === -1 ? 'TOTALE' : ($r['Automezzo'] ?? '') }}</td>

          {{-- Totale per riga --}}
          <td class="text-end">{{ $num0($r['Totale'] ?? 0) }}</td>

          {{-- Coppie KM/% per ogni convenzione --}}
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
