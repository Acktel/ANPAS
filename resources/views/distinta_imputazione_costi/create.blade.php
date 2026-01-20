@extends('layouts.app')

@php
  // fallback per sicurezza
  $anno = $anno ?? (int) session('anno_riferimento', now()->year);

  $preselectConvenzione = old('idConvenzione')
      ?? request('idConvenzione')
      ?? session('convenzione_selezionata');

  $sezioniMap = [
    2 => 'Automezzi',
    3 => 'Attrezzatura Sanitaria',
    4 => 'Telecomunicazioni',
    5 => 'Costi gestione struttura',
    6 => 'Costo del personale',
    7 => 'Materiale sanitario di consumo',
    8 => 'Costi amministrativi',
    9 => 'Quote di ammortamento',
    10 => 'Beni Strumentali < 516,00 €',
  ];
  $sezioneLabel = $sezioniMap[$sezione] ?? "Sezione $sezione";
@endphp

@section('content')
<div class="container-fluid">

  {{-- Header pagina --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="container-title mb-2 mb-md-0">
      Costi diretti — {{ $sezioneLabel }}
    </h1>
    <div class="d-flex gap-2">
      <span class="badge rounded-pill text-bg-secondary">
        Associazione: {{ $associazione }}
      </span>
      <span class="badge rounded-pill text-bg-primary">
        Anno: {{ $anno }}
      </span>
    </div>
  </div>

  {{-- Card con select convenzione + link bilancio sezione --}}
  <div class="card-anpas mb-3">
    <div class="card-body bg-anpas-white">
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label for="idConvenzione" class="form-label">Convenzione</label>
          <select
            id="idConvenzione"
            class="form-select @error('idConvenzione') is-invalid @enderror"
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
          @error('idConvenzione') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <small class="text-muted">La pagina si aggiorna per precompilare i campi della convenzione scelta.</small>
        </div>

        <div class="col-md-4 text-md-end">
          @if(in_array($sezione, [5,8], true))
            <a
              href="{{ route('distinta.imputazione.editBilancio', ['sezione' => $sezione, 'idAssociazione' => $idAssociazione]) }}"
              class="btn btn-outline-secondary"
              title="Modifica solo gli importi da bilancio della sezione"
            >
              <i class="fas fa-pen me-1"></i> Modifica Importi da Bilancio
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- FORM BULK --}}
  <form action="{{ route('distinta.imputazione.storeBulk') }}" method="POST" novalidate>
    @csrf
    <input type="hidden" name="idSezione" value="{{ $sezione }}">
    <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
    <input type="hidden" name="idAnno" value="{{ $anno }}">
    {{-- IMPORTANTE: passiamo anche la convenzione selezionata al submit --}}
    <input type="hidden" name="idConvenzione" id="idConvenzioneHidden" value="{{ $preselectConvenzione }}">

    <div class="card-anpas">
      <div class="card-body bg-anpas-white">
        <div class="table-responsive">
          <table class="table table-bordered table-striped-anpas align-middle">
            <thead class="thead-anpas">
              <tr>
                <th style="width:36%">Voce</th>
                <th class="text-end" style="width:18%">Importo Costo Diretto (€)</th>
                <th class="text-end" style="width:18%">Sconto (€)</th>
                <th style="width:20%">Note</th>
                <th class="text-end" style="width:14%">Indiretti (solo display)</th>
              </tr>
            </thead>
            <tbody>
              @php
                $convSel    = (string) ($preselectConvenzione ?? '');
                $esist      = $esistenti ?? [];
                $indMap     = $indirettiByVoceByConv ?? []; // [idVoce][idConv] => € indiretti
              @endphp

              @foreach($vociDisponibili as $voce)
                @php
                  $byVoce = $esist[(int)$voce->id] ?? [];
                  $pref   = ($convSel !== '' && isset($byVoce[(int)$convSel]))
                              ? $byVoce[(int)$convSel]
                              : ['costo'=>null,'ammortamento'=>null,'note'=>null];

                  $oldCosto = old("righe.{$voce->id}.costo", $pref['costo']);
                  $oldAmm   = old("righe.{$voce->id}.ammortamento", $pref['ammortamento']);
                  $oldNote  = old("righe.{$voce->id}.note", $pref['note']);

                  $indView  = ($convSel !== '')
                              ? ($indMap[(int)$voce->id][(int)$convSel] ?? null)
                              : null;
                @endphp
                <tr>
                  <td>{{ $voce->descrizione }}</td>

                  <td>
                    <input
                      type="number" step="0.01" min="0"
                      class="form-control text-end"
                      name="righe[{{ $voce->id }}][costo]"
                      value="{{ $oldCosto !== null ? number_format((float)$oldCosto, 2, '.', '') : '' }}"
                      placeholder="0,00">
                  </td>

                  <td>
                    <input
                      type="number" step="0.01" min="0"
                      class="form-control text-end"
                      name="righe[{{ $voce->id }}][ammortamento]"
                      value="{{ $oldAmm !== null ? number_format((float)$oldAmm, 2, '.', '') : '' }}"
                      placeholder="0,00">
                  </td>

                  <td>
                    <input
                      type="text"
                      class="form-control"
                      name="righe[{{ $voce->id }}][note]"
                      value="{{ $oldNote !== null ? e($oldNote) : '' }}"
                      placeholder="Note...">
                  </td>

                  {{-- Indiretti readonly per la convenzione selezionata --}}
                  <td>
                    <input
                      type="text"
                      class="form-control text-end disabled"
                      value="{{ $indView !== null ? number_format((float)$indView, 2, '.', '') : '' }}"
                      placeholder="{{ $convSel === '' ? 'Seleziona convenzione' : '—' }}"
                      readonly>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Barra azioni sticky --}}
    <div class="savebar shadow-sm">
      <div class="container-fluid py-2 d-flex justify-content-end gap-2">
        <a href="{{ route('distinta.imputazione.index', ['idAssociazione' => $idAssociazione]) }}" class="btn btn-secondary">
          Annulla
        </a>
        <button type="submit" class="btn btn-anpas-green">
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
  // Cambio convenzione => ricarico con ?idConvenzione=... e mantengo idAssociazione
  const sel = document.getElementById('idConvenzione');
  const hidden = document.getElementById('idConvenzioneHidden');

  // sync iniziale (in caso di autocompilazioni strane)
  if (sel && hidden && !hidden.value) hidden.value = sel.value || '';

  sel?.addEventListener('change', function(){
    hidden.value = this.value || '';
    const url = new URL(window.location.href);
    url.searchParams.set('idConvenzione', this.value || '');
    url.searchParams.set('idAssociazione', '{{ (int)$idAssociazione }}');
    window.location = url.toString();
  });
</script>
@endpush
