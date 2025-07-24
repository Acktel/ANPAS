@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Modifica Totali a Bilancio – Anno {{ $anno }}</h1>

    <form action="{{ route('ripartizioni.costi_radio.updateTotale') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row mb-3">
            <div class="col-md-3">
                <label for="TotManutenzioneRadio">Tot. Manutenzione Radio</label>
                <input type="number" step="0.01" name="TotManutenzioneRadio" class="form-control" value="{{ $record->ManutenzioneApparatiRadio ?? 0 }}">
            </div>
            <div class="col-md-3">
                <label for="TotMontaggioRadio">Tot. Montaggio/Smontaggio 118</label>
                <input type="number" step="0.01" name="TotMontaggioRadio" class="form-control" value="{{ $record->MontaggioSmontaggioRadio118 ?? 0 }}">
            </div>
            <div class="col-md-3">
                <label for="TotLocazioneRadio">Tot. Locazione Ponte Radio</label>
                <input type="number" step="0.01" name="TotLocazioneRadio" class="form-control" value="{{ $record->LocazionePonteRadio ?? 0 }}">
            </div>
            <div class="col-md-3">
                <label for="TotAmmortamentoRadio">Tot. Ammortamento Impianti Radio</label>
                <input type="number" step="0.01" name="TotAmmortamentoRadio" class="form-control" value="{{ $record->AmmortamentoImpiantiRadio ?? 0 }}">
            </div>
        </div>

        <button type="submit" class="btn btn-success">Salva</button>
        <a href="{{ route('ripartizioni.costi_radio.index') }}" class="btn btn-secondary">Annulla</a>
    </form>
</div>
@endsection
