@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="container-title mb-4">
        Modifica KM percorsi – {{ $automezzo->Automezzo }} ({{ $automezzo->Targa }}) – Anno {{ session('anno_riferimento') }}
    </h1>

    <form method="POST" action="{{ route('km-percorsi.update', $automezzo->idAutomezzo) }}">
        @csrf
        @method('PUT')

        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>Convenzione</th>
                        <th>KM Percorsi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($convenzioni as $conv)
                    @php
                        $km = $kmEsistenti->get($conv->idConvenzione)?->KMPercorsi ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $conv->Convenzione }}</td>
                        <td>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="km[{{ $conv->idConvenzione }}]"
                                class="form-control text-end"
                                value="{{ $km }}">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 d-flex justify-content-between">
            <a href="{{ route('km-percorsi.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Indietro
            </a>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-1"></i> Salva
            </button>
        </div>
    </form>
</div>
@endsection