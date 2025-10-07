@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="container-title mb-4">Nuovo Inserimento Ricavi Convenzioni – Anno {{ $anno }}</h1>

  {{-- Errori di validazione --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('rapporti-ricavi.store') }}">
    @csrf

    {{-- Associazione (se ce n'è una sola, la mostro in sola lettura + hidden) --}}
    @if ($associazioni->count() === 1)
      @php $only = $associazioni->first(); @endphp
      <input type="hidden" name="idAssociazione" value="{{ $only->idAssociazione }}">
      <div class="mb-4">
        <label class="form-label">Associazione</label>
        <input type="text" class="form-control" value="{{ $only->Associazione }}" disabled>
      </div>
    @else
      <div class="mb-4">
        <label for="idAssociazione" class="form-label">Associazione</label>
        <select name="idAssociazione" id="idAssociazione" class="form-select" required>
          <option value="">-- Seleziona Associazione --</option>
          @foreach($associazioni as $ass)
            <option value="{{ $ass->idAssociazione }}"
              {{ (string)old('idAssociazione') === (string)$ass->idAssociazione ? 'selected' : '' }}>
              {{ $ass->Associazione }}
            </option>
          @endforeach
        </select>
      </div>
    @endif

    <div class="alert alert-info">
      Inserisci il rimborso e, se serve, una nota per ciascuna convenzione. Il totale viene calcolato automaticamente.
    </div>

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
          @foreach($convenzioni as $conv)
            <tr>
              <td>{{ $conv->Convenzione }}</td>
              <td>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="ricavi[{{ $conv->idConvenzione }}]"
                  class="form-control text-end ricavo-input"
                  value="{{ old('ricavi.'.$conv->idConvenzione) }}"
                  placeholder="0,00">
              </td>
              <td>
                <textarea
                  name="note[{{ $conv->idConvenzione }}]"
                  class="form-control"
                  rows="2"
                  placeholder="Note per questa convenzione...">{{ old('note.'.$conv->idConvenzione) }}</textarea>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Totale --}}
    <div class="d-flex justify-content-end my-3">
      <div class="card" style="min-width:320px">
        <div class="card-body d-flex justify-content-between align-items-center">
          <strong>Totale Esercizio:</strong>
          <span id="totaleEsercizio" class="fs-5 fw-bold">€ 0,00</span>
        </div>
      </div>
    </div>

    <div class="mt-4 d-flex justify-content-between">
      <a href="{{ route('rapporti-ricavi.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Indietro
      </a>
      <button type="submit" class="btn btn-success">
        <i class="fas fa-check me-1"></i> Salva
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    function toNumber(v) {
      if (typeof v !== 'string') return 0;
      // accetta anche la virgola come separatore decimale
      v = v.replace(/\./g, '').replace(',', '.');
      const n = parseFloat(v);
      return isNaN(n) ? 0 : n;
    }

    function formatEuro(n) {
      try {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(n);
      } catch {
        return '€ ' + (Math.round(n * 100) / 100).toFixed(2).replace('.', ',');
      }
    }

    function recalcTotal() {
      let sum = 0;
      document.querySelectorAll('.ricavo-input').forEach(inp => {
        sum += toNumber(inp.value || '0');
      });
      document.getElementById('totaleEsercizio').textContent = formatEuro(sum);
    }

    // Bind
    document.querySelectorAll('.ricavo-input').forEach(inp => {
      inp.addEventListener('input', recalcTotal);
      inp.addEventListener('change', recalcTotal);
    });

    // Prima render (per old())
    recalcTotal();
  })();
</script>
@endpush
