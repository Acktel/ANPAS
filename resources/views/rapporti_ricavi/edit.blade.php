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
              $row  = optional($valori->get((int)$conv->idConvenzione));
              $rimborso = old('ricavi.'.$conv->idConvenzione, $row->Rimborso ?? '');
              $nota     = old('note.'.$conv->idConvenzione,  $row->note ?? '');
          @endphp

          <tr>
            <td>{{ $conv->Convenzione }}</td>

            <!-- INPUT NUMERICO PULITO -->
            <td>
              <input
                type="number"
                class="form-control text-end ricavo-input"
                name="ricavi[{{ $conv->idConvenzione }}]"
                value="{{ $rimborso !== '' ? number_format((float)$rimborso, 2, '.', '') : '' }}"
                step="0.01"
                min="0">
            </td>

            <td>
              <textarea
                name="note[{{ $conv->idConvenzione }}]"
                class="form-control"
                rows="2"
              >{{ $nota }}</textarea>
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

    <div class="mt-4 d-flex justify-content-center myborder-button">
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
(function() {

  // converte NUMBER → valuta italiana
  function formatEuro(n) {
    return new Intl.NumberFormat('it-IT', {
      style: 'currency',
      currency: 'EUR'
    }).format(n);
  }

  // ricalcola totale
  function recalcTotal() {
    let sum = 0;

    document.querySelectorAll('.ricavo-input').forEach(inp => {
      const v = parseFloat(inp.value);
      if (!isNaN(v)) sum += v;
    });

    document.getElementById('totaleEsercizio').textContent = formatEuro(sum);
  }

  // attiva eventi
  document.querySelectorAll('.ricavo-input').forEach(inp => {
    inp.addEventListener('input', recalcTotal);
    inp.addEventListener('change', recalcTotal);
  });

  // totale iniziale
  recalcTotal();

})();
</script>
@endpush
