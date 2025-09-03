@extends('layouts.app')

@section('content')
<div class="container-fluid">
@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
<div class="d-flex mb-3 position-relative" style="max-width:400px">
    <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="w-100">
        @csrf
        <div class="input-group">
            <!-- Campo visibile -->
            <input type="text" id="assocInput" class="form-control text-start" placeholder="Seleziona associazione"
                   value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}" readonly>

            <!-- Bottone -->
            <button type="button" id="assocDropdownToggle" class="btn btn-outline-secondary" aria-expanded="false" title="Mostra elenco">
                <i class="fas fa-chevron-down"></i>
            </button>

            <!-- Hidden input -->
            <input type="hidden" name="idAssociazione" id="assocHidden" value="{{ $selectedAssoc ?? '' }}">
        </div>

        <!-- Dropdown -->
        <ul id="assocDropdown" class="list-group position-absolute w-100" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0; background-color:#fff;">
            @foreach($associazioni as $assoc)
                <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
                    {{ $assoc->Associazione }}
                </li>
            @endforeach
        </ul>
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




    <script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('assocDropdownToggle');
    const dropdown = document.getElementById('assocDropdown');
    const assocInput = document.getElementById('assocInput');
    const assocHidden = document.getElementById('assocHidden');
    const form = document.getElementById('assocFilterForm');

    if (!toggleBtn || !dropdown) return;

    // Mostra/nasconde dropdown
    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
    });

    // Click su un elemento
    document.querySelectorAll('.assoc-item').forEach(item => {
        item.addEventListener('click', function () {
            const text = this.textContent.trim();
            const id = this.dataset.id;

            assocInput.value = text;
            assocHidden.value = id;

            dropdown.style.display = 'none';
            assocInput.style.textAlign = 'left';

            form.submit();
        });
    });

    // Chiude dropdown se clicchi fuori
    document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});
</script>
@endpush
