{{-- resources/views/template/costi_personale.blade.php --}}
@php
  $num2 = fn($v) => number_format((float)$v, 2, ',', '.');

  /**
   * Normalizza una riga (array o stdClass) garantendo tutte le chiavi richieste.
   */
  $safeRow = function ($row) {
    $r = is_array($row) ? $row : (array) $row;
    return array_replace([
      'Dipendente'         => '',
      'Retribuzioni'       => 0.0,
      'OneriSocialiInps'   => 0.0,
      'OneriSocialiInail'  => 0.0,
      'TFR'                => 0.0,
      'Consulenze'         => 0.0,
      'Totale'             => 0.0,
      'conv'               => [],
      'is_total'           => false,
    ], $r);
  };

  // Normalizza il totale AB per evitare chiavi mancanti
  $abTot = $safeRow($ab['tot'] ?? []);
@endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Costi personale – {{ $anno }}</title>
<style>
  @page { size: A4 landscape; margin: 8mm; }
  html,body{ margin:0; padding:0; }
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:8.6px; color:#111; }
  h2{ margin:0 0 6px 0; font-size:11px; }
  h3{ margin:6px 0 4px 0; page-break-after:avoid; font-size:10px; }
  .small{ color:#555; margin-bottom:6px; }

  table{ width:100%; border-collapse:collapse; table-layout:fixed; }
  thead{ display:table-header-group; }    /* header ripetuto */
  tr{ page-break-inside:avoid; }
  th,td{ border:1px solid #bbb; padding:2px 3px; line-height:1.12; }
  th{ background:#f8fafc; text-align:center; font-size:7.6px; }
  td.right{ text-align:right; }

  .total{ font-weight:700; background:#f3f3f3; }

  /* sezioni: non iniziare “in fondo” pagina */
  .section{
    margin-top:10px;
    page-break-inside:avoid;
    break-inside:avoid;
  }

  /* colonne AB */
  .w-name{ width:19%; }
  .w-num { width:8%; }
  .w-num-sm{ width:6%; }

  /* blocchi semplici */
  .simp-name{ width:34%; }
  .simp-num { width:13%; }
  .simp-num-sm{ width:10%; }
  .simp-total{ width:17%; }
</style>
</head>
<body>

  <h2>{{ $associazione->Associazione ?? '' }} — Distinte costi personale — Consuntivo {{ $anno }}</h2>
  <div class="small">Associazione: <strong>{{ $associazione->Associazione ?? '' }}</strong></div>

  {{-- ====================== AUTISTI & BARELLIERI ====================== --}}
  <div class="section">
    <h3>Autisti e Barellieri</h3>
    <table>
      <colgroup>
        <col class="w-name">
        <col class="w-num"><col class="w-num"><col class="w-num-sm"><col class="w-num"><col class="w-num"><col class="w-num">
        @foreach($convenzioni as $c) <col> @endforeach
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2">Cognome e Nome</th>
          <th rowspan="2">Retribuzioni</th>
          <th rowspan="2">Oneri Sociali INPS</th>
          <th rowspan="2">Oneri Sociali INAIL</th>
          <th rowspan="2">TFR</th>
          <th rowspan="2">Consulenze</th>
          <th rowspan="2">Totale</th>
          <th colspan="{{ $convenzioni->count() }}">Ripartizione per convenzione (importi)</th>
        </tr>
        <tr>
          @foreach($convenzioni as $c)
            <th>{{ $c->Convenzione }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($ab['rows'] as $r0)
          @php($r = $safeRow($r0))
          <tr>
            <td>{{ $r['Dipendente'] }}</td>
            <td class="right">{{ $num2($r['Retribuzioni']) }}</td>
            <td class="right">{{ $num2($r['OneriSocialiInps']) }}</td>
            <td class="right">{{ $num2($r['OneriSocialiInail']) }}</td>
            <td class="right">{{ $num2($r['TFR']) }}</td>
            <td class="right">{{ $num2($r['Consulenze']) }}</td>
            <td class="right">{{ $num2($r['Totale']) }}</td>
            @foreach($convenzioni as $c)
              <td class="right">{{ $num2($r['conv'][$c->idConvenzione] ?? 0) }}</td>
            @endforeach
          </tr>
        @endforeach
        <tr class="total">
          <td>{{ $abTot['Dipendente'] }}</td>
          <td class="right">{{ $num2($abTot['Retribuzioni']) }}</td>
          <td class="right">{{ $num2($abTot['OneriSocialiInps']) }}</td>
          <td class="right">{{ $num2($abTot['OneriSocialiInail']) }}</td>
          <td class="right">{{ $num2($abTot['TFR']) }}</td>
          <td class="right">{{ $num2($abTot['Consulenze']) }}</td>
          <td class="right">{{ $num2($abTot['Totale']) }}</td>
          @foreach($convenzioni as $c)
            <td class="right">{{ $num2($abTot['conv'][$c->idConvenzione] ?? 0) }}</td>
          @endforeach
        </tr>
      </tbody>
    </table>
  </div>

  {{-- ====================== BLOCCHI SEMPLICI ====================== --}}
  @foreach($semplici as $blocco)
    <div class="section">
      <h3>{{ $blocco['titolo'] }}</h3>
      <table>
        <colgroup>
          <col class="simp-name">
          <col class="simp-num"><col class="simp-num"><col class="simp-num-sm"><col class="simp-num"><col class="simp-num"><col class="simp-total">
        </colgroup>
        <thead>
          <tr>
            <th>Cognome e Nome</th>
            <th>Retribuzioni</th>
            <th>Oneri Sociali INPS</th>
            <th>Oneri Sociali INAIL</th>
            <th>TFR</th>
            <th>Consulenze</th>
            <th>Totale</th>
          </tr>
        </thead>
        <tbody>
          @foreach($blocco['rows'] as $r0)
            @php($r = $safeRow($r0))
            <tr class="{{ !empty($r['is_total']) ? 'total' : '' }}">
              <td>{{ $r['Dipendente'] }}</td>
              <td class="right">{{ $num2($r['Retribuzioni']) }}</td>
              <td class="right">{{ $num2($r['OneriSocialiInps']) }}</td>
              <td class="right">{{ $num2($r['OneriSocialiInail']) }}</td>
              <td class="right">{{ $num2($r['TFR']) }}</td>
              <td class="right">{{ $num2($r['Consulenze']) }}</td>
              <td class="right">{{ $num2($r['Totale']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endforeach

</body>
</html>
