@extends('layouts.app')

@section('content')
<div class="container-fluid">
@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
  <div class="d-flex mb-3">
    <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="me-3">
      @csrf
      <select id="assocSelect" name="idAssociazione" class="form-select" onchange="this.form.submit()">
        @foreach($associazioni as $assoc)
          <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $selectedAssoc ? 'selected' : '' }}>
            {{ $assoc->Associazione }}
          </option>
        @endforeach
      </select>
    </form>
  </div>
@endif
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
        <div id="tabella-autisti" class="table-responsive">
            @include('partials.table_autisti') {{-- <table class="table table-bordered table-striped-anpas w-100 text-center align-middle"> --}}
        </div>

        <div id="tabella-generica" class="table-responsive d-none">
            @include('partials.table_generica') {{-- <table class="table table-bordered table-striped-anpas w-100 text-center align-middle"> --}}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('ripartizioni.costi_personale.script')
@endpush
