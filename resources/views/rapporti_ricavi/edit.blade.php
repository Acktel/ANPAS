@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4 container-title">
    Modifica ricavi per convenzione — Anno {{ $anno }}
    @isset($associazione) <small class="text-muted">({{ $associazione }})</small> @endisset
  </h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('rapporti-ricavi.update', ['id' => $idAssociazione]) }}">
    @csrf
    @method('PUT')

    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr class="text-center">
            <th style="width:45%">Convenzione</th>
            <th style="width:20%">Rimborso (€)</th>
            <th>Note (opzionale)</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($convenzioni as $conv)
            @php
              $row = optional($valori->get((int)$conv->idConvenzione));
              $rimborsoDb = $row->Rimborso ?? null;
              $notaDb     = $row->note     ?? '';

              $rimborso = old('ricavi.'.$conv->idConvenzione, $rimborsoDb);
              $nota     = old('note.'.$conv->idConvenzione,   $notaDb);
            @endphp
            <tr>
              <td>{{ $conv->Convenzione }}</td>
              <td>
                <input
                  type="number"
                  name="ricavi[{{ $conv->idConvenzione }}]"
                  class="form-control text-end ricavo-input"
                  value="{{ is_numeric($rimborso) && $rimborso != 0 ? number_format($rimborso, 2, '.', '') : '' }}"
                  step="0.01" min="0" placeholder="0,00">
              </td>
              <td>
                <textarea
                  name="note[{{ $conv->idConvenzione }}]"
                  class="form-control"
                  rows="2"
                  placeholder="Annotazioni su questa convenzione...">{{ $nota }}</textarea>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end my-3">
      <div class="card" style="min-width:320px">
        <div class="card-body d-flex justify-content-between align-items-center">
          <strong>Totale Esercizio:</strong>
          <span id="totaleEsercizio" class="fs-5 fw-bold">€ 0,00</span>
        </div>
      </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
      <button type="submit" class="btn btn-success me-2">
        <i class="fas fa-check me-1"></i> Salva
      </button>
      <a href="{{ route('rapporti-ricavi.index') }}" class="btn btn-secondary ms-2">
        <i class="fas fa-times me-1"></i> Annulla
      </a>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    function toNumber(v){ if(v==null) return 0; v=String(v).replace(/\./g,'').replace(',', '.'); const n=parseFloat(v); return isNaN(n)?0:n; }
    function formatEuro(n){ try{ return new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(n);}catch{ return '€ '+(Math.round(n*100)/100).toFixed(2).replace('.',','); } }
    function recalcTotal(){
      let sum=0;
      document.querySelectorAll('.ricavo-input').forEach(inp=>sum+=toNumber(inp.value));
      document.getElementById('totaleEsercizio').textContent = formatEuro(sum);
    }
    document.querySelectorAll('.ricavo-input').forEach(inp=>{
      inp.addEventListener('input',recalcTotal);
      inp.addEventListener('change',recalcTotal);
    });
    recalcTotal();
  })();
</script>
@endpush
