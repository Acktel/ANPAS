{{-- resources/views/template/distinta_imputazione_costi.blade.php --}}
@php
  $fmt = fn($v) => number_format((float)$v, 2, ',', '.');

  /** Titoli sezione (usa r['sezione_id'] se presente) */
  $sectionTitles = [
      2  => 'AUTOMEZZI ED ATTREZZATURE SANITARIE (VEDI TABELLE)',
      3  => 'ATTREZZATURA SANITARIA',
      4  => 'TELECOMUNICAZIONI',
      5  => 'COSTI DI GESTIONE DELLA STRUTTURA',
      6  => 'COSTI DEL PERSONALE DIPENDENTE',
      7  => 'MATERIALE DI CONSUMO',
      8  => 'COSTI AMMINISTRATIVI',
      9  => 'QUOTE DI AMMORTAMENTO',
      10 => 'BENI STRUMENTALI > €516',
      11 => 'ALTRI COSTI',
  ];

  // Totali globali delle prime 3 colonne numeriche
  $totBil = array_sum(array_map(fn($x)=>(float)($x['bilancio']??0), $righe));
  $totDir = array_sum(array_map(fn($x)=>(float)($x['diretta'] ??0),  $righe));
  $totInd = array_sum(array_map(fn($x)=>(float)($x['totale']  ??0),  $righe));
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Distinta imputazione costi – {{ $associazione }} – {{ $anno }}</title>
<style>
  *{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; }
  @page { size: A4 landscape; margin: 8mm; }
  html,body{ margin:0; padding:0; }
  h1{ font-size:13px; margin:0 0 4px; }
  h2{ font-size:10px; margin:0 0 6px; }

  table{ width:100%; border-collapse:collapse; table-layout:fixed; margin: 4px 0 10px; }
  thead{ display: table-header-group; }
  tr{ page-break-inside: avoid; }
  th,td{ border:1px solid #999; padding:2px 3px; font-size:8px; line-height:1.08; }
  thead th{ background:#f7f7f7; font-weight:700; text-align:center; }

  .num{ text-align:right; }

  .txt{
    text-align:left;
    white-space:normal;
    word-break:break-word;
    overflow:visible;
  }

  .sec-row td{ background:#d9d9d9; font-weight:700; text-transform:uppercase; }
  .tot{ font-weight:700; background:#f3f3f3; }

  .tiny{ font-size:7.2px; line-height:1.05; }
  .tight{ padding:1px 2px; font-size:7.6px; }

  /* larghezze: stringiamo per farci stare tutto */
  .col-desc{ width:32%; }
  .col-narrow{ width:6%; }
  .col-conv{ width:auto; } /* ogni coppia Diretti/Indiretti si stringe */
</style>
</head>
<body>

  <h1>Distinta imputazione costi</h1>
  <h2>{{ $associazione }} — Consuntivo {{ $anno }}</h2>

  @php $colspan = 4 + (count($convenzioni) * 2); @endphp

  <table>
    <colgroup>
      <col class="col-desc">
      <col class="col-narrow">
      <col class="col-narrow">
      <col class="col-narrow">
      @foreach($convenzioni as $conv)
        <col class="col-conv">
        <col class="col-conv">
      @endforeach
    </colgroup>

    <thead>
      <tr>
        <th rowspan="2" class="tight">DESCRIZIONE COSTO</th>
        <th rowspan="2" class="tight">BILANCIO</th>
        <th rowspan="2" class="tight">DIRETTI<br>(TOT.)</th>
        <th rowspan="2" class="tight">INDIRETTI<br>(TOT.)</th>
        @foreach($convenzioni as $conv)
          <th colspan="2" class="tight">{{ $conv }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach($convenzioni as $conv)
          <th class="tiny tight">Diretti</th>
          <th class="tiny tight">Indiretti</th>
        @endforeach
      </tr>
    </thead>

    <tbody>
      @php $currentSection = null; @endphp

      @foreach($righe as $r)
        @php
          $bil = (float)($r['bilancio'] ?? 0);
          $dir = (float)($r['diretta']  ?? 0);
          $ind = (float)($r['totale']   ?? 0);
          $secId = (int)($r['sezione_id'] ?? 0);
        @endphp

        @if($secId !== $currentSection)
          @php
            $currentSection = $secId;
            $title = $sectionTitles[$secId] ?? 'ALTRO';
          @endphp
          <tr class="sec-row"><td colspan="{{ $colspan }}">{{ $title }}</td></tr>
        @endif

        <tr>
          <td class="txt">{{ $r['voce'] ?? '' }}</td>
          <td class="num">{{ $fmt($bil) }}</td>
          <td class="num">{{ $fmt($dir) }}</td>
          <td class="num">{{ $fmt($ind) }}</td>

          @foreach($convenzioni as $conv)
            @php $cell = $r[$conv] ?? ['diretti'=>0,'indiretti'=>0]; @endphp
            <td class="num">{{ $fmt($cell['diretti']   ?? 0) }}</td>
            <td class="num">{{ $fmt($cell['indiretti'] ?? 0) }}</td>
          @endforeach
        </tr>
      @endforeach

      <tr class="tot">
        <td class="txt">TOTALE</td>
        <td class="num">{{ $fmt($totBil) }}</td>
        <td class="num">{{ $fmt($totDir) }}</td>
        <td class="num">{{ $fmt($totInd) }}</td>
        @foreach($convenzioni as $conv)
          @php
            $sumD = 0; $sumI = 0;
            foreach ($righe as $r) {
              $sumD += (float)($r[$conv]['diretti']   ?? 0);
              $sumI += (float)($r[$conv]['indiretti'] ?? 0);
            }
          @endphp
          <td class="num">{{ $fmt($sumD) }}</td>
          <td class="num">{{ $fmt($sumI) }}</td>
        @endforeach
      </tr>
    </tbody>
  </table>

</body>
</html>
