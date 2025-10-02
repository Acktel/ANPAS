@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Modifica Costi Automezzo</h1>
    <form method="POST" action="{{ route('ripartizioni.costi_automezzi.update', $record->idAutomezzo) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="idAutomezzo" value="{{ $record->idAutomezzo }}">

        @foreach ([
            'LeasingNoleggio', 'Assicurazione', 'ManutenzioneOrdinaria', 'ManutenzioneStraordinaria',
            'RimborsiAssicurazione', 'PuliziaDisinfezione', 'Carburanti', 'Additivi',
            'RimborsiUTF', 'InteressiPassivi', 'AltriCostiMezzi', 'ManutenzioneSanitaria',
            'LeasingSanitaria', 'AmmortamentoMezzi', 'AmmortamentoSanitaria'
        ] as $campo)
            <div class="mb-3">
                <label class="form-label">{{ $campo }}</label>
                <input type="number" step="0.01" name="{{ $campo }}" value="{{ $record->$campo ?? 0 }}" class="form-control">
            </div>
        @endforeach

        <button type="submit" class="btn btn-success">Salva</button>
        <a href="{{ route('ripartizioni.costi_automezzi.index') }}" class="btn btn-secondary">Annulla</a>
    </form>
</div>
@endsection
