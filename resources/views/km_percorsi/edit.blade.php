@extends('layouts.app')
@php
use App\Models\Associazione;

$user = Auth::user();
$isImpersonating = session()->has('impersonate');

$selectedAssociazione = session('selectedAssociazione') ?? $automezzo->idAssociazione;
$assocCorr = Associazione::getById($selectedAssociazione);
$annoCorr = session('annoCorrente') ?? ($automezzo->idAnno ?? now()->year);

@endphp

@section('content')
<div class="container">    
    <h1 class="container-title mb-4">
        Modifica KM percorsi – ({{ $automezzo->Targa }} - {{ $automezzo->CodiceIdentificativo }})
    </h1>
    <p class="text-muted mb-4">
        Associazione #{{ $assocCorr->Associazione }} — Anno {{ $annoCorr}}
    </p>

<form method="POST" action="{{ route('km-percorsi.update', $automezzo->idAutomezzo) }}">
    @csrf
    @method('PUT')

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th>Convenzione</th>
                    <th>KM Percorsi</th>
                    <th style="width:160px;">Titolare</th>
                </tr>
            </thead>
            <tbody>
                @foreach($convenzioni as $conv)
                @php
                $rec = $kmEsistenti->get($conv->idConvenzione); // contiene KMPercorsi + is_titolare
                $km = $rec->KMPercorsi ?? 0;
                $isTitolare = (int)($rec->is_titolare ?? 0) === 1;
                $abilitato = (int)($conv->abilita_rot_sost ?? 0) === 1; // mostra la checkbox solo se abilitato
                @endphp
                <tr>
                    <td class="text-start">{{ $conv->Convenzione }}</td>
                    <td style="max-width:180px;">
                        <input
                            type="number"
                            min="0"
                            step="1"
                            name="km[{{ $conv->idConvenzione }}]"
                            class="form-control text-end"
                            value="{{ $km }}">
                    </td>
                    <td>
                        @if($abilitato)
                        <div class="form-check d-flex align-items-center justify-content-center gap-2">
                            <input class="form-check-input" type="checkbox"
                                id="tit_{{ $conv->idConvenzione }}"
                                name="titolare[]"
                                value="{{ $conv->idConvenzione }}"
                                {{ $isTitolare ? 'checked' : '' }}>
                            <label for="tit_{{ $conv->idConvenzione }}" class="form-check-label">
                                Mezzo titolare
                            </label>
                        </div>
                        <small class="text-muted d-block">Un solo titolare per convenzione.</small>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-center myborder-button">
        <button type="submit" class="btn btn-anpas-green">
            <i class="fas fa-check me-1"></i> Salva
        </button>
        <a href="{{ route('km-percorsi.index') }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Annulla
        </a>
    </div>
</form>
</div>
@endsection