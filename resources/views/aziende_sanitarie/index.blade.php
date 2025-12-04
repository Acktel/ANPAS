@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <h1 class="container-title mb-4">Aziende Sanitarie</h1>

    {{-- Success message --}}
    @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Messaggio duplicazione --}}
    <div id="noDataMessage" class="alert alert-info {{ $showDuplica ? '' : 'd-none' }}">
        Nessuna azienda sanitaria presente per l’anno corrente.<br>
        Vuoi importare le anagrafiche e i collegamenti dall’anno precedente?
        <div class="mt-2">
            <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
            <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
        </div>
    </div>


    {{-- Pulsante nuova azienda --}}
    <div class="d-flex mb-3">
        <div class="ms-auto">
            @can('manage-all-associations')
            @if (!session()->has('impersonate'))
            <a href="{{ route('aziende-sanitarie.create') }}" class="btn btn-anpas-green">
                <i class="fas fa-plus me-1"></i> Nuova Azienda Sanitaria
            </a>
            @endif
            @endcan
        </div>
    </div>
    
    {{-- SELECT CONVENZIONE (solo utenti non elevati + AJAX attivo) --}}
    @if(!$isElevato && $useAjax && $convenzioni->isNotEmpty())
    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label fw-bold">Convenzione</label>
            <select id="convSelect" class="form-select form-select-anpas">
                @foreach($convenzioni as $c)
                <option value="{{ $c->idConvenzione }}"
                    {{ (int)$selectedConv === (int)$c->idConvenzione ? 'selected' : '' }}>
                    {{ $c->Convenzione }}
                </option>
                @endforeach
            </select>
        </div>
    </div>
    @else
    <input type="hidden" id="convSelect" value="{{ $selectedConv }}">
    @endif


    {{-- TABELLA --}}
    <div class="card-anpas">
        <div class="card-body bg-anpas-white p-0">

            <table id="aziendeSanitarieTable"
                class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">

                <thead class="thead-anpas">
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Indirizzo</th>
                        <th>Provincia</th>
                        <th>Città</th>
                        <th>CAP</th>
                        <th>Email</th>
                        <th>Lotti</th>
                        <th class="col-actions text-center">Azioni</th>
                    </tr>
                </thead>

                {{-- NO-AJAX: render server side --}}
                @if(!$useAjax)
                <tbody>
                    @forelse($aziende as $a)
                    <tr>
                        <td>{{ $a->idAziendaSanitaria }}</td>
                        <td>{{ $a->Nome }}</td>
                        <td>
                        {{ trim(($a->indirizzo_via ?? '') . ' ' . ($a->indirizzo_civico ?? '')) ?: ($a->Indirizzo ?? '—') }}
                        </td>
                        <td>{{ $a->provincia }}</td>
                        <td>{{ $a->citta }}</td>
                        <td>{{ $a->cap ?? '' }}</td>
                        <td>{{ $a->mail }}</td>
                        <td>
                            @php
                            $full = implode(', ', $a->Lotti ?? []);
                            $short = strlen($full) > 100 ? substr($full, 0, 100).'…' : $full;
                            @endphp
                            @if (!empty($a->Lotti))
                            <span class="ellipsis-cell" title="{{ $full }}">{{ $short }}</span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('aziende-sanitarie.edit', $a->idAziendaSanitaria) }}"
                                class="btn btn-sm btn-anpas-edit me-1 btn-icon">
                                <i class="fas fa-edit"></i>
                            </a>

                        {{-- Pulsante DELETE solo per chi può gestire tutte le associazioni --}}
                            @can('manage-all-associations')
                            <form action="{{ route('aziende-sanitarie.destroy', $a->idAziendaSanitaria) }}"
                                method="POST" class="d-inline"
                                onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-anpas-delete btn-icon">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    {{-- SAFE: 9 celle, una per colonna → DataTables non esplode --}}
                    <tr>
                        <td colspan="9" class="text-center py-3 text-muted">
                            Nessuna azienda sanitaria trovata.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @endif

            </table>

        </div>
    </div>

</div>
@endsection
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrf            = document.querySelector('meta[name="csrf-token"]').content;
        const useAjax         = @json($useAjax);
        const isElevato       = @json($isElevato);
        const hasConvenzioni  = @json($convenzioni->isNotEmpty());
        const convSelect      = document.getElementById('convSelect');

        // DEBUG
        console.log("DEBUG:", { useAjax, isElevato, hasConvenzioni });

        if (!useAjax) {
            console.log("NO AJAX → lascio la tabella com'è");
            return;
        }

        if (useAjax && !hasConvenzioni) {
            console.warn("AJAX ma niente convenzioni → tabella statica");
            return;
        }

        if ($.fn.DataTable.isDataTable('#aziendeSanitarieTable')) {
            $('#aziendeSanitarieTable').DataTable().clear().destroy();
        }

        const config = {
            stateSave: false,
            order: [[0, 'asc']],
               language: {
                url: '/js/i18n/Italian.json',
                emptyTable: 'Nessuna convenzione.', // messaggio quando non ci sono righe
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>',
                    next: '<i class="fas fa-angle-right"></i>',
                    previous: '<i class="fas fa-angle-left"></i>'
                },
            },
            stripeClasses: ['table-white', 'table-striped-anpas'],
             searching:true,
            ordering: true,

            // 🔍 Mostra barra di ricerca in alto, paginatore in basso
            dom: '<"top"f>rt<"bottom"lip><"clear">',

            ajax: {
                url: '{{ route("aziende-sanitarie.data") }}',
                data: d => { d.idConvenzione = convSelect.value; }
            },

            columns: [
                { data: 'idAziendaSanitaria' },
                { data: 'Nome' },
                {
                    data: null,
                    render: row => {
                        const indirizzo = `${row.indirizzo_via ?? ''} ${row.indirizzo_civico ?? ''}`.trim();
                        return indirizzo || (row.Indirizzo ?? '—');
                    }
                },
                { data: 'provincia' },
                { data: 'citta' },
                { data: 'cap' },
                { data: 'mail' },
                {
                    data: 'Lotti',
                    render: d => {
                        if (!Array.isArray(d) || d.length === 0) {
                            return '<span class="text-muted">—</span>';
                        }

                        const full = d.join(', ');
                        const short = full.length > 100 ? full.substring(0, 100) + '…' : full;

                        return `<span class="ellipsis-cell" title="${full}">${short}</span>`;
                    }
                },
                {
                    data: 'idAziendaSanitaria',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: id => `
                        <a href="/aziende-sanitarie/${id}/edit"
                            class="btn btn-sm btn-anpas-edit me-1 btn-icon">
                            <i class="fas fa-edit"></i>
                        </a>

                        <form action="/aziende-sanitarie/${id}" method="POST"
                              class="d-inline"
                              onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-anpas-delete btn-icon">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    `
                }
            ]
        };

        const table = $('#aziendeSanitarieTable').DataTable(config);

        if (!isElevato) {
            convSelect.addEventListener('change', function () {
                fetch('{{ route("aziende-sanitarie.sessione.setConvenzione") }}', {
                    method: 'POST',
                    headers: {
                        "X-CSRF-TOKEN": csrf,
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ idConvenzione: this.value })
                }).finally(() => table.ajax.reload());
            });
        }
    });
</script>
@endpush
