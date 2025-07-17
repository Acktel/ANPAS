@extends('layouts.app')

@section('content')
<div class="container-fluid">
    {{-- Titolo --}}
    @php
   
    $dipendente = App\Models\Dipendente::getOne($record->idDipendente);
    $nome = $dipendente->DipendenteCognome . ' ' . $dipendente->DipendenteNome; 
    @endphp
    <h1 class="container-title mb-4">
        Dettaglio Costi Dipendente: {{ $nome }} 
    </h1>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-striped mb-0">
                <tbody>
                    <tr>
                        <th>Anno</th>
                        <td>{{ $record->idAnno }}</td>
                    </tr>
                    <tr>
                        <th>Retribuzioni</th>
                        <td>{{ number_format($record->Retribuzioni, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <th>Oneri Sociali</th>
                        <td>{{ number_format($record->OneriSociali, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <th>TFR</th>
                        <td>{{ number_format($record->TFR, 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <th>Consulenze</th>
                        <td>{{ number_format($record->Consulenze, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="table-success">
                        <th>Totale</th>
                        @php
                        $record->Totale = $record->Retribuzioni + $record->OneriSociali + $record->TFR + $record->Consulenze;
                        @endphp
                        <td><strong>{{ number_format($record->Totale, 2, ',', '.') }} €</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-end">
            <a href="{{ route('ripartizioni.personale.costi.index') }}" class="btn btn-secondary">Torna all'elenco</a>
            <a href="{{ route('ripartizioni.personale.costi.edit', $record->idDipendente) }}" class="btn btn-primary">Modifica</a>
        </div>
    </div>
</div>
@endsection
