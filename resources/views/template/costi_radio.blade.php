{{-- resources/views/template/costi_radio.blade.php --}}
@php
  $rows = collect($rows);
  $num2 = fn($v) => number_format((float)$v, 2, ',', '.');
@endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Distinta rilevazione analitica costi radio – {{ $anno }}</title>
<style>
  @page { size: A4 landscape; margin: 8mm; }
  html,body{ margin:0; padding:0; }
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:8.6px; color:#111; }

  h3{ margin:0 0 4px 0; font-size:11px; }
  .sub{ margin:0 0 8px 0; font-size:9px; color:#555; }

  table{ width:100%; border-collapse:collapse; table-layout:fixed; }
  thead{ display:table-header-group; }
  tr{ page-break-inside:avoid; }
  th,td{ border:1px solid #bbb; padding:2px 3px; line-height:1.12; }
  th{ background:#eef7ff; font-size:7.6px; text-align:center; }
  td.num{ text-align:right; white-space:nowrap; }
  td.txt{ text-align:left; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

  .tot td{ background:#f3f3f3; font-weight:700; }
  .col-targa{ width:11%; }
  .col-cod{ width:11%; }
</style>
</head>
<body>

  <h3>DISTINTA RILEVAZIONE ANALITICA COSTI RADIO</h3>
  <div class="sub">{{ $associazione->Associazione ?? '' }} — Consuntivo {{ $anno }}</div>

  <table>
    <colgroup>
      <col class="col-targa"><col class="col-cod">
      <col><col><col><col>
    </colgroup>
    <thead>
      <tr>
        <th>TARGA</th>
        <th>CODICE ID</th>
        <th>MANUTENZIONE APPARATI RADIO</th>
        <th>MONTAGGIO/SMONTAGGIO RADIO 118</th>
        <th>LOCAZIONE PONTE RADIO</th>
        <th>AMMORTAMENTO IMPIANTI RADIO</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td class="txt">{{ $r['Targa'] }}</td>
          <td class="txt">{{ $r['Codice'] }}</td>
          <td class="num">{{ $num2($r['ManutenzioneApparatiRadio'] ?? 0) }}</td>
          <td class="num">{{ $num2($r['MontaggioSmontaggioRadio118'] ?? 0) }}</td>
          <td class="num">{{ $num2($r['LocazionePonteRadio'] ?? 0) }}</td>
          <td class="num">{{ $num2($r['AmmortamentoImpiantiRadio'] ?? 0) }}</td>
        </tr>
      @endforeach
      <tr class="tot">
        <td colspan="2">TOTALE A BILANCIO</td>
        <td class="num">{{ $num2($tot['ManutenzioneApparatiRadio'] ?? 0) }}</td>
        <td class="num">{{ $num2($tot['MontaggioSmontaggioRadio118'] ?? 0) }}</td>
        <td class="num">{{ $num2($tot['LocazionePonteRadio'] ?? 0) }}</td>
        <td class="num">{{ $num2($tot['AmmortamentoImpiantiRadio'] ?? 0) }}</td>
      </tr>
    </tbody>
  </table>

</body>
</html>
