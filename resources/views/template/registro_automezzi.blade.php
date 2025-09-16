{{-- resources/views/template/registro_automezzi.blade.php --}}
@php
  $nVeicoli = $automezzi?->count() ?? 0;
  $sumKmRif = $automezzi->sum(fn($r)=>(float)($r->KmRiferimento ?? 0));
  $sumKmTot = $automezzi->sum(fn($r)=>(float)($r->KmTotali ?? 0));

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
    @page { size: A4 landscape; margin: 8mm; }
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 8px; color:#111; margin:0; padding:0; }
    h1 { font-size: 11px; margin: 0 0 4px; }
    .small { font-size: 8px; color:#555; margin-bottom: 5px; }

    .summary { width:100%; border-collapse:collapse; margin: 5px 0 8px 0; table-layout: fixed; font-size:7.8px; }
    .summary th, .summary td { border:1px solid #999; padding:2px 3px; }
    .summary th { background:#f1f5f9; text-align:left; }

    .tbl { width:100%; border-collapse: collapse; table-layout: fixed; font-size:7.6px; }
    .tbl th, .tbl td { border:1px solid #999; padding:2px 2px; vertical-align: middle; }
    .tbl th { background:#f8fafc; font-weight:bold; font-size:7.4px; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }

    /* larghezze più strette per far stare tutto */
    .col-idx { width: 20px; }
    .col-targa { width: 60px; }
    .col-cod { width: 55px; }
    .col-annoimm { width: 50px; }
    .col-annoacq { width: 50px; }
    .col-mod { width: 95px; }
    .col-tipov { width: 85px; }
    .col-kmr { width: 65px; }
    .col-kmt { width: 65px; }
    .col-carb { width: 65px; }
    .col-datasan { width: 75px; }
    .col-collaudo { width: 75px; }

    .text-center { text-align:center; }
    .text-end { text-align:right; }
  </style>
</head>
<body>
  <h1>Registro Automezzi</h1>
  <div class="small">
    Anno: <strong>{{ $anno }}</strong> — Associazione: <strong>{{ $associazione->Associazione ?? ('#'.$idAssociazione) }}</strong>
  </div>

  {{-- Riepilogo --}}
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

  {{-- Tabellone --}}
  <table class="tbl">
    <thead>
      <tr>
        <th class="col-idx">#</th>
        <th class="col-targa">Targa</th>
        <th class="col-cod">Codice Identificativo</th>
        <th class="col-annoimm">Anno prima imm.</th>
        <th class="col-annoacq">Anno acquisto</th>
        <th class="col-mod">Modello</th>
        <th class="col-tipov">Tipo veicolo</th>
        <th class="col-kmr">Km esercizio rif.</th>
        <th class="col-kmt">Totale Km</th>
        <th class="col-carb">Carburante</th>
        <th class="col-datasan">Ultima Aut. san.</th>
        <th class="col-collaudo">Ultimo Collaudo</th>
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
        </tr>
      @empty
        <tr><td colspan="12" class="text-center">Nessun automezzo trovato.</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
