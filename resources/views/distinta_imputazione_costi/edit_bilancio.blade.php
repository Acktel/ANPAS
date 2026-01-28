@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Modifica “Importo Totale da Bilancio Consuntivo” — {{ $sezioneLabel }} ({{ $anno }})
  </h1>

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
    </ul>
  </div>
  @endif

  <form method="POST" action="{{ route('distinta.imputazione.updateBilancio', ['sezione' => $sezione]) }}" novalidate>
    @csrf
    @method('PUT')

    <input type="hidden" name="idAssociazione" value="{{ $idAssociazione }}">
    <input type="hidden" name="idAnno" value="{{ $anno }}">

    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="thead-anpas">
              <tr>
                <th style="width:40%">Voce</th>
                <th class="text-end" style="width:30%">Importo Totale da Bilancio Consuntivo</th>
                <th style="width:30%">Note</th>
              </tr>
            </thead>
            <tbody>
              @foreach($righe as $r)
                @php
                  $idVoce = (int) $r['idVoceConfig'];

                  $valDefault = is_numeric($r['bilancio'])
                    ? number_format((float)$r['bilancio'], 2, '.', '')
                    : '';

                  $valInput = old('bilancio.'.$idVoce, $valDefault);

                  // NOTE: qui usiamo note_bilancio
                  $noteInput = old('note_bilancio.'.$idVoce, $r['note'] ?? '');
                @endphp

                <tr>
                  <td>{{ $r['descrizione'] }}</td>

                  <td class="text-end">
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      name="bilancio[{{ $idVoce }}]"
                      value="{{ $valInput }}"
                      class="form-control text-end"
                      autocomplete="off"
                      aria-label="Importo da bilancio per {{ $r['descrizione'] }}">
                  </td>

                  <td>
                    <textarea
                      name="note_bilancio[{{ $idVoce }}]"
                      class="form-control"
                      rows="2"
                      maxlength="2000"
                      placeholder="Note di bilancio...">{{ $noteInput }}</textarea>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="small text-muted mb-3">
          Qui modifichi solo gli importi da bilancio; i costi diretti/ammortamenti non vengono toccati.
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i> Salva
          </button>
          <a
            href="{{ route('distinta.imputazione.index', ['idAssociazione' => $idAssociazione, 'anno' => $anno]) }}"
            class="btn btn-secondary">
            Annulla
          </a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
