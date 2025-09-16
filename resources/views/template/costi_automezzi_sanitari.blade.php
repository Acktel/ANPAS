{{-- resources/views/template/costi_automezzi_sanitari.blade.php --}}
@php
  $fmt = fn($n) => number_format((float)$n, 2, ',', '.');
@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Distinta costi automezzi/attrezzatura sanitaria</title>
  <style>
    @page { size: A4 landscape; margin: 8mm; }
    html, body { margin:0; padding:0; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 8.3px; color:#111; }

    .head {
      border:2px solid #00c2c2; background:#e7ffff;
      padding:4px 6px; text-align:center; font-weight:700; font-size:11px; text-transform:uppercase;
    }
    .sub { text-align:center; margin:3px 0 8px 0; font-size:9px; }

    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    thead { display: table-header-group; }   /* ripete header se il tavolone va su più pagine */
    th, td { border:1px solid #999; padding:2px 3px; vertical-align:middle; }
    thead th { background:#f8fafc; font-weight:700; text-align:center; font-size:7.6px; line-height:1.05; }
    tbody td { text-align:right; font-size:8.1px; }
    tbody td.txt { text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tot { font-weight:700; background:#f3f3f3; }

    /* colgroup: due colonne testuali + 15 numeriche strette */
    .col-targa  { width: 8.5%; }
    .col-cod    { width: 9.5%; }
    .col-num    { width: auto; }  /* le restanti si dividono lo spazio, restando strette grazie al padding ridotto */

    /* abbreviazioni di header ben leggibili anche piccole */
    .h-2line { white-space:normal; }
  </style>
</head>
<body>

  <div class="head">
    DISTINTA RILEVAZIONE ANALITICA COSTI AUTOMEZZI ED ATTREZZATURA SANITARIA
  </div>
  <div class="sub">
    <strong>{{ $associazione->Associazione ?? '' }}</strong> — Consuntivo {{ $anno }}
  </div>

  <table>
    <colgroup>
      <col class="col-targa">
      <col class="col-cod">
      {{-- 15 colonne numeriche --}}
      @for ($i=0; $i<15; $i++)
        <col class="col-num">
      @endfor
    </colgroup>

    <thead>
      <tr>
        <th>TARGA</th>
        <th>COD.<br>IDENTIFICATIVO</th>

        <th class="h-2line">LEASING/<br>NOLEGGIO L.T.</th>
        <th>ASSICURAZIONE</th>
        <th class="h-2line">MANUTENZIONE<br>ORDINARIA</th>
        <th class="h-2line">MANUTENZIONE<br>STRAORD.</th>
        <th class="h-2line">RIMBORSI<br>ASSICURAZIONI</th>
        <th class="h-2line">PULIZIA E<br>DISINFEZIONE</th>
        <th>CARBURANTI</th>
        <th>RIMBORSI UTF</th>
        <th>ADDITIVI</th>
        <th class="h-2line">INTERESSI<br>PASSIVI</th>
        <th class="h-2line">ALTRI<br>COSTI MEZZI</th>
        <th class="h-2line">MANUT.<br>ATTREZZ. SAN.</th>
        <th class="h-2line">LEASING<br>ATTREZZ. SAN.</th>
        <th class="h-2line">AMMORT.<br>AUTOMEZZI</th>
        <th class="h-2line">AMMORT.<br>ATTREZZ. SAN.</th>
      </tr>
    </thead>

    <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="txt">{{ $r['Targa'] }}</td>
          <td class="txt">{{ $r['Codice'] }}</td>

          <td>{{ $fmt($r['LeasingNoleggio']) }}</td>
          <td>{{ $fmt($r['Assicurazione']) }}</td>
          <td>{{ $fmt($r['ManutenzioneOrdinaria']) }}</td>
          <td>{{ $fmt($r['ManutenzioneStraordinaria']) }}</td>
          <td>{{ $fmt($r['RimborsiAssicurazione']) }}</td>
          <td>{{ $fmt($r['PuliziaDisinfezione']) }}</td>
          <td>{{ $fmt($r['Carburanti']) }}</td>
          <td>{{ $fmt($r['RimborsiUTF']) }}</td>
          <td>{{ $fmt($r['Additivi']) }}</td>
          <td>{{ $fmt($r['InteressiPassivi']) }}</td>
          <td>{{ $fmt($r['AltriCostiMezzi']) }}</td>
          <td>{{ $fmt($r['ManutenzioneSanitaria']) }}</td>
          <td>{{ $fmt($r['LeasingSanitaria']) }}</td>
          <td>{{ $fmt($r['AmmortamentoMezzi']) }}</td>
          <td>{{ $fmt($r['AmmortamentoSanitaria']) }}</td>
        </tr>
      @empty
        <tr>
          <td class="txt" colspan="17">Nessun automezzo incluso nel riparto per l’anno selezionato.</td>
        </tr>
      @endforelse

      {{-- riga totale --}}
      <tr class="tot">
        <td class="txt">{{ $tot['Targa'] }}</td>
        <td></td>
        <td>{{ $fmt($tot['LeasingNoleggio']) }}</td>
        <td>{{ $fmt($tot['Assicurazione']) }}</td>
        <td>{{ $fmt($tot['ManutenzioneOrdinaria']) }}</td>
        <td>{{ $fmt($tot['ManutenzioneStraordinaria']) }}</td>
        <td>{{ $fmt($tot['RimborsiAssicurazione']) }}</td>
        <td>{{ $fmt($tot['PuliziaDisinfezione']) }}</td>
        <td>{{ $fmt($tot['Carburanti']) }}</td>
        <td>{{ $fmt($tot['RimborsiUTF']) }}</td>
        <td>{{ $fmt($tot['Additivi']) }}</td>
        <td>{{ $fmt($tot['InteressiPassivi']) }}</td>
        <td>{{ $fmt($tot['AltriCostiMezzi']) }}</td>
        <td>{{ $fmt($tot['ManutenzioneSanitaria']) }}</td>
        <td>{{ $fmt($tot['LeasingSanitaria']) }}</td>
        <td>{{ $fmt($tot['AmmortamentoMezzi']) }}</td>
        <td>{{ $fmt($tot['AmmortamentoSanitaria']) }}</td>
      </tr>
    </tbody>
  </table>
</body>
</html>
