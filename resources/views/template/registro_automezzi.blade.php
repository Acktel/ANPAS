{{-- Registro Automezzi – PDF --}}
@php
  /** @var \Illuminate\Support\Collection $automezzi */
  $nVeicoli = $automezzi?->count() ?? 0;

  $sumKmRif = 0; $sumKmTot = 0;
  foreach ($automezzi as $r) {
    $sumKmRif += (float)($r->KmRiferimento ?? 0);
    $sumKmTot += (float)($r->KmTotali ?? 0);
  }

  $num = fn($v) => number_format((float)$v, 0, ',', '.');
  $dt  = function($v) {
    if (empty($v)) return '—';
    try { return \Carbon\Carbon::parse($v)->format('d/m/Y'); } catch (\Throwable $e) { return $v; }
  };

@endphp
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Registro Automezzi – {{ $anno }} – {{ $associazione->Associazione ?? ('#'.$idAssociazione) }}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
    h1,h2,h3 { margin: 0 0 6px 0; }
    .header { margin-bottom: 10px; }
    .small { font-size: 11px; color:#555; }

    .summary { width:100%; border-collapse:collapse; margin: 8px 0 14px 0; table-layout: fixed; }
    .summary th, .summary td { border:1px solid #ccc; padding:6px 8px; }
    .summary th { background:#f1f5f9; text-align:left; }
    .text-end { text-align:right; }
    .text-center { text-align:center; }

    .tbl { width:100%; border-collapse: collapse; table-layout: fixed; }
    .tbl th, .tbl td { border:1px solid #ccc; padding:5px 6px; vertical-align: middle; }
    .tbl th { background:#f8fafc; }
    /* un'unica tabella che può proseguire su più pagine con thead ripetuto */
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; }

    /* larghezze colonne per far stare tutto */
    .col-idx { width: 28px; }
    .col-targa { width: 90px; }
    .col-cod { width: 80px; }
    .col-annoimm { width: 70px; }
    .col-annoacq { width: 70px; }
    .col-mod { width: 150px; }
    .col-tipov { width: 120px; }
    .col-kmr { width: 90px; }
    .col-kmt { width: 90px; }
    .col-carb { width: 90px; }
    .col-datasan { width: 95px; }
    .col-collaudo { width: 95px; }
    .col-note { width: 160px; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Registro Automezzi</h1>
    <div class="small">
      Anno: <strong>{{ $anno }}</strong> — Associazione: <strong>{{ $associazione->Associazione ?? ('#'.$idAssociazione) }}</strong>
    </div>
  </div>

  {{-- riepilogo compatto --}}
  <table class="summary">
    <thead>
      <tr>
        <th>Numero automezzi</th>
        <th class="text-end">Tot. km esercizio riferimento</th>
        <th class="text-end">Tot. km percorsi</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $nVeicoli }}</td>
        <td class="text-end">{{ $num($sumKmRif) }}</td>
        <td class="text-end">{{ $num($sumKmTot) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- tabellone unico --}}
  <table class="tbl">
    <thead>
      <tr>
        <th class="col-idx">#</th>
        <th class="col-targa">Targa</th>
        <th class="col-cod">Codice identificativo</th>
        <th class="col-annoimm">Anno prima imm.</th>
        <th class="col-annoacq">Anno d’acquisto</th>
        <th class="col-mod">Modello</th>
        <th class="col-tipov">Tipo di veicolo</th>
        <th class="col-kmr text-end">Km esercizio rif.</th>
        <th class="col-kmt text-end">Totale km percorsi</th>
        <th class="col-carb">Carburante</th>
        <th class="col-datasan">Ultima autorizz. sanitaria</th>
        <th class="col-collaudo">Ultimo collaudo</th>
        <th class="col-note">Note</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($automezzi as $i => $r)
        <tr>
          <td class="text-center">{{ $i+1 }}</td>
          <td>{{ $r->Targa }}</td>
          <td>{{ $r->CodiceIdentificativo }}</td>
          <td class="text-center">{{ $r->AnnoPrimaImmatricolazione ?: '—' }}</td>
          <td class="text-center">{{ $r->AnnoAcquisto ?: '—' }}</td>
          <td>{{ $r->Modello }}</td>
          <td>{{ $r->TipoVeicolo }}</td>
          <td class="text-end">{{ $num($r->KmRiferimento ?? 0) }}</td>
          <td class="text-end">{{ $num($r->KmTotali ?? 0) }}</td>
          <td>{{ $r->TipoCarburante }}</td>
          <td class="text-center">{{ $dt($r->DataUltimaAutorizzazioneSanitaria ?? null) }}</td>
          <td class="text-center">{{ $dt($r->DataUltimoCollaudo ?? null) }}</td>
          <td>{{ $r->note ?? '' }}</td>
        </tr>
      @empty
        <tr><td colspan="13" class="text-center">Nessun automezzo trovato.</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
