{{-- resources/views/template/pdf_riepiloghi_dati_costi.blade.php --}}
@php
  $fmt = fn($v) => number_format((float)$v, 2, ',', '.');

  // Cast sicuro
  $num = function($v) {
    if ($v === '-' || $v === null || $v === '') return 0.0;
    return (float) $v;
  };

  /**
   * Trasforma $p['sezioni_costi'] in una lista piatta di item:
   *  - ['type'=>'header', 'label'=>string]
   *  - ['type'=>'row',    'desc'=>string, 'prev'=>float, 'cons'=>float]
   *  - ['type'=>'total',  'label'=>string, 'prev'=>float, 'cons'=>float]
   */
  $flattenCostSections = function(array $sezioni, callable $num) {
    $out = [];
    foreach ($sezioni as $sec) {
      $out[] = ['type'=>'header', 'label'=>$sec['label'] ?? ''];
      foreach ($sec['righe'] as $r) {
        $out[] = [
          'type' => 'row',
          'desc' => $r->descrizione ?? '',
          'prev' => $num($r->preventivo ?? 0),
          'cons' => $num($r->consuntivo ?? 0),
        ];
      }
      if (!empty($sec['totali'])) {
        $out[] = [
          'type' => 'total',
          'label'=> 'TOTALE '.$sec['label'],
          'prev' => (float)($sec['totali']['preventivo'] ?? 0),
          'cons' => (float)($sec['totali']['consuntivo'] ?? 0),
        ];
      }
    }
    return $out;
  };

  /**
   * Chunk "furbo": massimo 2 pagine. La prima prende fino a $maxPerPage item,
   * il resto (anche se lungo) sta tutto nella seconda.
   */
  $chunkCostsInTwoPages = function(array $flat, int $maxPerPage = 42) {
    $first = array_slice($flat, 0, $maxPerPage);
    $second = array_slice($flat, $maxPerPage);
    return [$first, $second];
  };
@endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Riepilogo dati e costi – {{ $associazione }} – {{ $anno }}</title>
<style>
  @page { size: A4 landscape; margin: 8mm; }  /* margine più stretto */
  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9.2px; color:#111; } /* più fitto */

  h1 { margin: 0 0 6px 0; font-size: 12px; }
  h2 { margin: 0 0 8px 0; font-size: 10px; color:#444; }
  h3 { margin: 8px 0 4px 0; font-size: 10px; page-break-after: avoid; }

  .section { margin-top: 8px; page-break-inside: avoid; break-inside: avoid; }

  .badge {
    display:inline-block; padding: 2px 6px; border-radius: 4px;
    background:#e8f4ff; border:1px solid #bcd9ff; font-weight:600;
  }

  table { width:100%; border-collapse:collapse; table-layout: fixed; }
  thead { display: table-header-group; }   /* ripeti intestazioni su pagina nuova */
  tr { page-break-inside: avoid; }

  th, td { border:1px solid #999; padding: 3px 4px; vertical-align: middle; }
  thead th { background:#f2f6ff; text-align:center; font-weight:700; }

  .text-end { text-align: right; }
  .wrap { white-space: normal; word-break: break-word; }

  .tot-row td { background:#efefef; font-weight:700; }
  .sec-head td { background:#e9ecef; font-weight:700; }

  /* Larghezze colonne */
  .col-desc { width: 60%; }
  .col-num  { width: 20%; }

  /* Tabella "compact" per far stare Riepilogo dati in UNA pagina */
  .compact { font-size: 9.0px; }
  .compact th, .compact td { padding: 2.5px 3px; }

  /* Nessun page-break prima del primo blocco; dal secondo in poi sì */
  .block { margin-top: 2px; }
  .block + .block { page-break-before: always; }
</style>
</head>
<body>

  <h1>Riepilogo dati e costi</h1>
  <h2>{{ $associazione }} — Consuntivo {{ $anno }}</h2>

  @foreach($pagine as $pi => $p)
    <div class="block">
      {{-- Titolo convenzione/pagina logica --}}
      <div class="section">
        <h3><span class="badge">{{ $p['conv_label'] }}</span></h3>

        {{-- ====================== 1) RIEPILOGO DATI (una pagina) ====================== --}}
        @php
          $totPrev1 = 0.0; $totCons1 = 0.0;
          $rowsDati = $p['tab_generale'] ?? [];
          /* se molte righe, usa variante più compatta */
          $useCompact = count($rowsDati) > 24;
        @endphp
        <table class="{{ $useCompact ? 'compact' : '' }}">
          <colgroup>
            <col class="col-desc">
            <col class="col-num">
            <col class="col-num">
          </colgroup>
          <thead>
            <tr>
              <th>Descrizione</th>
              <th>Preventivo</th>
              <th>Consuntivo</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rowsDati as $r)
              @php
                $prev = $num($r['preventivo'] ?? 0);
                $cons = $num($r['consuntivo'] ?? 0);
                $totPrev1 += $prev;
                $totCons1 += $cons;
              @endphp
              <tr>
                <td class="wrap">{{ $r['descrizione'] ?? '' }}</td>
                <td class="text-end">{{ $fmt($prev) }}</td>
                <td class="text-end">{{ $fmt($cons) }}</td>
              </tr>
            @endforeach
            <tr class="tot-row">
              <td>TOTALE GENERALE — RIEPILOGO DATI</td>
              <td class="text-end">{{ $fmt($totPrev1) }}</td>
              <td class="text-end">{{ $fmt($totCons1) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- =============== 2) RIEPILOGO COSTI (max 2 pagine per blocco) =============== --}}
      @php
        $flat = $flattenCostSections($p['sezioni_costi'] ?? [], $num);
        /* con font/padding stretti, 46–48 item ci stanno comodi; mettiamo 46 */
        [$page1, $page2] = $chunkCostsInTwoPages($flat, 46);

        // Totali generali costi (su tutte le righe di tipo "row")
        $totPrev2 = 0.0; $totCons2 = 0.0;
        foreach ($flat as $it) {
          if ($it['type'] === 'row') { $totPrev2 += $it['prev']; $totCons2 += $it['cons']; }
        }
      @endphp

      {{-- Tabella costi — pagina 1 --}}
      <div class="section">
        <table>
          <colgroup>
            <col class="col-desc">
            <col class="col-num">
            <col class="col-num">
          </colgroup>
          <thead>
            <tr>
              <th>Descrizione</th>
              <th>Preventivo</th>
              <th>Consuntivo</th>
            </tr>
          </thead>
          <tbody>
            @foreach($page1 as $it)
              @if($it['type']==='header')
                <tr class="sec-head"><td colspan="3">{{ $it['label'] }}</td></tr>
              @elseif($it['type']==='row')
                <tr>
                  <td class="wrap">{{ $it['desc'] }}</td>
                  <td class="text-end">{{ $fmt($it['prev']) }}</td>
                  <td class="text-end">{{ $fmt($it['cons']) }}</td>
                </tr>
              @elseif($it['type']==='total')
                <tr>
                  <td class="text-end"><strong>{{ $it['label'] }}</strong></td>
                  <td class="text-end"><strong>{{ $fmt($it['prev']) }}</strong></td>
                  <td class="text-end"><strong>{{ $fmt($it['cons']) }}</strong></td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Tabella costi — pagina 2 (solo se serve) --}}
      @if(count($page2))
        <div style="page-break-before: always"></div>
        <div class="section">
          <table>
            <colgroup>
              <col class="col-desc">
              <col class="col-num">
              <col class="col-num">
            </colgroup>
            <thead>
              <tr>
                <th>Descrizione</th>
                <th>Preventivo</th>
                <th>Consuntivo</th>
              </tr>
            </thead>
            <tbody>
              @foreach($page2 as $it)
                @if($it['type']==='header')
                  <tr class="sec-head"><td colspan="3">{{ $it['label'] }}</td></tr>
                @elseif($it['type']==='row')
                  <tr>
                    <td class="wrap">{{ $it['desc'] }}</td>
                    <td class="text-end">{{ $fmt($it['prev']) }}</td>
                    <td class="text-end">{{ $fmt($it['cons']) }}</td>
                  </tr>
                @elseif($it['type']==='total')
                  <tr>
                    <td class="text-end"><strong>{{ $it['label'] }}</strong></td>
                    <td class="text-end"><strong>{{ $fmt($it['prev']) }}</strong></td>
                    <td class="text-end"><strong>{{ $fmt($it['cons']) }}</strong></td>
                  </tr>
                @endif
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

      {{-- Totale generale costi del blocco --}}
      <table style="margin-top:6px">
        <colgroup><col class="col-desc"><col class="col-num"><col class="col-num"></colgroup>
        <tbody>
          <tr class="tot-row">
            <td>TOTALE GENERALE — RIEPILOGO COSTI</td>
            <td class="text-end">{{ $fmt($totPrev2) }}</td>
            <td class="text-end">{{ $fmt($totCons2) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  @endforeach

</body>
</html>
