{{-- resources/views/template/pdf_riepiloghi_dati_costi.blade.php --}}
@php
  // formato numerico
  $fmt = fn($v) => number_format((float)$v, 2, ',', '.');

  // cast sicuro
  $num = function($v) {
    if ($v === '-' || $v === null || $v === '') return 0.0;
    return (float) $v;
  };

  // accesso chiave sia per oggetti che per array
  $get = function($row, string $key, $default = null) {
    if (is_array($row))  return $row[$key] ?? $default;
    if (is_object($row)) return $row->{$key} ?? $default;
    return $default;
  };

  /**
   * Flatten delle sezioni costi:
   * - header: ['type'=>'header','label'=>string]
   * - row   : ['type'=>'row',   'desc'=>string,'prev'=>float,'cons'=>float]
   * - total : ['type'=>'total', 'label'=>string,'prev'=>float,'cons'=>float]
   */
  $flattenCostSections = function(array $sezioni) use ($num, $get) {
    $out = [];
    foreach ($sezioni as $sec) {
      $labelSec = is_array($sec) ? ($sec['label'] ?? '') : ($sec->label ?? '');
      $righe    = is_array($sec) ? ($sec['righe'] ?? []) : ($sec->righe ?? []);
      $tot      = is_array($sec) ? ($sec['totali'] ?? []) : ($sec->totali ?? []);

      if ($labelSec !== '') {
        $out[] = ['type'=>'header', 'label'=> (string) $labelSec];
      }

      foreach ($righe as $r) {
        $out[] = [
          'type' => 'row',
          'desc' => (string) $get($r, 'descrizione', ''),
          'prev' => $num($get($r, 'preventivo', 0)),
          'cons' => $num($get($r, 'consuntivo', 0)),
        ];
      }

      if (!empty($tot)) {
        $totPrev = (float) (is_array($tot) ? ($tot['preventivo'] ?? 0) : ($tot->preventivo ?? 0));
        $totCons = (float) (is_array($tot) ? ($tot['consuntivo'] ?? 0) : ($tot->consuntivo ?? 0));
        $out[] = [
          'type'  => 'total',
          'label' => 'TOTALE ' . (string) $labelSec,
          'prev'  => $totPrev,
          'cons'  => $totCons,
        ];
      }
    }
    return $out;
  };
@endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Riepilogo dati e costi – {{ $associazione }} – {{ $anno }}</title>
<style>
  @page { size: A4 landscape; margin: 8mm; }
  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 8.6px; color:#111; }

  h1 { margin: 0 0 6px 0; font-size: 12px; }
  h2 { margin: 0 0 8px 0; font-size: 9.5px; color:#444; }
  h3 { margin: 6px 0 4px 0; font-size: 9.5px; page-break-after: avoid; }

  .section { margin-top: 6px; page-break-inside: avoid; break-inside: avoid; }

  .badge {
    display:inline-block; padding: 2px 6px; border-radius: 4px;
    background:#e8f4ff; border:1px solid #bcd9ff; font-weight:600; font-size: 8.6px;
  }

  table { width:100%; border-collapse:collapse; table-layout: fixed; }
  thead { display: table-header-group; } /* ripeti intestazioni su pagina nuova */
  tr { page-break-inside: avoid; }

  th, td { border:1px solid #cfcfcf; padding: 2px 3px; vertical-align: middle; line-height: 1.14; }
  thead th { background:#f8fafc; text-align:center; font-weight:700; font-size: 7.6px; }

  .text-end { text-align: right; }
  .wrap { white-space: normal; overflow-wrap: anywhere; word-break: break-word; hyphens: auto; }

  .tot-row td { background:#efefef; font-weight:700; }
  .sec-head td { background:#e9ecef; font-weight:700; }

  .col-desc { width: 58%; }
  .col-num  { width: 21%; }

  .compact { font-size: 8.2px; }
  .compact th, .compact td { padding: 1px 2px; line-height: 1.1; }

  /* niente page-break forzati tra blocchi */
  .block { margin-bottom: 8px; }
</style>
</head>
<body>

  <h1>Riepilogo dati e costi</h1>
  <h2>{{ $associazione }} — Consuntivo {{ $anno }}</h2>

  @foreach(($pagine ?? []) as $pi => $p)
    @php
      $convLabel   = is_array($p) ? ($p['conv_label'] ?? '') : ($p->conv_label ?? '');
      $tabGenerale = is_array($p) ? ($p['tab_generale'] ?? []) : ($p->tab_generale ?? []);
      $sezCosti    = is_array($p) ? ($p['sezioni_costi'] ?? []) : ($p->sezioni_costi ?? []);

      $rowsDati    = $tabGenerale ?? [];
      $hasDati     = is_countable($rowsDati) ? (count($rowsDati) > 0) : !empty($rowsDati);

      $flat        = $flattenCostSections(is_array($sezCosti) ? $sezCosti : []);
      $hasCosti    = count($flat) > 0;

      // totali generali costi (solo righe)
      $totPrev2 = 0.0; $totCons2 = 0.0;
      foreach ($flat as $it) {
        if (($it['type'] ?? null) === 'row') {
          $totPrev2 += (float)$it['prev'];
          $totCons2 += (float)$it['cons'];
        }
      }
    @endphp

    <div class="block">
      <div class="section">
        <h3><span class="badge">{{ $convLabel }}</span></h3>

        {{-- ====================== 1) RIEPILOGO DATI (solo se presente) ====================== --}}
        @if($hasDati)
          @php
            $totPrev1 = 0.0; $totCons1 = 0.0;
            $useCompact = is_countable($rowsDati) ? (count($rowsDati) > 28) : false;
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
                  $desc = (string) $get($r, 'descrizione', '');
                  $prev = $num($get($r, 'preventivo', 0));
                  $cons = $num($get($r, 'consuntivo', 0));
                  $totPrev1 += $prev; $totCons1 += $cons;
                @endphp
                <tr>
                  <td class="wrap">{{ $desc }}</td>
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
        @endif
      </div>

      {{-- =============== 2) RIEPILOGO COSTI (unica tabella, no spezzatini manuali) =============== --}}
      @if($hasCosti)
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
              @foreach($flat as $it)
                @if(($it['type'] ?? '') === 'header')
                  <tr class="sec-head">
                    <td colspan="3">{{ $it['label'] }}</td>
                  </tr>
                @elseif(($it['type'] ?? '') === 'row')
                  <tr>
                    <td class="wrap">{{ $it['desc'] }}</td>
                    <td class="text-end">{{ $fmt($it['prev']) }}</td>
                    <td class="text-end">{{ $fmt($it['cons']) }}</td>
                  </tr>
                @elseif(($it['type'] ?? '') === 'total')
                  <tr>
                    <td class="text-end"><strong>{{ $it['label'] }}</strong></td>
                    <td class="text-end"><strong>{{ $fmt($it['prev']) }}</strong></td>
                    <td class="text-end"><strong>{{ $fmt($it['cons']) }}</strong></td>
                  </tr>
                @endif
              @endforeach
            </tbody>
          </table>

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
      @endif
    </div>
  @endforeach

</body>
</html>
