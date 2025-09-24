{{-- resources/views/ripartizioni/costi_personale/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    {{-- 🧾 Titolo --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 id="titolo-pagina" class="container-title">
            Distinta Rilevazione Analitica Costi Personale − Anno {{ $anno }}
        </h1>
    </div>

    {{-- 🔘 Menu qualifiche (arrivano come oggetti: id + nome) --}}
    <div class="mb-4">
        <div class="btn-group" role="group" aria-label="Qualifiche">
            @foreach ($qualifiche as $q)
                @php
                    $isDefault = (int)$q->id === 1; // AUTISTA SOCCORRITORE
                @endphp
                <button type="button"
                    class="btn btn-outline-primary btn-qualifica {{ $isDefault ? 'active btn-anpas-green-active' : '' }}"
                    data-id="{{ (int)$q->id }}"
                    data-label="{{ e($q->nome) }}">
                    {{ strtoupper($q->nome) }}
                </button>
            @endforeach
        </div>
    </div>

    @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="d-flex mb-3 position-relative" style="max-width:400px">
        <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="w-100">
            @csrf
            <div class="input-group">
                <input
                    type="text"
                    id="assocInput"
                    class="form-control text-start"
                    placeholder="Seleziona associazione"
                    value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
                    readonly>
                <button type="button" id="assocDropdownToggle" class="btn btn-outline-secondary" aria-expanded="false" title="Mostra elenco">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <input type="hidden" name="idAssociazione" id="assocHidden" value="{{ $selectedAssoc ?? '' }}">
            </div>

            <ul id="assocDropdown" class="list-group position-absolute w-100"
                style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0; background-color:#fff;">
                @foreach($associazioni as $assoc)
                <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
                    {{ $assoc->Associazione }}
                </li>
                @endforeach
            </ul>
        </form>
    </div>
    @endif

    {{-- 📊 Contenitore tabelle --}}
    <div id="contenitore-tabella">
        <div id="tabella-autisti" class="table-responsive">
            @include('partials.table_autisti')
        </div>

        <div id="tabella-generica" class="table-responsive d-none">
            @include('partials.table_generica')
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('ripartizioni.costi_personale.script')

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ===== Dropdown associazione =====
    const toggleBtn   = document.getElementById('assocDropdownToggle');
    const dropdown    = document.getElementById('assocDropdown');
    const assocInput  = document.getElementById('assocInput');
    const assocHidden = document.getElementById('assocHidden');
    const form        = document.getElementById('assocFilterForm');

    if (toggleBtn && dropdown) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
        });

        document.querySelectorAll('.assoc-item').forEach(item => {
            item.addEventListener('click', function() {
                assocInput.value  = (this.textContent || '').trim();
                assocHidden.value = this.dataset.id || '';
                dropdown.style.display = 'none';
                assocInput.style.textAlign = 'left';
                form.submit();
            });
        });

        document.addEventListener('click', function(e) {
            if (!form.contains(e.target)) dropdown.style.display = 'none';
        });
    }

    // ===== Bottoni qualifiche → carica dati con idQualifica =====
    const titleEl = document.getElementById('titolo-pagina');

    async function fetchAndRender(idQualifica, label) {
        const url = idQualifica
            ? `{{ route('ripartizioni.personale.costi.data') }}?idQualifica=${encodeURIComponent(idQualifica)}`
            : `{{ route('ripartizioni.personale.costi.data') }}`;

        const res = await fetch(url);
        const json = await res.json();
        const data = json.data || [];
        const labels = json.labels || {};

        // Aggiorna titolo
        titleEl.textContent = `Distinta Rilevazione Analitica Costi Personale (${label}) − Anno {{ $anno }}`;

        // id=1 -> tab autisti, altrimenti tab generica
        const isAutisti = String(idQualifica) === '1';
        document.getElementById('tabella-autisti').classList.toggle('d-none', !isAutisti);
        document.getElementById('tabella-generica').classList.toggle('d-none', isAutisti);

        if (isAutisti) {
            // funzione definita in ripartizioni/costi_personale/script.blade.php
            if (typeof buildAutistiTable === 'function') {
                buildAutistiTable(data, labels);
            }
        } else {
            if (typeof buildGenericaTable === 'function') {
                buildGenericaTable(data);
            }
        }
    }

    // click handler sui bottoni
    document.querySelectorAll('.btn-qualifica').forEach(btn => {
        btn.addEventListener('click', async function() {
            document.querySelectorAll('.btn-qualifica').forEach(b => b.classList.remove('active','btn-anpas-green-active'));
            this.classList.add('active','btn-anpas-green-active');

            const idQualifica = this.getAttribute('data-id');
            const label       = this.getAttribute('data-label') || '';
            await fetchAndRender(idQualifica, label);
        });
    });

    // ===== load iniziale: id=1 (AUTISTA SOCCORRITORE) =====
    const defaultBtn = document.querySelector('.btn-qualifica[data-id="1"]')
                      || document.querySelector('.btn-qualifica');
    if (defaultBtn) {
        const idQualifica = defaultBtn.getAttribute('data-id');
        const label       = defaultBtn.getAttribute('data-label') || '';
        fetchAndRender(idQualifica, label);
    }
});
</script>
@endpush
