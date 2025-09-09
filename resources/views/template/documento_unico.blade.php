@php
  $num0 = fn($v) => number_format((float)$v, 0, ',', '.');
  $num2 = fn($v) => number_format((float)$v, 2, ',', '.');
  $pct2 = fn($v) => number_format((float)$v, 2, ',', '.') . '%';
@endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Documento unico – {{ $anno }} – {{ $associazione->Associazione ?? '' }}</title>
<style>
  @page { size: A4 landscape; margin: 12mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  h1{margin:0 0 8px 0;} .small{color:#555;margin:0 0 12px 0;}
  table{width:100%;border-collapse:collapse;table-layout:fixed;}
  th,td{border:1px solid #ccc;padding:6px 7px;vertical-align:middle;}
  thead th{background:#f8fafc;text-align:center;}
  .text-end{text-align:right;} .row-total{background:#f6f6f6;font-weight:bold;}
  .page-break{ page-break-after: always; }
  .col-rimb{width:9%} .col-pct{width:7%} .col-tot{width:16%}
  .col-targa{width:12%} .col-cod{width:16%} .col-nome{width:24%}
</style>
</head>
<body>

{{-- === SEZIONE 1: RAPPORTI RICAVI === --}}
<h1>Rapporti ricavi / percentuali</h1>
<div class="small">Associazione: <b>{{ $associazione->Associazione ?? '' }}</b> — Esercizio: <b>{{ $anno }}</b></div>
<table>
  <thead>
    <tr>
      <th class="col-tot" rowspan="2">TOTALE RICAVI DELL’ESERCIZIO</th>
      @foreach ($convenzioni as $c)<th colspan="2">{{ $c->Convenzione }}</th>@endforeach
      <th class="col-pct" rowspan="2">Tot %</th>
    </tr>
    <tr>
      @foreach ($convenzioni as $c)
        <th class="col-rimb">RIMBORSO</th><th class="col-pct">%</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-end">{{ $num2($ricaviRow['TotaleEsercizio'] ?? 0) }}</td>
      @foreach ($convenzioni as $c)
        @php $k='c'.$c->idConvenzione; @endphp
        <td class="text-end">{{ $num2($ricaviRow[$k.'_rimborso'] ?? 0) }}</td>
        <td class="text-end">{{ $pct2($ricaviRow[$k.'_percent'] ?? 0) }}</td>
      @endforeach
      <td class="text-end">100,00%</td>
    </tr>
  </tbody>
</table>

<div class="page-break"></div>

{{-- === SEZIONE 2: DISTINTA KM PERCORSI === --}}
<h1>Distinta KM percorsi / % per convenzione</h1>
<table>
  <thead>
    <tr>
      <th class="col-targa" rowspan="2">Targa</th>
      <th class="col-cod"   rowspan="2">Codice identificativo</th>
      <th class="col-nome"  rowspan="2">Automezzo</th>
      <th class="col-tot"   rowspan="2">KM totali</th>
      @foreach ($convenzioni as $c)<th colspan="2">{{ $c->Convenzione }}</th>@endforeach
    </tr>
    <tr>
      @foreach ($convenzioni as $c)<th>KM</th><th class="col-pct">%</th>@endforeach
    </tr>
  </thead>
  <tbody>
    @foreach ($kmRows as $r)
      <tr class="{{ ($r['is_totale'] ?? 0)===-1 ? 'row-total' : '' }}">
        <td>{{ ($r['is_totale'] ?? 0)===-1 ? '' : ($r['Targa'] ?? '') }}</td>
        <td>{{ ($r['is_totale'] ?? 0)===-1 ? '' : ($r['CodiceIdentificativo'] ?? '') }}</td>
        <td>{{ ($r['is_totale'] ?? 0)===-1 ? 'TOTALE' : ($r['Automezzo'] ?? '') }}</td>
        <td class="text-end">{{ $num0($r['Totale'] ?? 0) }}</td>
        @foreach ($convenzioni as $c)
          @php $k='c'.$c->idConvenzione; @endphp
          <td class="text-end">{{ $num0($r[$k.'_km'] ?? 0) }}</td>
          <td class="text-end">{{ $pct2($r[$k.'_percent'] ?? 0) }}</td>
        @endforeach
      </tr>
    @endforeach
  </tbody>
</table>

<div class="page-break"></div>

{{-- === SEZIONE 3: DISTINTA SERVIZI SVOLTI === --}}
<h1>Distinta servizi svolti / % per convenzione</h1>
<table>
  <thead>
    <tr>
      <th class="col-targa" rowspan="2">Targa</th>
      <th class="col-cod"   rowspan="2">Codice identificativo</th>
      <th class="col-nome"  rowspan="2">Automezzo</th>
      <th class="col-tot"   rowspan="2">Totale servizi</th>
      @foreach ($convenzioni as $c)<th colspan="2">{{ $c->Convenzione }}</th>@endforeach
    </tr>
    <tr>
      @foreach ($convenzioni as $c)<th>N. servizi</th><th class="col-pct">%</th>@endforeach
    </tr>
  </thead>
  <tbody>
    @foreach ($servRows as $r)
      <tr class="{{ ($r['is_totale'] ?? 0)===-1 ? 'row-total' : '' }}">
        <td>{{ ($r['is_totale'] ?? 0)===-1 ? '' : ($r['Targa'] ?? '') }}</td>
        <td>{{ ($r['is_totale'] ?? 0)===-1 ? '' : ($r['CodiceIdentificativo'] ?? '') }}</td>
        <td>{{ ($r['is_totale'] ?? 0)===-1 ? 'TOTALE' : ($r['Automezzo'] ?? '') }}</td>
        <td class="text-end">{{ $num0($r['Totale'] ?? 0) }}</td>
        @foreach ($convenzioni as $c)
          @php $k='c'.$c->idConvenzione; @endphp
          <td class="text-end">{{ $num0($r[$k.'_n'] ?? 0) }}</td>
          <td class="text-end">{{ $pct2($r[$k.'_percent'] ?? 0) }}</td>
        @endforeach
      </tr>
    @endforeach
  </tbody>
</table>

<div class="page-break"></div>

{{-- === SEZIONE 4: REGISTRO AUTOMEZZI (condensato) === --}}
<h1>Registro automezzi</h1>
<table>
  <thead>
    <tr>
      <th class="col-targa">Targa</th>
      <th class="col-cod">Codice identificativo</th>
      <th class="col-nome">Automezzo</th>
      <th>Modello</th>
      <th>Km totali</th>
      <th>Carburante</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($registro as $a)
      <tr>
        <td>{{ $a->Targa }}</td>
        <td>{{ $a->CodiceIdentificativo }}</td>
        <td>{{ $a->Automezzo }}</td>
        <td>{{ $a->Modello ?? '' }}</td>
        <td class="text-end">{{ $num0($a->KmTotali ?? 0) }}</td>
        <td>{{ $a->idTipoCarburante ?? '' }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

</body>
</html>
