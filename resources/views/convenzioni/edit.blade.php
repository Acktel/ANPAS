@extends('layouts.app')

@php
  $user = Auth::user();
  $isImpersonating = session()->has('impersonate');
  $annoCorr = session('anno_riferimento', now()->year);
  $assoCorr = $associazioni->firstWhere('idAssociazione', $conv->idAssociazione);

@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Modifica Convenzione
  </h1>
  <p class="text-muted mb-4">
    Associazione #{{ $assoCorr->Associazione }} — Anno {{ $conv->idAnno }}
  </p>

  {{-- Errori validazione --}}
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('convenzioni.update', $conv->idConvenzione) }}" method="POST">
        @csrf
        @method('PUT')

       <input type="hidden" name="idAssociazione" value="{{ $assoCorr->idAssociazione ?? $conv->idAssociazione }}">
       <input type="hidden" name="idAnno" value="{{ $conv->idAnno }}">
        {{-- Associazione e Anno --}}

        {{-- Descrizione --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Descrizione</label>
            <input type="text"
              style="text-transform: uppercase;"
              name="Convenzione"
              class="form-control"
              value="{{ old('Convenzione', $conv->Convenzione) }}"
              required>
          </div>
        </div>

        {{-- Aziende Sanitarie e flag materiale fornito --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="aziende_sanitarie" class="form-label">Aziende Sanitarie associate</label>
            <select name="aziende_sanitarie[]" id="aziende_sanitarie" class="form-select" multiple size="6">
              @foreach($aziendeSanitarie as $az)
                <option value="{{ $az->idAziendaSanitaria }}"
                  {{ in_array($az->idAziendaSanitaria, old('aziende_sanitarie', $aziendeSelezionate ?? [])) ? 'selected' : '' }}>
                  {{ $az->Nome }}
                </option>
              @endforeach
            </select>
            <small class="form-text text-muted">
              Puoi selezionare una o più aziende sanitarie (CTRL/CMD per selezione multipla).
            </small>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label d-block">Materiale sanitario fornito da azienda sanitaria</label>
            @php $flag = (int) old('materiale_fornito_asl', $conv->materiale_fornito_asl ?? 0); @endphp
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="materiale_fornito_asl" id="matSi" value="1" {{ $flag === 1 ? 'checked' : '' }}>
              <label class="form-check-label" for="matSi">Sì</label>
            </div>
            <div class="form-check form-check-inline ms-3">
              <input class="form-check-input" type="radio" name="materiale_fornito_asl" id="matNo" value="0" {{ $flag === 0 ? 'checked' : '' }}>
              <label class="form-check-label" for="matNo">No</label>
            </div>
          </div>
        </div>

        {{-- Flag rotazione/sostitutivi + azione contestuale --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label d-block">Calcolo Rotazione / Mezzi sostitutivi necessario?</label>
            @php $abilita = (int) old('abilita_rot_sost', $conv->abilita_rot_sost ?? 0); @endphp
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="abilita_rot_sost" id="rotSostSi" value="1"
                {{ $abilita === 1 ? 'checked' : '' }}
                onclick="toggleRotSost(true)">
              <label class="form-check-label" for="rotSostSi">Sì</label>
            </div>
            <div class="form-check form-check-inline ms-3">
              <input class="form-check-input" type="radio" name="abilita_rot_sost" id="rotSostNo" value="0"
                {{ $abilita === 0 ? 'checked' : '' }}
                onclick="toggleRotSost(false)">
              <label class="form-check-label" for="rotSostNo">No</label>
            </div>

            {{-- Box azioni contestuali --}}
            <div id="rotSostBox" class="mt-3" style="display: {{ $abilita ? 'block' : 'none' }};">
              @php $titolare = \App\Models\Convenzione::getMezzoTitolare($conv->idConvenzione); @endphp

              {{-- CTA principale: aggiorna flag e poi redireziona nel punto giusto --}}
              <button type="button"
                class="btn btn-outline-anpas-green btn-sm mb-2"
                onclick="setRotSostAndGo({{ $conv->idConvenzione }}, {{ $titolare->idAutomezzo ?? 'null' }})">
                <i class="fa fa-car me-1"></i>
                @if($titolare)
                  Modifica KM del TITOLARE
                @else
                  Nomina mezzo TITOLARE / Gestisci KM
                @endif
              </button>

              {{-- Info TITOLARE, se presente --}}
              @if($titolare)
                <div class="alert alert-info p-2 mb-0">
                  <strong>Mezzo titolare:</strong> {{ $titolare->Targa }} – {{ $titolare->CodiceIdentificativo }}<br>
                  <strong>Km titolare:</strong> {{ number_format($titolare->km_titolare, 0, ',', '.') }} km<br>
                  <strong>Totale convenzione:</strong> {{ number_format($titolare->km_totali, 0, ',', '.') }} km<br>
                  <strong>Percentuale impiego:</strong> {{ $titolare->percentuale }}%
                </div>
              @else
                <div class="alert alert-warning p-2 mb-0">
                  Nessun mezzo titolare nominato per questa convenzione.
                </div>
              @endif
            </div>
          </div>
        </div>

        {{-- Note --}}
        <div class="row">
          <div class="col-md-8">
            <label for="note" class="form-label">Note</label>
            <textarea name="note" id="note" class="form-control" rows="3">{{ old('note', $conv->note) }}</textarea>
          </div>
        </div>

        {{-- Pulsanti azione --}}
        <div class="d-flex justify-content-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Aggiorna Convenzione
          </button>
          <a href="{{ route('convenzioni.index', [
            'idAssociazione' => $selectedAssoc,
            'idAnno'         => $selectedAnno
          ]) }}" class="btn btn-secondary">
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
  function toggleRotSost(show) {
    document.getElementById('rotSostBox').style.display = show ? 'block' : 'none';
  }

  /**
   * 1) Abilita il flag abilita_rot_sost lato server
   * 2) Se ho un titolare -> vai a /km-percorsi/{idAutomezzo}/edit
   *    altrimenti        -> vai a /km-percorsi?idConvenzione={idConv} per nomina/gestione
   */
  async function setRotSostAndGo(idConv, idAutomezzoTitolare) {
    const btn = event.currentTarget;
    btn.disabled = true;

    // marca visualmente "Sì"
    const yes = document.getElementById('rotSostSi');
    if (yes) yes.checked = true;

    try {
      const resp = await fetch(`/convenzioni/${idConv}/set-rot-sost`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        }
      });
      const out = await resp.json();

      if (!resp.ok || !out.success) {
        alert(out.message || 'Errore durante l’aggiornamento del flag.');
        btn.disabled = false;
        return;
      }

      // Decidi la rotta in base alla presenza del titolare
      if (idAutomezzoTitolare && Number.isInteger(idAutomezzoTitolare)) {
        window.location.href = `/km-percorsi/${idAutomezzoTitolare}/edit`;
      } else {
        window.location.href = `/km-percorsi?idConvenzione=${idConv}`;
      }
    } catch (err) {
      console.error(err);
      alert('Errore imprevisto durante l’aggiornamento del flag.');
      btn.disabled = false;
    }
  }
</script>
@endpush
