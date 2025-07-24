@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Visualizza Costi Automezzo</h1>

    <ul class="list-group">
        @foreach (get_object_vars($record) as $key => $value)
            <li class="list-group-item d-flex justify-content-between">
                <strong>{{ $key }}</strong>
                <span>{{ number_format($value, 2, ',', '.') }}</span>
            </li>
        @endforeach
    </ul>

    <a href="{{ route('ripartizioni.costi_automezzi.index') }}" class="btn btn-secondary mt-4">Torna indietro</a>
@endsection
