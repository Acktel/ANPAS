@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="container-title mb-4">
        Dettaglio Servizi Svolti – {{ $automezzo->Automezzo }} ({{ $automezzo->Targa }}) – Anno {{ session('anno_riferimento') }}
    </h1>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th>Convenzione</th>
                    <th>N. Servizi Svolti</th>
                </tr>
            </thead>
            <tbody>
                @foreach($convenzioni as $conv)
                @php
                $nServizi = $valori[$conv->idConvenzione] ?? 0;
                @endphp
                <tr>
                    <td>{{ $conv->Convenzione }}</td>
                    <td>{{ number_format($nServizi, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>


    <div class="mt-4">
        <a href="{{ route('servizi-svolti.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Indietro
        </a>
    </div>
</div>
@endsection