@extends('layouts.app')
@php
  $assocCorr = \App\Models\Associazione::getById(session('associazione_selezionata', Auth::user()->IdAssociazione));
@endphp
@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title">
            Modifica Servizi Svolti – {{ $automezzo->Targa }} – Anno {{ session('anno_riferimento') }}
        </h1>
    </div>


    <form method="POST" action="{{ route('servizi-svolti.update', $automezzo->idAutomezzo) }}">
        @csrf
        @method('PUT')

        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>Convenzione</th>
                        <th>Numero Servizi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($convenzioni as $conv)
                    @php
                        $nServizi = $serviziEsistenti->get($conv->idConvenzione)?->NumeroServizi ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $conv->Convenzione }}</td>
                        <td>
                            <input
                                type="number"
                                step="1"
                                min="0"
                                name="servizi[{{ $conv->idConvenzione }}]"
                                class="form-control text-end"
                                value="{{ $nServizi }}">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-anpas-green">
                <i class="fas fa-check me-1"></i> Salva
            </button>
            <a href="{{ route('servizi-svolti.index') }}" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i> Indietro
            </a>

        </div>
    </form>
</div>
@endsection
