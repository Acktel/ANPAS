@extends('layouts.app')

@section('title', 'Imputazione Costi Ossigeno')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Imputazione Costi Ossigeno</h1>

    @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card-anpas mb-3">
        <div class="card-body d-flex flex-column align-items-start">
            <h4 class="mb-2">
                NUMERO TOTALE SERVIZI EFFETTUATI NELL'ESERCIZIO<br>
                <span class="text-danger fw-bold">
                    (al netto dei servizi effettuati per convenzioni MSA, MSAB e ASA <u>SE PRESENTI</u>):
                </span>
                <span id="totaleServizi" class="fw-bold text-anpas-green align-items-center">
                    {{ isset($totale_inclusi) ? number_format($totale_inclusi, 0, ',', '.') : 'N/A' }}
                </span>
            </h4>
        </div>
        
    </div>
    {{-- Select associazione --}}
    @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="d-flex mb-3">
        <form id="assocFilterForm" method="POST" class="me-3">
            @csrf
            <select id="assocSelect" name="idAssociazione" class="form-select">
                @foreach($associazioni as $assoc)
                <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $selectedAssoc ? 'selected' : '' }}>
                    {{ $assoc->Associazione }}
                </option>
                @endforeach
            </select>
        </form>
    </div>
    @endif
    <div class="mb-3 d-flex justify-content-end">
        <a href="{{ route('imputazioni.ossigeno.editTotale') }}" class="btn btn-anpas-edit">
            <i class="fas fa-edit me-1"></i> Modifica Totale a Bilancio
        </a>
    </div>

    <div class="card-anpas">
        <div class="card-body">
            <table id="ossigenoTable" class="common-css-dataTable table table-hover table-bordered w-100 table-striped-anpas text-center align-middle">
                <thead class="thead-anpas text-center">
                    <tr>
                        <th>Targa</th>
                        <th>N. SERVIZI SINGOLO AUTOMEZZO</th>
                        <th>PERCENTUALE DI RIPARTO</th>
                        <th>IMPORTO</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
<<<<<<< HEAD
    $(function () {
        const table = $('#ossigenoTable').DataTable({
            ajax: {
                url: '{{ route("imputazioni.ossigeno.getData") }}',
                data: function (d) {
                    d.idAssociazione = $('#assocSelect').val(); // passa idAssociazione selezionata
                }
            },
=======
    $(function() {
            let storedTotaleRow = null;

        $('#ossigenoTable').DataTable({
            // 
            ajax: {
    url: '{{ route("imputazioni.ossigeno.getData") }}',
dataSrc: function(res) {
    let data = res.data || [];

    // Trova e isola la riga 'TOTALE'
    storedTotaleRow = data.find(r => r.is_totale === -1);
    data = data.filter(r => r.is_totale !== -1);

    // Ritorna solo le righe normali
    return data;
}
},
>>>>>>> modifiche_tabelle_anpas_luca
            processing: true,
            serverSide: false,
            paging: true,
            searching: false,
            ordering: true,
<<<<<<< HEAD
            order: [[4, 'asc']], // is_totale
            orderFixed: [[4, 'asc']],
=======
            //modificato da 5 a 4 per mettere "TOTALE" in cima
            order: [], // is_totale
>>>>>>> modifiche_tabelle_anpas_luca
            info: false,
            columns: [
                { data: 'Targa' },
                { data: 'n_servizi', className: 'text-end' },
                { data: 'percentuale', className: 'text-end', render: d => d + '%' },
                { data: 'importo', className: 'text-end', render: d => parseFloat(d).toFixed(2).replace('.', ',') },
                { data: 'is_totale', visible: false, searchable: false }
            ],
<<<<<<< HEAD
            rowCallback: function (row, data, index) {
=======
            
            rowCallback: function(row, data,index) {
>>>>>>> modifiche_tabelle_anpas_luca
                if (data.is_totale === -1) {
                    $(row).addClass('table-warning fw-bold');
                }
                $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
            },


drawCallback: function(settings) {
    const api = this.api();
    const pageRows = api.rows({ page: 'current' }).nodes();

    // Rimuove eventuali duplicati
    $(pageRows).filter('.totale-row').remove();

    // Se esiste la riga totale, la reinseriamo
    if (storedTotaleRow) {
        const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

        api.columns().every(function(index) {
            const col = api.settings()[0].aoColumns[index];

            if (!col.bVisible) return;

            const key = col.data;
            let cellValue = '';

            if (typeof col.render === 'function') {
                cellValue = col.render(storedTotaleRow[key], 'display', storedTotaleRow, { row: -1, col: index, settings });
            } else if (key) {
                cellValue = storedTotaleRow[key] ?? '';
            }

            $lastRow.append(`<td class="${col.className || ''}">${cellValue}</td>`);
        });

        $(api.table().body()).append($lastRow);
    }
},




            language: {
                url: '/js/i18n/Italian.json'
            }
        });

        // Cambio associazione
        $('#assocSelect').on('change', function () {
            const idAssociazione = $(this).val();
            $.post("{{ route('sessione.setAssociazione') }}", {
                _token: '{{ csrf_token() }}',
                idAssociazione: idAssociazione
            }).done(() => {
                table.ajax.reload(); // Ricarica solo la tabella
                // location.reload(); // se vuoi aggiornare anche {{ $totale_inclusi }} da backend
            });
        });
    });
</script>
@endpush
