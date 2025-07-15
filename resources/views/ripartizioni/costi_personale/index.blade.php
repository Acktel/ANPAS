@extends('layouts.app')

@section('content')
<div class="container-fluid">
    {{-- Titolo e bottone --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title">Distinta Rilevazione Analitica Costi Personale − Anno {{ $anno }}</h1>
        <a href="{{ route('ripartizioni.personale.costi.create') }}" class="btn btn-anpas-green">
            <i class="fas fa-plus me-1"></i> Nuovo inserimento
        </a>
    </div>

    {{-- Tabella --}}
    <div class="table-responsive">
        <table id="table-costi" class="table table-bordered w-100 text-center align-middle">
            <thead class="table-light">
                <tr id="header-main"></tr>
                <tr id="header-sub"></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(async function () {
    const res = await fetch("{{ route('ripartizioni.personale.costi.data') }}");
    const { data, labels } = await res.json();
    if (!data.length) return;

    const table = $('#table-costi');

    const staticColumns = [
        { key: 'is_totale', label: '', hidden: true },
        { key: 'idDipendente', label: 'ID', hidden: true },
        { key: 'Dipendente', label: 'COGNOME' },
        { key: 'Retribuzioni', label: 'RETRIBUZIONI' },
        { key: 'OneriSociali', label: 'ONERI<br>SOCIALI' },
        { key: 'TFR', label: 'TFR' },
        {
            key: 'Consulenze',
            label: 'CONSULENZE PER PERSONALE DIPENDENTE<br>E SORVEGLIANZA SANITARIA D.Lgs 626/94'
        },
        { key: 'Totale', label: 'TOTALE' }
    ];

    const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

    let headerMain = '';
    let headerSub = '';
    const columns = [];
    let visibleIndex = 0;
    const costColumnIndexes = [];

    // Colonne fisse
    staticColumns.forEach(col => {
        headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
        columns.push({ data: col.key, visible: !col.hidden });
        if (!col.hidden) visibleIndex++;
    });

    // Colonne dinamiche per convenzioni
    convenzioni.forEach(conv => {
        const nomeConvenzione = labels[conv];
        headerMain += `<th colspan="2">${nomeConvenzione}</th>`;
        headerSub += `<th class="text-end">Importo €</th><th class="text-end">%</th>`;

        columns.push({ data: `${conv}_importo`, className: 'text-end', defaultContent: "0.00" });
        costColumnIndexes.push(visibleIndex++);
        columns.push({ data: `${conv}_percent`, className: 'text-end', visible: false, defaultContent: "0.00" });
        visibleIndex++;
    });

    // Azioni
    headerMain += `<th rowspan="2">Azioni</th>`;
    columns.push({
        data: null,
        orderable: false,
        searchable: false,
        render: function(row) {
            if (row.is_totale || !row.idDipendente) return '-';
            return `
                <a href="/ripartizioni/personale/costi/${row.idDipendente}" class="btn btn-sm btn-info me-1">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="/ripartizioni/personale/costi/${row.idDipendente}/edit" class="btn btn-sm btn-anpas-edit me-1">
                    <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="/ripartizioni/personale/costi/${row.idDipendente}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-anpas-delete">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            `;
        }
    });

    $('#header-main').html(headerMain);
    $('#header-sub').html(headerSub);

    table.DataTable({
        data,
        columns,
        columnDefs: [
            { targets: 0, visible: false, searchable: false }, // is_totale
            { targets: costColumnIndexes, className: 'text-end' },
        ],
        order: [[0, 'asc']],
        orderFixed: [[0, 'asc']],
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
        },
        rowCallback: function(row, data) {
            if (data.is_totale) {
                $(row).addClass('fw-bold table-totalRow');
            }
        }
    });
});
</script>
@endpush
