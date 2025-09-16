{{-- resources/views/template/rip_costi_automezzi_unico.blade.php --}}
@php
  $fmt = fn($v) => number_format((float)$v, 2, ',', '.');
@endphp

<style>
  @page { size: A4 landscape; margin: 8mm; }

  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; }
  body { font-family: DejaVu Sans, sans-serif; color:#111; }

  h3 { margin:0 0 6px; text-transform: uppercase; font-size: 12px; }
  .sub { margin:0 0 8px; font-size: 9px; color:#444; }

  table { width:100%; border-collapse: collapse; table-layout: fixed; }
  thead { display: table-header-group; }     /* ripete header su nuova pagina */
  tr { page-break-inside: avoid; }

  /* Stile tabella compatto */
  th, td { border:1px solid #8a8a8a; font-size: 8.6px; padding: 2px 3px; line-height: 1.15; }
  th { background:#eef7ff; font-weight:700; }

  /* Colonne più strette per farci stare tutto */
  .voce { width: 26%; }
  .right { text-align:right; }
  .nowrap { white-space:nowrap; }

  .tot-row td { font-weight:700; background:#f7f7f7; }

  /* Blocchi mezzo: nessuna interruzione forzata prima; compattiamo i margini */
  .mezzo-block { margin: 8px 0 10px; }
  .mezzo-block h3 { margin-top: 2px; }

  /* Quando serve proprio staccare (solo se tabella precedente ha occupato quasi tutta la pagina) ci pensa il browser */
  /* NIENTE .break con page-break-before: always; */
</style>

{{-- ==================== SEZIONE 1: TABELLA TOTALE ==================== --}}
<h3>RIPARTIZIONE COSTI AUTOMEZZI – MATERIALE E ATTREZZATURA SANITARIA – COSTI RADIO</h3>
<div class="sub">{{ $associazione->Associazione }} — Consuntivo {{ $anno }}</div>

<table>
  <thead>
    <tr>
      <th class="voce">TOTALE AUTO</th>
      @foreach($convenzioni as $conv)
        <th class="right">{{ $conv }}</th>
      @endforeach
      <th class="right">TOTALE</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rowsTotali as $r)
      <tr>
        <td class="nowrap">{{ $r['voce'] ?? '' }}</td>
        @foreach($convenzioni as $conv)
          <td class="right">{{ $fmt($r[$conv] ?? 0) }}</td>
        @endforeach
        <td class="right">{{ $fmt($r['totale'] ?? 0) }}</td>
      </tr>
    @endforeach
    <tr class="tot-row">
      <td>TOTALI</td>
      @foreach($convenzioni as $conv)
        <td class="right">{{ $fmt($colTotals[$conv] ?? 0) }}</td>
      @endforeach
      <td class="right">{{ $fmt($grandTot) }}</td>
    </tr>
  </tbody>
</table>

{{-- ================= SEZIONE 2: BLOCCHI MEZZO COMPATTI (NO PAGE-BREAK FORZATI) ================= --}}
@foreach($sezioniMezzo as $i => $sec)
  <div class="mezzo-block">
    <h3>RIPARTIZIONE COSTI AUTOMEZZI – MATERIALE E ATTREZZATURA SANITARIA – COSTI RADIO</h3>
    <div class="sub">
      {{ $associazione->Associazione }} — Consuntivo {{ $anno }}<br>
      <strong>Targa:</strong> {{ $sec['targa'] }} &nbsp; <strong>Codice:</strong> {{ $sec['codice'] }}
    </div>

    <table>
      <thead>
        <tr>
          <th class="voce">VOCE</th>
          <th class="right">TOTALE COSTI DA RIPARTIRE</th>
          @foreach($convenzioni as $conv)
            <th class="right">{{ $conv }}</th>
          @endforeach
          <th class="right">TOTALE</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sec['righe'] as $r)
          <tr>
            <td class="nowrap">{{ $r['voce'] ?? '' }}</td>
            <td class="right">{{ $fmt($r['totale'] ?? 0) }}</td>
            @foreach($convenzioni as $conv)
              <td class="right">{{ $fmt($r[$conv] ?? 0) }}</td>
            @endforeach
            <td class="right">{{ $fmt($r['totale'] ?? 0) }}</td>
          </tr>
        @endforeach
        <tr class="tot-row">
          <td>TOTALI</td>
          <td class="right">{{ $fmt($sec['totTot']) }}</td>
          @foreach($convenzioni as $conv)
            <td class="right">{{ $fmt($sec['totCol'][$conv] ?? 0) }}</td>
          @endforeach
          <td class="right">{{ $fmt($sec['totTot']) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
@endforeach
