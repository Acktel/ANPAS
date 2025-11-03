@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="container-title mb-0">Modifica {{ $labelMerge }}</h1>
    <span class="badge text-bg-primary">Anno: {{ $anno }}</span>
  </div>
  <p class="text-muted">Associazione <strong>{{ $nomeAssociazione }}</strong> — Convenzione <strong>{{ $nomeConvenzione }}</strong></p>

  <form action="{{ route('riepilogo.costi.update.formazione') }}" method="POST" class="card-anpas mb-3">
    @csrf
    <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
    <input type="hidden" name="idConvenzione" value="{{ $idConvenzione }}">
    <input type="hidden" name="idA" value="{{ $idA }}">
    <input type="hidden" name="idB" value="{{ $idB }}">

    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="thead-anpas">
            <tr>
              <th>Voce</th>
              <th class="text-end" style="width:25%">Preventivo (€)</th>
              <th class="text-end" style="width:25%">Consuntivo (€)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Allegati A</td>
              <td><input type="text" name="preventivo_a" class="form-control text-end" value="{{ number_format($prevA,2,',','.') }}" inputmode="decimal" placeholder="0,00"></td>
              <td><input type="text" class="form-control text-end" value="{{ number_format($consA,2,',','.') }}" readonly></td>
            </tr>
            <tr>
              <td>DAE + RDAE</td>
              <td><input type="text" name="preventivo_b" class="form-control text-end" value="{{ number_format($prevB,2,',','.') }}" inputmode="decimal" placeholder="0,00"></td>
              <td><input type="text" class="form-control text-end" value="{{ number_format($consB,2,',','.') }}" readonly></td>
            </tr>
            <tr class="table-light">
              <td class="fw-bold">Totale {{ $labelMerge }}</td>
              <td class="text-end fw-bold">{{ number_format($prevA+$prevB,2,',','.') }}</td>
              <td class="text-end fw-bold">{{ number_format($consA+$consB,2,',','.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('riepilogo.costi', ['idAssociazione'=>$idAssociazione,'idConvenzione'=>$idConvenzione]) }}" class="btn btn-secondary">Annulla</a>
        <button type="submit" class="btn btn-anpas-green"><i class="fas fa-check me-1"></i> Salva</button>
      </div>
    </div>
  </form>
</div>
@endsection
