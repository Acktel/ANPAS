{{-- resources/views/template/costi_automezzi_sanitari_2blocchi.blade.php --}}
@php
  // se $rows è array, meglio cast a collection solo per comodità
  $rows = collect($rows);

  $w1 = (100 - 14) / max(count($cols1),1); // % per le colonne del blocco 1
  $w2 = (100 - 14) / max(count($cols2),1); // % per le colonne del blocco 2
@endphp

<style>
  @page { size: A4 landscape; margin: 8mm; }
  table { width:100%; border-collapse:collapse; }
  thead { display: table-header-group; }
  th,td { border:1px solid #999; padding:3px; font-size:9px;width: fit-content; }
  tr { page-break-inside: avoid; break-inside: avoid; }
  .page-break { page-break-before: always; break-before: page; }
  .thwrap { white-space: normal; word-break: break-word; line-height: 1.15; }
  .txt { white-space: nowrap; }
  .tot td { font-weight: bold; background:#f4f7ff; }
</style>

<h3 style="margin:0 0 4px;">DISTINTA RILEVAZIONE ANALITICA COSTI AUTOMEZZI ED ATTREZZATURA SANITARIA</h3>
<div style="margin:0 0 8px;">{{ $associazione->Associazione }} — Consuntivo {{ $anno }}</div>

{{-- BLOCCO 1: prime colonne --}}
<table>
  <thead>
    <tr>
      <th style="width:7%">TARGA</th>
      <th style="width:7%">CODICE ID</th>
      @foreach($cols1 as $key => $label)
        <th style="width:{{ $w1 }}%"><div class="thwrap">{{ $label }}</div></th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $r)
      <tr>
        <td class="txt">{{ $r['Targa'] ?? $r->Targa ?? '' }}</td>
        <td class="txt">{{ $r['Codice'] ?? $r->CodiceIdentificativo ?? '' }}</td>
        @foreach($cols1 as $key => $label)
          @php $val = is_array($r) ? ($r[$key] ?? 0) : ($r->$key ?? 0); @endphp
          <td style="text-align:right">{{ number_format((float)$val, 2, ',', '.') }}</td>
        @endforeach
      </tr>
    @endforeach
    <tr class="tot">
      <td colspan="2">TOTALE</td>
      @foreach($cols1 as $key => $label)
        <td style="text-align:right">{{ number_format((float)($tot1[$key] ?? 0), 2, ',', '.') }}</td>
      @endforeach
    </tr>
  </tbody>
</table>

<div class="page-break"></div> {{-- NUOVA PAGINA TRA I DUE BLOCCHI --}}

{{-- BLOCCO 2: seconde colonne --}}
<table>
  <thead>
    <tr>
      <th style="width:7%">TARGA</th>
      <th style="width:7%">CODICE ID</th>
      @foreach($cols2 as $key => $label)
        <th style="width:{{ $w2 }}%"><div class="thwrap">{{ $label }}</div></th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $r)
      <tr>
        <td class="txt">{{ $r['Targa'] ?? $r->Targa ?? '' }}</td>
        <td class="txt">{{ $r['Codice'] ?? $r->CodiceIdentificativo ?? '' }}</td>
        @foreach($cols2 as $key => $label)
          @php $val = is_array($r) ? ($r[$key] ?? 0) : ($r->$key ?? 0); @endphp
          <td style="text-align:right">{{ number_format((float)$val, 2, ',', '.') }}</td>
        @endforeach
      </tr>
    @endforeach
    <tr class="tot">
      <td colspan="2">TOTALE</td>
      @foreach($cols2 as $key => $label)
        <td style="text-align:right">{{ number_format((float)($tot2[$key] ?? 0), 2, ',', '.') }}</td>
      @endforeach
    </tr>
  </tbody>
</table>
