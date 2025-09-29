{{-- resources/views/distinta_imputazione_costi/create.blade.php --}}
@extends('layouts.app')

@php
  $anno = session('anno_riferimento', now()->year);
  // priorità: old -> querystring -> session
  $preselectConvenzione = old('idConvenzione')
      ?? request('idConvenzione')
      ?? session('convenzione_selezionata');
@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Aggiungi Costi Diretti</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('distinta.imputazione.store') }}" method="POST" novalidate>
        @csrf

        <input type="hidden" name="idSezione" value="{{ $sezione }}">

        {{-- Associazione e Anno --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Associazione</label>
            <input type="text" class="form-control" value="{{ $associazione }}" disabled>
            <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anno</label>
            <input type="text" class="form-control" value="{{ $anno }}" disabled>
            <input type="hidden" name="idAnno" value="{{ $anno }}">
          </div>
        </div>

        {{-- Convenzione --}}
        <div class="mb-3">
          <label for="idConvenzione" class="form-label">Convenzione</label>
          <select
            name="idConvenzione"
            id="idConvenzione"
            class="form-select @error('idConvenzione') is-invalid @enderror"
            required
          >
            <option value="">-- Seleziona --</option>
            @foreach($convenzioni as $conv)
              <option
                value="{{ $conv->idConvenzione }}"
                {{ (string)$preselectConvenzione === (string)$conv->idConvenzione ? 'selected' : '' }}
              >
                {{ $conv->Convenzione }}
              </option>
            @endforeach
          </select>
          @error('idConvenzione')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Voce (da riepilogo_voci_config) --}}
        <div class="mb-3">
          <label for="idVoceConfig" class="form-label">Voce</label>
          <select
            name="idVoceConfig"
            id="idVoceConfig"
            class="form-select @error('idVoceConfig') is-invalid @enderror"
            required
          >
            <option value="">-- Seleziona --</option>
            @foreach($vociDisponibili as $voce)
              <option
                value="{{ $voce->id }}"
                {{ (string)old('idVoceConfig') === (string)$voce->id ? 'selected' : '' }}
              >
                {{ $voce->descrizione }}
              </option>
            @endforeach
          </select>
          @error('idVoceConfig')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Importi --}}
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="costo" class="form-label">Importo Costo Diretto (€)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control @error('costo') is-invalid @enderror"
              name="costo"
              id="costo"
              value="{{ old('costo') }}"
              placeholder="0,00"
            >
            @error('costo')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label for="ammortamento" class="form-label">Ammortamento (€)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control @error('ammortamento') is-invalid @enderror"
              name="ammortamento"
              id="ammortamento"
              value="{{ old('ammortamento') }}"
              placeholder="0,00"
            >
            @error('ammortamento')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>


        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-3">
            <i class="fas fa-check me-1"></i> Salva
          </button>
          <a href="{{ route('distinta.imputazione.index') }}" class="btn btn-secondary">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // --- Dati dal controller ---
  // bilanci: { "idVoceConfig": number }
  // esistenti: { "idVoceConfig": { "idConvenzione": { costo: number, ammortamento: number } } }
  const bilanciRaw   = @json($bilancioPerVoce ?? []);
  const esistentiRaw = @json($esistenti ?? (object)[]);
  
console.log(bilanciRaw, esistentiRaw);
  // Normalizzo chiavi a stringa
  const toStringKeys = (obj) => {
    const out = {};
    Object.keys(obj || {}).forEach(k => {
      const v = obj[k];
      if (v && typeof v === 'object' && !Array.isArray(v)) {
        out[String(k)] = toStringKeys(v); // ricorsivo
      } else {
        out[String(k)] = v;
      }
    });
    return out;
  };

  const bilanci   = toStringKeys(bilanciRaw);
  const esistenti = toStringKeys(esistentiRaw);

  // --- Helpers DOM ---
  const $voce = document.getElementById('idVoceConfig');
  const $conv = document.getElementById('idConvenzione');

  function setMoney(el, val) {
    if (!el) return;
    const n = Number(val);
    el.value = Number.isFinite(n) ? n.toFixed(2) : '';
  }

  function getNested(o, k1, k2) {
    const a = o && Object.prototype.hasOwnProperty.call(o, String(k1)) ? o[String(k1)] : undefined;
    if (!a) return undefined;
    return a[String(k2)];
  }

  function applySuggerimenti() {
    const voceId = ($voce && $voce.value) ? String($voce.value) : '';
    const convId = ($conv && $conv.value) ? String($conv.value) : '';

    // 1) Bilancio “calcolato” per voce (solo display)
    const bil = Object.prototype.hasOwnProperty.call(bilanci, voceId) ? bilanci[voceId] : '';
    setMoney(document.getElementById('bilancio_consuntivo_view'), bil);

    // 2) Prefill costo/ammortamento se esiste già una riga salvata per (voce, convenzione)
    const riga = voceId && convId ? getNested(esistenti, voceId, convId) : undefined;

    if (riga) {
      setMoney(document.getElementById('costo'),        riga.costo);
      setMoney(document.getElementById('ammortamento'), riga.ammortamento);
    } else {
      setMoney(document.getElementById('costo'),        '');
      setMoney(document.getElementById('ammortamento'), '');
    }
  }

  // Aggiorna quando cambia o si digita (per UI che “prefillano” da tastiera)
  ['change','input'].forEach(ev => {
    $voce && $voce.addEventListener(ev, applySuggerimenti);
    $conv && $conv.addEventListener(ev, applySuggerimenti);
  });

  // Al load (copre il caso con convenzione/voce già selezionate)
  document.addEventListener('DOMContentLoaded', applySuggerimenti);
  // Esegui anche subito nel caso la sezione scripts venga resa dopo il DOM
  applySuggerimenti();
</script>
@endpush
