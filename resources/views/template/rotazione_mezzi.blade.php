<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Rotazione Mezzi</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
  h1 { margin: 0 0 8px 0; font-size: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #999; padding: 4px; text-align: center; }
  th { background: #eee; }
  td.left { text-align: left; }
  .tot { font-weight: bold; background: #f5f5f5; }
  .muted { color: #666; }
</style>
</head>
<body>
  <h1>Rotazione Mezzi</h1>
  <div><strong>Associazione:</strong> {{ $associazione }}</div>
  <div><strong>Anno:</strong> {{ $anno }}</div>

  @php
    // intestazioni dinamiche: per ogni convenzione due colonne (KM / %)
    $convIds = array_keys($convenzioni);
  @endphp

  <table style="margin-top:10px">
    <thead>
      <tr>
        <th>PROGR</th>
        <th>TARGA</th>
        <th>CODICE</th>
        <th>KM TOT</th>
        @foreach($convIds as $cid)
          <th colspan="2">{{ $convenzioni[$cid] }}</th>
        @endforeach
      </tr>
      <tr>
        <th class="muted"> </th>
        <th class="muted"> </th>
        <th class="muted"> </th>
        <th class="muted"> </th>
        @foreach($convIds as $cid)
          <th>KM</th>
          <th>%</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td class="left">{{ $r['progressivo'] }}</td>
          <td>{{ $r['targa'] }}</td>
          <td>{{ $r['codice'] }}</td>
          <td>{{ number_format($r['km_tot'], 0, ',', '.') }}</td>
          @foreach($convIds as $cid)
            @php
              $cell = $r['per_conv'][$cid] ?? ['km'=>0,'pct'=>0];
            @endphp
            <td>{{ number_format($cell['km'], 0, ',', '.') }}</td>
            <td>{{ number_format($cell['pct'] * 100, 2, ',', '.') }}%</td>
          @endforeach
        </tr>
      @endforeach

      {{-- riga totale --}}
      <tr class="tot">
        <td class="left" colspan="3">TOTALE</td>
        <td>{{ number_format($totale['km_tot'], 0, ',', '.') }}</td>
        @foreach($convIds as $cid)
          <td>{{ number_format($totale['per_conv'][$cid]['km'] ?? 0, 0, ',', '.') }}</td>
          <td>100,00%</td>
        @endforeach
      </tr>
    </tbody>
  </table>
</body>
</html>
