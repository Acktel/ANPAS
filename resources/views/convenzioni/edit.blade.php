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
              @php
              $titolare = \App\Models\Convenzione::getMezzoTitolare($conv->idConvenzione);
              $isRotazione = \App\Services\RipartizioneCostiService::isRegimeRotazione((int)$conv->idConvenzione);
              $mezziRot = $isRotazione
              ? \App\Models\Convenzione::getMezziRotazione((int)$conv->idConvenzione)
              : [];

              @endphp

              {{-- CTA principale: aggiorna flag e poi redireziona nel punto giusto --}}
              <button type="button"
                class="btn btn-outline-anpas-green btn-sm mb-2"
                onclick="setRotSostAndGo(event, {{ $conv->idConvenzione }}, {{ $titolare->idAutomezzo ?? 'null' }})"
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
                <strong>Km titolare (su questa convenzione):</strong> {{ number_format($titolare->km_titolare, 0, ',', '.') }} km<br>
                <strong>Totale km convenzione:</strong> {{ number_format($titolare->km_totali_conv, 0, ',', '.') }} km<br>

                {{-- Nuove due percentuali affiancate --}}
                <div class="mt-2">
                  <span class="badge me-2">
                    % TRADIZIONALE: {{ number_format($titolare->percent_trad, 2, ',', '.') }}%
                  </span>
                </div>
              </div>
              @else
              <div class="alert alert-warning p-2 mb-0">
                Nessun mezzo titolare nominato per questa convenzione.
              </div>
              @endif
              
              
              @if($abilita === 1 && $isRotazione)

  @php
    $maxPerc = 0.0;
    $maxId  = null;
    foreach ($mezziRot as $mm) {
      if ((float)$mm->percent_rot > $maxPerc) {
        $maxPerc = (float)$mm->percent_rot;
        $maxId   = (int)$mm->idAutomezzo;
      }
    }

    $sumPerc = 0.0;
    foreach ($mezziRot as $mm) {
      $sumPerc += (float)$mm->percent_rot;
    }
  @endphp

  <div class="card mt-3 shadow-sm" style="border-left: 6px solid #f0ad4e;">
    <div class="card-body p-3">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
        <div>
          <div class="d-flex align-items-center gap-2">
            <i class="fa fa-refresh text-warning"></i>
            <h6 class="mb-0">Regime <strong>ROTAZIONE MEZZI</strong></h6>
            <span class="badge bg-warning text-dark">Attivo</span>
          </div>
          <div class="text-muted small mt-1">
            Riparto basato su <strong>km del mezzo sulla convenzione</strong> / <strong>km totali convenzione</strong>.
          </div>
        </div>

        <div class="text-end">
          <div class="small text-muted">Totale km convenzione</div>
          <div class="fw-bold">
            {{ number_format($mezziRot[0]->km_totali_conv ?? 0, 0, ',', '.') }} km
          </div>
        </div>
      </div>

      @if(empty($mezziRot))
        <div class="alert alert-secondary p-2 mt-3 mb-0">
          Nessun km registrato per questa convenzione: impossibile calcolare le %.
        </div>
      @else

        <div class="table-responsive mt-3">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:260px;">Mezzo</th>
                <th class="text-end">Km su conv.</th>
                <th style="min-width:260px;">Quota</th>
                <th class="text-center" style="width:120px;">Azioni</th>
              </tr>
            </thead>
            <tbody>
              @foreach($mezziRot as $m)
                @php
                  $perc = (float)$m->percent_rot;
                  $isTop = ((int)$m->idAutomezzo === (int)$maxId);
                @endphp

                <tr @if($isTop) class="table-warning" @endif>
                  <td>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                      <div>
                        <div class="fw-semibold">
                          {{ $m->Targa }}
                          @if(!empty($m->CodiceIdentificativo))
                            <span class="text-muted fw-normal">— {{ $m->CodiceIdentificativo }}</span>
                          @endif
                        </div>

                        <div class="small text-muted">
                          @if($m->is_titolare)
                            <span class="badge bg-primary text-white">Titolare</span>
                          @else
                            <span class="badge bg-secondary text-white">Non titolare</span>
                          @endif

                          @if($isTop)
                            <span class="badge bg-dark ms-1 text-white">TOP</span>
                          @endif
                        </div>
                      </div>

                      <div class="text-end">
                        <div class="small text-muted">%</div>
                        <div class="fw-bold">{{ number_format($perc, 2, ',', '.') }}%</div>
                      </div>
                    </div>
                  </td>

                  <td class="text-end fw-semibold">
                    {{ number_format($m->km_conv, 0, ',', '.') }}
                  </td>

                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="progress flex-grow-1" style="height: 10px;">
                        <div
                          class="progress-bar"
                          role="progressbar"
                          style="width: {{ min(100, max(0, $perc)) }}%;"
                          aria-valuenow="{{ $perc }}"
                          aria-valuemin="0"
                          aria-valuemax="100">
                        </div>
                      </div>
                      <span class="small text-muted" style="min-width:70px;">
                        {{ number_format($perc, 2, ',', '.') }}%
                      </span>
                    </div>
                    <div class="small text-muted mt-1">
                      {{ number_format($m->km_conv, 0, ',', '.') }} / {{ number_format($m->km_totali_conv, 0, ',', '.') }} km
                    </div>
                  </td>

                  <td class="text-center">
                    <a class="btn btn-outline-secondary btn-sm"
                       href="/km-percorsi/{{ (int)$m->idAutomezzo }}/edit">
                      <i class="fa fa-pencil me-1"></i> KM
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>

            <tfoot class="table-light">
              <tr>
                <th class="text-end" colspan="2">Somma % (arrotondata)</th>
                <th>
                  <span class="fw-bold">{{ number_format($sumPerc, 2, ',', '.') }}%</span>
                  @if(abs($sumPerc - 100.0) > 0.2)
                    <span class="badge bg-danger ms-2">Attenzione</span>
                    <div class="small text-muted mt-1">
                      Se non torna ~100% è normale se mancano km o per arrotondamenti; qui però verifica.
                    </div>
                  @else
                    <span class="badge bg-success ms-2">OK</span>
                  @endif
                </th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <span class="badge bg-light text-dark border">
            Suggerimento: il mezzo <strong>TOP</strong> è quello che “pesa” di più sulla convenzione.
          </span>
        </div>

      @endif
    </div>
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
        <div class="d-flex justify-content-center mt-4 myborder-button">
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
  async function setRotSostAndGo(ev, idConv, idAutomezzoTitolare) {
    const btn = ev.currentTarget;
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