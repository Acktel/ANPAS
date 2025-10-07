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
<div class="mb-3">
    <form method="POST" action="{{ route('sessione.setAssociazione') }}" id="assocFilterForm" class="w-100" style="max-width:400px; position:relative;">
        @csrf
        <div class="input-group">
            <!-- Campo visibile -->
            <input
                type="text"
                id="assocInput"
                name="assocLabel"
                class="form-control"
                autocomplete="off"
                placeholder="Seleziona associazione"
                value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
            >

            <!-- Bottone integrato -->
            <button type="button" id="assocFilterToggleBtn" class="btn btn-outline-secondary"
                    aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
                <i class="fas fa-chevron-down"></i>
            </button>

            <!-- Campo nascosto con l'id reale -->
            <input type="hidden" id="assocId" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
        </div>

        <!-- Dropdown custom -->
        <ul id="assocDropdown" class="list-group position-absolute w-100" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0;
                   background-color:#fff; opacity:1; -webkit-backdrop-filter:none; backdrop-filter:none;">
            @foreach($associazioni as $assoc)
                <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
                    {{ $assoc->Associazione }}
                </li>
            @endforeach
        </ul>
    </form>
</div>

    @endif
    {{-- Tabella --}}
    <div class="table-responsive">
        <table id="table-km" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
            <thead class="thead-anpas">
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
        const { data: rawData, labels } = await res.json();
        if (!rawData.length) return;

        const totaleRow = rawData.find(r => r.is_totale === -1);
        let data = rawData.filter(r => r.is_totale !== -1);

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
                if (!row.idAutomezzo || row.Targa === 'TOTALE') return '-';
                return `
            <a href="/km-percorsi/${row.idAutomezzo}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza">
                <i class="fas fa-eye"></i>
            </a>
            <a href="/km-percorsi/${row.idAutomezzo}/edit" class="btn btn-warning me-1 btn-icon" title="Modifica"> 
                <i class="fas fa-edit"></i>
            </a>
            <form method="POST" action="/km-percorsi/${row.idAutomezzo}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-danger btn-icon" title="Elimina">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        `;
            }
        });


        $('#header-main').html(headerMain);
          $('#header-main th').each(function() {
      if ($(this).attr('colspan')) {
       $(this).addClass('border-bottom-special');
      }
    });
        $('#header-sub').html(headerSub);

        table.DataTable({
            data,
            columns,
            columnDefs: [{
                    targets: 0,
                    visible: false,
                    searchable: false
                }, // is_totale
                // {
                //     targets: kmColumnIndexes,
                //     className: 'kmTh'
                // }
            ],
            stateDuration: -1,
            stateSave: true, 
            order: [],// ordina per is_totale (0: normali, -1: totale)
            responsive: true,
            language: {
                url: '/js/i18n/Italian.json',
                                paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
            },
    rowCallback: (rowEl, rowData, index) => {
      if (rowData.is_totale === -1) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },
            stripeClass: ['table-striped-anpas'],


            drawCallback: function(settings) {
    const api = this.api();
    const pageRows = api.rows({ page: 'current' }).nodes();

    // Rimuovi eventuali righe "TOTALE" precedenti (evita duplicazioni)
    $(pageRows).filter('.totale-row').remove();

    // Aggiungi la riga TOTALE alla fine della pagina
    if (totaleRow) {
        const columnCount = api.columns().visible().reduce((acc, isVisible) => acc + (isVisible ? 1 : 0), 0);
        const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

        api.columns().every(function(index) {
            const col = columns[index];
            if (col.visible === false) return;

            let cellValue = '';
            if (typeof col.render === 'function') {
                cellValue = col.render(totaleRow, 'display', null, { row: -1, col: index, settings });
            } else if (col.data) {
                cellValue = totaleRow[col.data] ?? '';
            }

            $lastRow.append(`<td>${cellValue}</td>`);
        });

        $(api.table().body()).append($lastRow);
    }
},

            
        });
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const assocInput = document.getElementById('assocInput');
    const assocId = document.getElementById('assocId');
    const assocForm = document.getElementById('assocFilterForm');
    const toggleBtn = document.getElementById('assocFilterToggleBtn');
    const dropdown = document.getElementById('assocDropdown');
    const options = Array.from(dropdown.querySelectorAll('li.assoc-item'));

    // Funzione per filtrare dropdown
    function filterDropdown() {
        const val = assocInput.value.toLowerCase();
        let anyVisible = false;
        options.forEach(opt => {
            if (opt.textContent.toLowerCase().includes(val)) {
                opt.style.display = '';
                anyVisible = true;
            } else {
                opt.style.display = 'none';
            }
        });
        dropdown.style.display = anyVisible ? 'block' : 'none';
    }

    // Toggle dropdown con bottone
    toggleBtn.addEventListener('click', function() {
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';
        this.setAttribute('aria-expanded', !isVisible);
        if (!isVisible) assocInput.focus();
    });

    // Filtra mentre si scrive
    assocInput.addEventListener('input', filterDropdown);

    // Seleziona un'opzione
    options.forEach(opt => {
        opt.addEventListener('click', function() {
            assocInput.value = this.textContent;
            assocId.value = this.dataset.id;
            dropdown.style.display = 'none';
            toggleBtn.setAttribute('aria-expanded', false);
            assocForm.submit();
        });
    });

    // Chiudi dropdown cliccando fuori
    document.addEventListener('click', function(e) {
        if (!assocForm.contains(e.target)) {
            dropdown.style.display = 'none';
            toggleBtn.setAttribute('aria-expanded', false);
        }
    });
});
</script>




@endpush