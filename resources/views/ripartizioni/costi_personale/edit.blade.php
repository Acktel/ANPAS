@extends('layouts.app')
@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Costi Dipendente</h1>

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('ripartizioni.personale.costi.update', $record->idDipendente) }}" method="POST" id="costiForm">
        @csrf
        @method('PUT')

        <input type="hidden" name="idDipendente" value="{{ $record->idDipendente }}">

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Dipendente</label>
            <input type="text" class="form-control" value="{{ $record->DipendenteCognome }} {{ $record->DipendenteNome }}" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label">Anno</label>
            <input type="text" class="form-control" value="{{ $anno }}" disabled>
          </div>
        </div>

        <hr>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Retribuzioni</label>
            <input type="number" name="Retribuzioni" step="0.01" class="form-control cost-input"
              value="{{ old('Retribuzioni', $record->Retribuzioni) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Oneri Sociali INPS</label>
            <input type="number" name="OneriSocialiInps" step="0.01" class="form-control cost-input"
              value="{{ old('OneriSocialiInps', $record->OneriSocialiInps) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Oneri Sociali INAIL</label>
            <input type="number" name="OneriSocialiInail" step="0.01" class="form-control cost-input"
              value="{{ old('OneriSocialiInail', $record->OneriSocialiInail) }}">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">TFR</label>
            <input type="number" name="TFR" step="0.01" class="form-control cost-input"
              value="{{ old('TFR', $record->TFR) }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">Consulenze</label>
            <input type="number" name="Consulenze" step="0.01" class="form-control cost-input"
              value="{{ old('Consulenze', $record->Consulenze) }}">
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-6">
            <label class="form-label">Totale</label>
            <input type="number" step="0.01" class="form-control" id="Totale"
              value="{{ number_format((float)$record->Totale, 2, '.', '') }}" readonly>
          </div>
        </div>

        {{-- === Ripartizione percentuali per mansioni (solo se più di una qualifica) === --}}
        @if(($qualifiche->count() ?? 0) > 1)
          <hr>
          <h5 class="mb-3">Ripartizione costo per mansione (%)</h5>

          @error('percentuali')
            <div class="alert alert-danger">{{ $message }}</div>
          @enderror

          <table class="table table-bordered align-middle mb-2">
            <thead>
              <tr>
                <th style="width:60%">Mansione</th>
                <th style="width:40%">Percentuale (%)</th>
              </tr>
            </thead>
            <tbody id="tbody-percentuali">
              @foreach($qualifiche as $q)
              <tr data-qualifica-id="{{ $q->id }}">
                <td>{{ $q->nome }}</td>
                <td>
                  <input
                    type="number"
                    name="percentuali[{{ $q->id }}]"
                    class="form-control percent-input"
                    min="0" max="100" step="0.01"
                    value="{{ old('percentuali.'.$q->id, $percentuali[$q->id] ?? 0) }}"
                  >
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>

          <div class="alert alert-info d-none" id="percentWarning">
            La somma delle percentuali deve essere esattamente 100%.
          </div>

          {{-- ANTEPRIMA RIPARTIZIONE COSTI --}}
          <h6 class="mt-4">Anteprima ripartizione costi per mansione</h6>
          <div class="table-responsive">
            <table class="table table-striped-anpas table-bordered mb-4" id="anteprima-table">
              <thead class="thead-anpas">
                <tr>
                  <th>Mansione</th>
                  <th class="text-end">Retribuzioni</th>
                  <th class="text-end">Oneri Sociali Inps</th>
                  <th class="text-end">Oneri Sociali Inail</th>
                  <th class="text-end">TFR</th>
                  <th class="text-end">Consulenze</th>
                  <th class="text-end">Totale</th>
                </tr>
              </thead>
              <tbody>
                @foreach($qualifiche as $q)
                <tr data-anteprima-id="{{ $q->id }}">
                  <td>{{ $q->nome }}</td>
                  <td class="text-end" data-col="retribuzioni">0.00</td>
                  <td class="text-end" data-col="OneriSocialiInps">0.00</td>                  
                  <td class="text-end" data-col="OneriSocialiInail">0.00</td>
                  <td class="text-end" data-col="tfr">0.00</td>
                  <td class="text-end" data-col="consulenze">0.00</td>
                  <td class="text-end fw-bold" data-col="totale">0.00</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Salva Modifiche
          </button>
          <a href="{{ route('ripartizioni.personale.costi.index') }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
  const inputs = document.querySelectorAll('.cost-input');
  const totalEl = document.getElementById('Totale');
  const percentInputs = document.querySelectorAll('.percent-input');
  const warning = document.getElementById('percentWarning');
  const anteprimaBody = document.querySelector('#anteprima-table tbody');

  function toNum(v) {
    if (typeof v === 'string') v = v.replace(',', '.');
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }
  function fix2(n) { return (Math.round(n * 100) / 100).toFixed(2); }

  function getCosti() {
    const retribuzioni = toNum(document.querySelector('input[name="Retribuzioni"]').value);
    const OneriSocialiInps        = toNum(document.querySelector('input[name="OneriSocialiInps"]').value);
    const OneriSocialiInail        = toNum(document.querySelector('input[name="OneriSocialiInail"]').value);
    const tfr          = toNum(document.querySelector('input[name="TFR"]').value);
    const consulenze   = toNum(document.querySelector('input[name="Consulenze"]').value);
    return { retribuzioni, OneriSocialiInps, OneriSocialiInail, tfr, consulenze };
  }

  function recalcTot() {
    const c = getCosti();
    const sum = c.retribuzioni + c.OneriSocialiInps + c.OneriSocialiInail + c.tfr + c.consulenze;
    if (totalEl) totalEl.value = fix2(sum);
    // aggiorna anche l'anteprima quando cambiano i costi
    recalcAnteprima();
  }

  function recalcWarning() {
    if (!percentInputs.length || !warning) return true;
    let sum = 0;
    percentInputs.forEach(i => sum += toNum(i.value));
    const ok = Math.abs(sum - 100) <= 0.01;
    warning.classList.toggle('d-none', ok);
    return ok;
  }

  // se solo 2 mansioni, l'altra si auto-completa a 100 - x
  function wireAutoMirrorIfTwo() {
    if (percentInputs.length !== 2) return;
    const a = percentInputs[0];
    const b = percentInputs[1];

    function mirror(source, target) {
      let val = toNum(source.value);
      if (val < 0) val = 0;
      if (val > 100) val = 100;
      source.value = fix2(val);
      target.value = fix2(Math.max(0, 100 - val));
      recalcWarning();
      recalcAnteprima();
    }

    a.addEventListener('input', () => mirror(a, b));
    a.addEventListener('change', () => mirror(a, b));
    b.addEventListener('input', () => mirror(b, a));
    b.addEventListener('change', () => mirror(b, a));

    // normalizza iniziale
    mirror(a, b);
  }

  // Aggiorna la tabella "Anteprima ripartizione"
  function recalcAnteprima() {
    if (!anteprimaBody) return;

    const c = getCosti();
    // mappa qualificaId -> percentuale (0..100)
    const percs = {};
    percentInputs.forEach(i => {
      const tr = i.closest('tr');
      const id = tr ? tr.getAttribute('data-qualifica-id') : null;
      if (id) percs[id] = toNum(i.value);
    });

    // per ogni riga anteprima, calcolo
    anteprimaBody.querySelectorAll('tr').forEach(tr => {
      const id = tr.getAttribute('data-anteprima-id');
      const p = (percs[id] || 0) / 100;

      const r = c.retribuzioni * p;
      const o1 = c.OneriSocialiInps        * p;
      const o2 = c.OneriSocialiInail        * p;
      const t = c.tfr          * p;
      const s = c.consulenze   * p;
      const tot = r + o1 + o2 + t + s;

      tr.querySelector('[data-col="retribuzioni"]').textContent = fix2(r);
      tr.querySelector('[data-col="OneriSocialiInps"]').textContent        = fix2(o1);
      tr.querySelector('[data-col="OneriSocialiInail"]').textContent        = fix2(o2);
      tr.querySelector('[data-col="tfr"]').textContent          = fix2(t);
      tr.querySelector('[data-col="consulenze"]').textContent   = fix2(s);
      tr.querySelector('[data-col="totale"]').textContent       = fix2(tot);
    });
  }

  // listeners
  inputs.forEach(i => { i.addEventListener('input', recalcTot); i.addEventListener('change', recalcTot); });
  percentInputs.forEach(i => { i.addEventListener('input', () => { recalcWarning(); recalcAnteprima(); }); i.addEventListener('change', () => { recalcWarning(); recalcAnteprima(); }); });

  // init
  recalcTot();
  recalcWarning();
  wireAutoMirrorIfTwo();
  recalcAnteprima();
})();
</script>
@endpush
