{{-- resources/views/mezzi_sostitutivi/edit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-2">Modifica costi orari — Mezzi sostitutivi</h1>

  <p class="text-muted mb-4">
    Anno <strong>{{ $anno }}</strong>
    — Convenzione <strong>{{ $nomeConvenzione ?? ('#'.$idConvenzione) }}</strong>
    @if(isset($percTitolare))
      — KM mezzo titolare <strong>{{ number_format($percTitolare, 2, ',', '.') }}%</strong>
    @endif
  </p>

  @if(session('success')) <div class="alert alert-success" id="flash-message">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger"  id="flash-message">{{ session('error') }}</div>  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
  @endif

  @unless($isSostitutivi ?? true)
    <div class="alert alert-warning">
      Questa convenzione <strong>non</strong> è in regime <em>Mezzi sostitutivi</em>
      (flag disattivo o KM mezzo titolare &lt; 98%). Il salvataggio è disabilitato.
    </div>
  @endunless

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('mezzi_sostitutivi.salva') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="idConvenzione" value="{{ $idConvenzione }}">
        <input type="hidden" name="idAnno" value="{{ $anno }}">

        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="costo_fascia_oraria" class="form-label">Costo per fascia oraria (€)</label>
            <input
              type="number"
              id="costo_fascia_oraria"
              name="costo_fascia_oraria"
              class="form-control text-end @error('costo_fascia_oraria') is-invalid @enderror"
              step="0.01" min="0" inputmode="decimal" placeholder="0,00"
              value="{{ old('costo_fascia_oraria', isset($costo) ? number_format((float)$costo, 2, '.', '') : '0.00') }}"
              {{ ($isSostitutivi ?? true) ? '' : 'disabled' }}
            >
            @error('costo_fascia_oraria') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="text-muted">
              Valorizza solo se la convenzione è in regime <strong>Mezzi sostitutivi</strong> (KM titolare ≥ 98%).
            </small>
          </div>

          <div class="col-12 mt-3">
            <a href="{{ route('riepilogo.costi') }}" class="btn btn-secondary">Annulla</a>
            <button class="btn btn-anpas-green ms-2" {{ ($isSostitutivi ?? true) ? '' : 'disabled' }}>
              <i class="fas fa-check me-1"></i> Salva
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const flash = document.getElementById('flash-message');
  if (!flash) return;
  setTimeout(() => {
    flash.style.transition = 'opacity .5s ease, max-height .5s ease, padding .4s, margin .4s';
    flash.style.opacity = '0';
    flash.style.maxHeight = flash.scrollHeight + 'px';
    flash.offsetHeight;
    flash.style.maxHeight = '0';
    flash.style.paddingTop = '0';
    flash.style.paddingBottom = '0';
    flash.style.marginTop = '0';
    flash.style.marginBottom = '0';
    setTimeout(() => flash.remove(), 650);
  }, 3500);
})();
</script>
@endpush
