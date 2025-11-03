{{-- resources/views/riepilogo_costi/edit_preventivi_sezione.blade.php --}}
@extends('layouts.app')

@php
  $labels = [
    2=>'Automezzi', 3=>'Attrezzatura Sanitaria', 4=>'Telecomunicazioni',
    5=>'Costi gestione struttura', 6=>'Costo del personale',
    7=>'Materiale sanitario di consumo', 8=>'Costi amministrativi',
    9=>'Quote di ammortamento', 10=>'Beni Strumentali < 516,00 €',
    11=> 'Altri costi' 
  ];
  $titoloSezione = $sezioneLabel ?? ($labels[$sezione] ?? "Sezione $sezione");
  $nomeAssociazione = \App\Models\Associazione::getById($idAssociazione)->Associazione ?? ('#'.$idAssociazione);
@endphp

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="container-title mb-2 mb-md-0">
      Modifica Preventivi — {{ $titoloSezione }}
    </h1>

    <div class="d-flex gap-2">
      <span class="badge rounded-pill text-bg-primary">Anno: {{ $anno }}</span>
    </div>
  </div>
      <p class="text-muted mb-4">
    Anno <strong>{{ $anno }}</strong>
    — Associazione <strong>{{ $nomeAssociazione }}</strong>

  </p>
  {{-- Selezione convenzione --}}
  <div class="card-anpas mb-3">
    <div class="card-body bg-anpas-white">
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label for="idConvenzione" class="form-label">Convenzione</label>
          <select id="idConvenzione" class="form-select">
            <option value="">-- Seleziona --</option>
            @foreach($convenzioni as $conv)
              <option value="{{ $conv->idConvenzione }}"
                {{ (string)($idConvenzione ?? '') === (string)$conv->idConvenzione ? 'selected' : '' }}>
                {{ $conv->Convenzione }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">Seleziona la convenzione per cui modificare i preventivi.</small>
        </div>
      </div>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success" id="flash-message">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- FORM bulk preventivi --}}
  <form
    action="{{ route('riepilogo.costi.updatePreventiviSezione', ['sezione' => $sezione]) }}"
    method="POST"
    id="bulkPreventiviForm"
    novalidate
  >
    @csrf
    <input type="hidden" name="idAssociazione" value="{{ (int)$idAssociazione }}">
    <input type="hidden" name="idAnno"         value="{{ (int)$anno }}">
    <input type="hidden" name="idConvenzione"  id="idConvenzioneHidden" value="{{ (string)($idConvenzione ?? '') }}">

    <div class="card-anpas">
      <div class="card-body bg-anpas-white">
        <div class="table-responsive">
          <table class="table table-bordered table-striped-anpas align-middle">
            <thead class="thead-anpas">
              <tr>
                <th style="width:50%">Voce</th>
                <th class="text-end" style="width:25%">Preventivo (€)</th>
                <th class="text-end" style="width:25%">Consuntivo (€)</th>
              </tr>
            </thead>
            <tbody>
              @forelse($voci as $voce)
                @php
                  $prevVal = $preventivi[$voce->id] ?? null;            // float o null
                  $consVal = $indirettiByVoce[$voce->id] ?? null;       // float o null
                  $prevStr = $prevVal !== null ? number_format((float)$prevVal, 2, ',', '.') : '';
                  $consStr = $consVal !== null ? number_format((float)$consVal, 2, ',', '.') : '';
                @endphp
                <tr>
                  <td>{{ $voce->descrizione }}</td>

                  {{-- PREVENTIVO (editabile: accetta virgola) --}}
                  <td>
                    <input
                      type="text" inputmode="decimal"
                      name="righe[{{ (int)$voce->id }}][preventivo]"
                      class="form-control text-end"
                      value="{{ $prevStr }}"
                      placeholder="0,00"
                      {{ $idConvenzione ? '' : 'disabled' }}
                    >
                  </td>

                  {{-- CONSUNTIVO (readonly) --}}
                  <td>
                    <input
                      type="text"
                      class="form-control text-end"
                      value="{{ $consStr }}"
                      placeholder="{{ $idConvenzione ? '0,00' : 'Seleziona convenzione' }}"
                      readonly
                    >
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="3" class="text-center text-muted">
                    Nessuna voce disponibile per questa sezione.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Barra azioni sticky --}}
    <div class="savebar shadow-sm">
      <div class="container-fluid py-2 d-flex justify-content-end gap-2">
        <a href="{{ route('riepilogo.costi') }}" class="btn btn-secondary">Annulla</a>
        <button type="submit" class="btn btn-anpas-green" {{ $idConvenzione ? '' : 'disabled' }}>
          <i class="fas fa-check me-1"></i> Salva tutto
        </button>
      </div>
    </div>
  </form>
</div>
@endsection

@push('styles')
<style>
  .savebar {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 1px solid rgba(0,0,0,.1);
    z-index: 1020;
  }
  .thead-anpas th { vertical-align: middle; }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const sel    = document.getElementById('idConvenzione');
  const hidden = document.getElementById('idConvenzioneHidden');
  const form   = document.getElementById('bulkPreventiviForm');

  // sync iniziale
  if (sel && hidden && !hidden.value) hidden.value = sel.value || '';

  // cambio convenzione => reload con query
  sel?.addEventListener('change', function(){
    const v = this.value || '';
    hidden.value = v;
    const url = new URL(window.location.href);
    url.searchParams.set('idConvenzione', v);
    url.searchParams.set('idAssociazione', '{{ (int)$idAssociazione }}');
    window.location = url.toString();
  });

  // blocca submit senza convenzione
  form?.addEventListener('submit', function(e){
    if (!hidden.value) {
      e.preventDefault();
      alert('Seleziona una convenzione per salvare i preventivi.');
    }
  });

  // fade-out flash
  (function () {
    const flash = document.getElementById('flash-message');
    if (!flash) return;
    setTimeout(() => {
      flash.style.transition = 'opacity .5s ease, max-height .5s ease, padding .4s ease, margin .4s ease';
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
})();
</script>
@endpush
