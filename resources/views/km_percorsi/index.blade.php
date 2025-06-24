@extends('layouts.app')

@section('content')
<div class="container-fluid">
    {{-- Titolo e bottone --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title">Distinta Km percorsi per convenzione − Anno {{ $anno }}</h1>
        <a href="{{ route('km-percorsi.create') }}" class="btn btn-anpas-green">
            <i class="fas fa-plus me-1"></i> Nuovo inserimento
        </a>
    </div>

    {{-- Tabella --}}
    <div class="table-responsive">
        <table id="table-km" class="table table-bordered w-100 text-center align-middle">
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
    const res = await fetch("{{ route('km-percorsi.datatable') }}");
    const { data, labels } = await res.json();
    if (!data.length) return;

    const table = $('#table-km');

    // Colonne statiche iniziali
    const staticColumns = [
        { key: 'idAutomezzo', label: 'ID', hidden: true },
        { key: 'Automezzo', label: 'Automezzo' },
        { key: 'Targa', label: 'Targa' },
        { key: 'CodiceIdentificativo', label: 'Codice ID' },
        { key: 'Totale', label: 'KM Totali' },
    ];

    // Convenzioni ordinate (es. c1, c2, ..., c10)
    const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

    // Header e colonne
    let headerMain = '';
    let headerSub = '';
    const columns = [];
    let visibleIndex = 0;
    const kmColumnIndexes = [];

    // Static headers
staticColumns.forEach(col => {
    headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
    columns.push({ data: col.key, visible: !col.hidden });
    if (!col.hidden) visibleIndex++;
});

// Convenzioni dinamiche (hanno 2 colonne: km e %)
convenzioni.forEach(conv => {
    headerMain += `<th colspan="2">${labels[conv]}</th>`;
    headerSub += `<th class="kmTh">Km</th><th>%</th>`;
    columns.push({ data: `${conv}_km` });
    kmColumnIndexes.push(visibleIndex);
    visibleIndex++;
    columns.push({ data: `${conv}_percent` });
    visibleIndex++;
});

// Colonna Azioni (aggiunta a main, NON a sub)
headerMain += `<th rowspan="2">Azioni</th>`;
columns.push({
    data: null,
    orderable: false,
    searchable: false,
    render: function(row) {
        if (!row.idAutomezzo) return '-';
        return `
            <a href="/km-percorsi/${row.idAutomezzo}" class="btn btn-sm btn-info me-1">
                <i class="fas fa-eye"></i>
            </a>
            <a href="/km-percorsi/${row.idAutomezzo}/edit" class="btn btn-sm btn-warning me-1">
                <i class="fas fa-edit"></i>
            </a>
            <form method="POST" action="/km-percorsi/${row.idAutomezzo}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        `;
    }
});


    $('#header-main').html(headerMain);
    $('#header-sub').html(headerSub);

    // Inizializza DataTable
    table.DataTable({
        data,
        columns,
        columnDefs: [
            { targets: 0, visible: false, searchable: false },
            { targets: kmColumnIndexes, className: 'kmTh' }
        ],
        order: [[0, 'asc']],
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
        }
    });
});
</script>
@endpush
