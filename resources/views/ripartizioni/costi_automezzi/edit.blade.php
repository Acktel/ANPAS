@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Costi Automezzo</h1>
  <p class="text-muted mb-4">
    <b>Automezzo: {{ $automezzo->Targa }} — {{ $automezzo->CodiceIdentificativo }}</b>
  </p>

  <form method="POST" action="{{ route('ripartizioni.costi_automezzi.update', $record->idAutomezzo) }}">
    @csrf
    @method('PUT')

    {{-- chiavi --}}
    <input type="hidden" name="idAutomezzo" value="{{ $record->idAutomezzo }}">
    <input type="hidden" name="idAssociazione" value="{{ request('idAssociazione') }}">

    <div class="card-anpas mb-3">
      <div class="card-body bg-anpas-white">
        <div class="row g-3">
          @foreach ([
            'LeasingNoleggio'           => 'Leasing/Noleggio',
            'Assicurazione'             => 'Assicurazione',
            'ManutenzioneOrdinaria'     => 'Manutenzione ordinaria',
            'ManutenzioneStraordinaria' => 'Manutenzione straordinaria',
            'RimborsiAssicurazione'     => 'Rimborsi assicurazione',
            'PuliziaDisinfezione'       => 'Pulizia e disinfezione',
            'Carburanti'                => 'Carburanti',
            'Additivi'                  => 'Additivi',
            'RimborsiUTF'               => 'Rimborsi UTF',
            'InteressiPassivi'          => 'Interessi passivi',
            'AltriCostiMezzi'           => 'Altri costi mezzi',
            'ManutenzioneSanitaria'     => 'Manutenzione sanitaria',
            'LeasingSanitaria'          => 'Leasing sanitaria',
            'AmmortamentoMezzi'         => 'Ammortamento mezzi',
            'AmmortamentoSanitaria'     => 'Ammortamento sanitaria',
          ] as $campo => $label)
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label">{{ $label }}</label>
              <input
                type="text" inputmode="decimal" lang="it"
                name="{{ $campo }}"
                value="{{ old($campo, number_format((float)($record->$campo ?? 0), 2, ',', '.')) }}"
                class="form-control text-end"
                placeholder="0,00">
            </div>
          @endforeach

          {{-- NOTE in colonna singola --}}
          <div class="col-12">
            <label for="note" class="form-label">Note</label>
            <textarea name="Note" id="note" class="form-control" rows="3"
              placeholder="Annotazioni libere...">{{ old('Note', $record->Note) }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('ripartizioni.costi_automezzi.index') }}" class="btn btn-secondary">Annulla</a>
      <button type="submit" class="btn btn-success">Salva</button>
    </div>
  </form>
</div>
@endsection
