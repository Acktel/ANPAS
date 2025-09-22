{{-- resources/views/riepiloghi/edit_voce_totale.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-2">
    Modifica TOTALE — {{ $voceDescrizione ?? ('Voce #'.$voceId) }}
  </h1>
  <p class="text-muted mb-4">
    Associazione #{{ $riepilogo->idAssociazione }} — Anno {{ $riepilogo->idAnno }}
  </p>

  {{-- messaggi flash --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- errori validazione --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('riepiloghi.voce.applyTot') }}" method="POST" class="row g-3">
        @csrf

        <input type="hidden" name="idRiepilogo" value="{{ $riepilogo->idRiepilogo }}">
        <input type="hidden" name="idVoce" value="{{ $voceId }}">

        <div class="col-md-6">
          <label class="form-label">Preventivo (replicato su <strong>tutte</strong> le convenzioni)</label>
          <input
            type="number"
            name="preventivo"
            step="1.00"
            min="0"
            class="form-control @error('preventivo') is-invalid @enderror"
            value="{{ old('preventivo', $preventivoSuggerito ?? 0) }}"
            required
          >
          @error('preventivo')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">
            Salvando, questo valore sarà scritto/aggiornato su ogni convenzione della stessa associazione e anno.
          </small>
        </div>

        {{-- Se vuoi gestire anche il consuntivo lato controller, abilita questo campo e valida di conseguenza
        <div class="col-md-6">
          <label class="form-label">Consuntivo (opzionale)</label>
          <input
            type="number"
            name="consuntivo"
            step="0.01"
            min="0"
            class="form-control @error('consuntivo') is-invalid @enderror"
            value="{{ old('consuntivo') }}"
          >
          @error('consuntivo')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
        --}}

        <div class="col-12">
          <button class="btn btn-anpas-green">
            <i class="fas fa-check me-1"></i> Applica a tutte le convenzioni
          </button>
          <a href="{{ route('riepiloghi.index') }}" class="btn btn-secondary ms-2">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
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