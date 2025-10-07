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

        {{-- Gruppi base + diretto affiancati --}}
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label">Retribuzioni (base)</label>
            <input type="number" name="Retribuzioni" step="0.01" class="form-control cost-input"
              value="{{ old('Retribuzioni', $record->Retribuzioni) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Retribuzioni (costo diretto)</label>
            <input type="number" name="costo_diretto_Retribuzioni" step="0.01" class="form-control cost-input"
              value="{{ old('costo_diretto_Retribuzioni', $record->costo_diretto_Retribuzioni ?? 0) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">Oneri Sociali INPS (base)</label>
            <input type="number" name="OneriSocialiInps" step="0.01" class="form-control cost-input"
              value="{{ old('OneriSocialiInps', $record->OneriSocialiInps) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Oneri Sociali INPS (costo diretto)</label>
            <input type="number" name="costo_diretto_OneriSocialiInps" step="0.01" class="form-control cost-input"
              value="{{ old('costo_diretto_OneriSocialiInps', $record->costo_diretto_OneriSocialiInps ?? 0) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">Oneri Sociali INAIL (base)</label>
            <input type="number" name="OneriSocialiInail" step="0.01" class="form-control cost-input"
              value="{{ old('OneriSocialiInail', $record->OneriSocialiInail) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Oneri Sociali INAIL (costo diretto)</label>
            <input type="number" name="costo_diretto_OneriSocialiInail" step="0.01" class="form-control cost-input"
              value="{{ old('costo_diretto_OneriSocialiInail', $record->costo_diretto_OneriSocialiInail ?? 0) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">TFR (base)</label>
            <input type="number" name="TFR" step="0.01" class="form-control cost-input"
              value="{{ old('TFR', $record->TFR) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">TFR (costo diretto)</label>
            <input type="number" name="costo_diretto_TFR" step="0.01" class="form-control cost-input"
              value="{{ old('costo_diretto_TFR', $record->costo_diretto_TFR ?? 0) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">Consulenze (base)</label>
            <input type="number" name="Consulenze" step="0.01" class="form-control cost-input"
              value="{{ old('Consulenze', $record->Consulenze) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Consulenze (costo diretto)</label>
            <input type="number" name="costo_diretto_Consulenze" step="0.01" class="form-control cost-input"
              value="{{ old('costo_diretto_Consulenze', $record->costo_diretto_Consulenze ?? 0) }}">
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-12">
            <small class="text-muted">Il totale è calcolato come somma di tutte le voci <strong>(base + costo diretto)</strong>.</small>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-4">
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
                  type="text"
                  inputmode="decimal"
                  autocomplete="off"
                  name="percentuali[{{ $q->id }}]"
                  class="form-control percent-input"
                  data-min="0"
                  data-max="100"
                  data-decimals="2"
                  value="{{ old('percentuali.'.$q->id, $percentuali[$q->id] ?? '') }}">
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
  const totalEl = document.getElementById('Totale');
  const percentInputs = document.querySelectorAll('.percent-input');
  const warning = document.getElementById('percentWarning');
  const anteprimaBody = document.querySelector('#anteprima-table tbody');

  // considera anche i "costo_diretto_*"
  const selectors = [
    'input[name="Retribuzioni"]',
    'input[name="costo_diretto_Retribuzioni"]',
    'input[name="OneriSocialiInps"]',
    'input[name="costo_diretto_OneriSocialiInps"]',
    'input[name="OneriSocialiInail"]',
    'input[name="costo_diretto_OneriSocialiInail"]',
    'input[name="TFR"]',
    'input[name="costo_diretto_TFR"]',
    'input[name="Consulenze"]',
    'input[name="costo_diretto_Consulenze"]',
  ];
  const inputs = document.querySelectorAll(selectors.join(','));

  // ===== parsing/formatting permissivo per percentuali =====
  function parseDecimalLoose(v) {
    if (v == null) return null;
    const s = String(v).trim().replace(/\s+/g, '').replace(',', '.');
    if (s === '' || s === '-' || s === '.' || s === '-.') return null; // stato intermedio
    const n = Number(s);
    return Number.isFinite(n) ? n : null;
  }
  function formatFixed(n, decimals = 2) {
    const p = Math.pow(10, decimals);
    return (Math.round(n * p) / p).toFixed(decimals);
  }
  function clampAndFormatInput(el) {
    const min = parseDecimalLoose(el.dataset?.min);
    const max = parseDecimalLoose(el.dataset?.max);
    const dec = Number(el.dataset?.decimals || 2);
    let n = parseDecimalLoose(el.value);

    if (n == null) { el.value = ''; return 0; }
    if (min != null && n < min) n = min;
    if (max != null && n > max) n = max;
    el.value = formatFixed(n, dec);
    return n;
  }

  // ===== utilità numeriche per i calcoli esistenti =====
  function toNum(v) {
    const n = parseDecimalLoose(v);
    return n == null ? 0 : n;
  }
  function fix2(n) { return (Math.round(n * 100) / 100).toFixed(2); }

  // ===== calcolo costi base + diretto =====
  function getCostiSommaBaseDiretto() {
    const retr  = toNum(document.querySelector('input[name="Retribuzioni"]')?.value);
    const retrD = toNum(document.querySelector('input[name="costo_diretto_Retribuzioni"]')?.value);

    const inps  = toNum(document.querySelector('input[name="OneriSocialiInps"]')?.value);
    const inpsD = toNum(document.querySelector('input[name="costo_diretto_OneriSocialiInps"]')?.value);

    const inail  = toNum(document.querySelector('input[name="OneriSocialiInail"]')?.value);
    const inailD = toNum(document.querySelector('input[name="costo_diretto_OneriSocialiInail"]')?.value);

    const tfr   = toNum(document.querySelector('input[name="TFR"]')?.value);
    const tfrD  = toNum(document.querySelector('input[name="costo_diretto_TFR"]')?.value);

    const cons  = toNum(document.querySelector('input[name="Consulenze"]')?.value);
    const consD = toNum(document.querySelector('input[name="costo_diretto_Consulenze"]')?.value);

    return {
      retribuzioni: retr + retrD,
      OneriSocialiInps: inps + inpsD,
      OneriSocialiInail: inail + inailD,
      tfr: tfr + tfrD,
      consulenze: cons + consD,
    };
  }

  function recalcTotale() {
    const c = getCostiSommaBaseDiretto();
    const sum = c.retribuzioni + c.OneriSocialiInps + c.OneriSocialiInail + c.tfr + c.consulenze;
    if (totalEl) totalEl.value = fix2(sum);
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

  // ===== Anteprima: applica percentuali ai costi =====
  function recalcAnteprima() {
    if (!anteprimaBody) return;

    const c = getCostiSommaBaseDiretto();
    const percs = {};
    percentInputs.forEach(i => {
      const tr = i.closest('tr');
      const id = tr ? tr.getAttribute('data-qualifica-id') : null;
      if (id) percs[id] = toNum(i.value);
    });

    anteprimaBody.querySelectorAll('tr').forEach(tr => {
      const id = tr.getAttribute('data-anteprima-id');
      const p = ((percs[id] || 0) / 100);

      const r  = c.retribuzioni * p;
      const o1 = c.OneriSocialiInps * p;
      const o2 = c.OneriSocialiInail * p;
      const t  = c.tfr * p;
      const s  = c.consulenze * p;
      const tot = r + o1 + o2 + t + s;

      tr.querySelector('[data-col="retribuzioni"]').textContent = fix2(r);
      tr.querySelector('[data-col="OneriSocialiInps"]').textContent = fix2(o1);
      tr.querySelector('[data-col="OneriSocialiInail"]').textContent = fix2(o2);
      tr.querySelector('[data-col="tfr"]').textContent = fix2(t);
      tr.querySelector('[data-col="consulenze"]').textContent = fix2(s);
      tr.querySelector('[data-col="totale"]').textContent = fix2(tot);
    });
  }

  // ===== Auto-mirror solo con 2 mansioni, e solo a fine digitazione =====
  function wireAutoMirrorIfTwo() {
    if (percentInputs.length !== 2) return;
    const [a, b] = percentInputs;

    function mirror(source, target) {
      clampAndFormatInput(source);
      const v = parseDecimalLoose(source.value);
      if (v == null) { target.value = ''; }
      else {
        const x = Math.max(0, Math.min(100, v));
        const rest = Math.max(0, 100 - x);
        target.value = formatFixed(rest, Number(target.dataset?.decimals || 2));
      }
      recalcWarning();
      recalcAnteprima();
    }

    const finA = () => mirror(a, b);
    const finB = () => mirror(b, a);
    a.addEventListener('blur', finA);
    a.addEventListener('change', finA);
    b.addEventListener('blur', finB);
    b.addEventListener('change', finB);

    // normalizza una tantum all'avvio (senza disturbare la digitazione)
    mirror(a, b);
  }

  // ===== listeners =====
  inputs.forEach(i => {
    i.addEventListener('input', recalcTotale);
    i.addEventListener('change', recalcTotale);
  });

  percentInputs.forEach(i => {
    // durante input: solo ricalcoli, nessun clamp/format
    i.addEventListener('input', () => { recalcWarning(); recalcAnteprima(); });
    // a fine input: clamp + format
    const onFinish = () => { clampAndFormatInput(i); recalcWarning(); recalcAnteprima(); };
    i.addEventListener('blur', onFinish);
    i.addEventListener('change', onFinish);
  });

  // ===== init =====
  recalcTotale();
  // normalizza iniziale (se arrivano valori già sporchi)
  percentInputs.forEach(i => clampAndFormatInput(i));
  recalcWarning();
  wireAutoMirrorIfTwo();
  recalcAnteprima();
})();
</script>
@endpush
