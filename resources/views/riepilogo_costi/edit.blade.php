{{-- resources/views/riepilogo_costi/edit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-2">Modifica voce (Riepilogo Costi)</h1>

  <p class="text-muted mb-4">
    Anno <strong>{{ $anno }}</strong>
    — Associazione <strong>{{ $nomeAssociazione ?? ('#'.$idAssociazione) }}</strong>
    — Convenzione <strong>{{ $nomeConvenzione ?? ($idConvenzione === 'TOT' ? 'TOTALE' : '#'.$idConvenzione) }}</strong>
  </p>

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
  @endif

  @php
    $prev  = (float) ($preventivo ?? 0);
    $cons  = (float) ($consuntivo ?? 0);
    $scost = $prev != 0 ? round((($cons - $prev) / $prev) * 100, 2) : 0;
    $isTot = ($idConvenzione === 'TOT');
  @endphp

  @if($isTot)
    <div class="alert alert-warning">
      Per modificare il <strong>preventivo</strong> seleziona una convenzione specifica (non TOTALE).
      Il modulo è disabilitato.
    </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form id="editForm"
            action="{{ route('riepilogo.costi.update', $voceId) }}"
            method="POST"
            class="row g-3">
        @csrf
        @method('PUT')

        <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
        <input type="hidden" name="idConvenzione"  value="{{ $idConvenzione }}">

        <div class="col-12">
          <label class="form-label">Voce</label>
          <input type="text" class="form-control" value="{{ $voceDescrizione }}" readonly>
        </div>

        <div class="col-md-4">
          <label class="form-label">Preventivo</label>
          <input type="number"
                 name="preventivo"
                 step="0.01"
                 min="0"
                 class="form-control @error('preventivo') is-invalid @enderror js-preventivo"
                 value="{{ old('preventivo', $prev) }}"
                 {{ $isTot ? 'readonly' : '' }}>
          @error('preventivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label class="form-label">Consuntivo</label>
          <input type="text"
                 class="form-control js-consuntivo disabled"
                 value="{{ number_format($cons, 2, ',', '.') }}"
                 readonly>
          {{-- opzionale: se vuoi inviarlo comunque al controller, aggiungi un hidden numerico --}}
          {{-- <input type="hidden" name="consuntivo" value="{{ $cons }}"> --}}
        </div>
        <div class="col-12">
          <label class="form-label">Note</label>
          <textarea
            name="note"
            class="form-control"
            rows="2"
            placeholder="Note..."
            {{ $isTot ? 'readonly' : '' }}
          >{{ old('note', $note ?? '') }}</textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-anpas-green" {{ $isTot ? 'disabled' : '' }}>
            <i class="fas fa-check me-1"></i> Salva
          </button>
          <a href="{{ route('riepilogo.costi', ['idAssociazione' => $idAssociazione, 'idConvenzione' => $idConvenzione]) }}"
             class="btn btn-secondary ms-2">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const $prev = document.querySelector('.js-preventivo');
  const $cons = document.querySelector('.js-consuntivo');
  const $scos = document.querySelector('.js-scostamento');

  function parseNum(it){ return Number(String(it).replace(/\./g,'').replace(',', '.')) || 0; }

  function recalc(){
    if (!$prev || !$cons || !$scos) return;
    const prev = parseFloat($prev.value || 0);
    const cons = parseNum($cons.value || 0);
    const pct  = prev !== 0 ? ((cons - prev) / prev) * 100 : 0;
    $scos.value = (Math.round(pct * 100) / 100).toFixed(2) + '%';
  }

  $prev?.addEventListener('input', recalc);
});
</script>

  <script>
    (function () {
      // cerca prima un elemento con id, altrimenti prende il primo .alert.alert-success
      const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
      if (!flash) return;

      // aspetta 3500ms (3.5s) poi fa fade + collapse e rimuove l'elemento
      setTimeout(() => {
        // animazione: opacità + altezza
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
        flash.style.opacity = '0';
        // per lo "slide up" imposta max-height e padding a 0
        flash.style.maxHeight = flash.scrollHeight + 'px'; // inizializza
        // forza repaint per sicurezza
        // eslint-disable-next-line no-unused-expressions
        flash.offsetHeight;
        flash.style.maxHeight = '0';
        flash.style.paddingTop = '0';
        flash.style.paddingBottom = '0';
        flash.style.marginTop = '0';
        flash.style.marginBottom = '0';

        // rimuovi dal DOM dopo che l'animazione è finita
        setTimeout(() => {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 600); // lascia un po' di tempo alla transizione
      }, 3500);
    })();
  </script>
@endpush
