@extends('layouts.app')

@section('content')
<div class="container-fluid">

    {{-- 🔘 Menu qualifiche --}}
    <div class="mb-4">
        <div class="btn-group" role="group" aria-label="Qualifiche">
            @foreach ($qualifiche as $qualifica)
                <button type="button"
                        class="btn btn-outline-primary btn-qualifica {{ $qualifica === 'Autisti e Barellieri' ? 'active btn-anpas-green-active' : '' }}"
                        data-qualifica="{{ $qualifica }}">
                    {{ $qualifica }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- 🧾 Titolo + bottone --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 id="titolo-pagina" class="container-title">
            Distinta Rilevazione Analitica Costi Personale (Autisti e Barellieri) − Anno {{ $anno }}
        </h1>
        <a href="{{ route('ripartizioni.personale.costi.create') }}" class="btn btn-anpas-green">
            <i class="fas fa-plus me-1"></i> Nuovo inserimento
        </a>
    </div>

    {{-- 📊 Contenitore tabelle --}}
    <div id="contenitore-tabella">
        <div id="tabella-autisti">
            @include('partials.table_autisti')
        </div>

        <div id="tabella-generica" class="d-none">
            @include('partials.table_generica')
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('ripartizioni.costi_personale.script')
@endpush
