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
    $(async function() {
    const selectedAssoc = document.getElementById('assocSelect')?.value || null;

    const res = await fetch("{{ route('km-percorsi.datatable') }}");
     const {
            data,
            labels
        } = await res.json();
        if (!data.length) return;
        const table = $('#table-km');

        const staticColumns = [{
                key: 'is_totale',
                label: '',
                hidden: true
            },
            {
                key: 'idAutomezzo',
                label: 'ID',
                hidden: true
            },
            {
                key: 'Automezzo',
                label: 'Automezzo'
            },
            {
                key: 'Targa',
                label: 'Targa'
            },
            {
                key: 'CodiceIdentificativo',
                label: 'Codice ID'
            },
            {
                key: 'Totale',
                label: 'KM Totali'
            },
        ];

        const convenzioni = Object.keys(labels).sort((a, b) => parseInt(a.slice(1)) - parseInt(b.slice(1)));

        let headerMain = '';
        let headerSub = '';
        const columns = [];
        let visibleIndex = 0;
        const kmColumnIndexes = [];

        staticColumns.forEach(col => {
            headerMain += `<th rowspan="2"${col.hidden ? ' style="display:none"' : ''}>${col.label}</th>`;
            columns.push({
                data: col.key,
                visible: !col.hidden
            });
            if (!col.hidden) visibleIndex++;
        });

        convenzioni.forEach(conv => {
            headerMain += `<th colspan="2">${labels[conv]}</th>`;
            headerSub += `<th class="kmTh">Km Percorsi</th><th>%</th>`;
            columns.push({
                data: `${conv}_km`
            });
            kmColumnIndexes.push(visibleIndex);
            visibleIndex++;
            columns.push({
                data: `${conv}_percent`
            });
            visibleIndex++;
        });

        headerMain += `<th rowspan="2">Azioni</th>`;
        columns.push({
            data: null,
            orderable: false,
            searchable: false,
            createdCell: function(td) {
                $(td).addClass('col-azioni');
            },
            render: function(row) {
                if (!row.idAutomezzo || row.Automezzo === 'TOTALE') return '-';
                return `
            <a href="/km-percorsi/${row.idAutomezzo}" class="btn btn-sm btn-info me-1 btn-icon" title="Visualizza">
                <i class="fas fa-eye"></i>
            </a>
            <a href="/km-percorsi/${row.idAutomezzo}/edit" class="btn btn-sm btn-warning me-1 btn-icon" title="Modifica"> 
                <i class="fas fa-edit"></i>
            </a>
            <form method="POST" action="/km-percorsi/${row.idAutomezzo}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Elimina">
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
            columnDefs: [{
                    targets: 0,
                    visible: false,
                    searchable: false
                }, // is_totale
                {
                    targets: kmColumnIndexes,
                    className: 'kmTh'
                }
            ],
            order: [
                [0, 'asc']
            ], // ordina per is_totale (0: normali, -1: totale)
            orderFixed: [
                [0, 'asc']
            ], // fissa la riga totale in cima
            responsive: true,
            language: {
                url: '/js/i18n/Italian.json'
            },
            rowCallback: function(row, data, index) {
                if (index % 2 === 0) {
                    $(row).removeClass('even').removeClass('odd').addClass('even');
                } else {
                    $(row).removeClass('even').removeClass('odd').addClass('odd');
                }
            },
            stripeClasses: ['table-white', 'table-striped-anpas'],
        });
    });
</script>
@endpush