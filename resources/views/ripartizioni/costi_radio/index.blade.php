@extends('layouts.app')


@section('title', 'Costi Radio')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title mb-0">Distinta Rilevazione Analitica Costi Radio − Anno {{ $anno }}</h1>
    </div>

@if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
<div class="mb-3" style="max-width:400px; position: relative;">
    <form method="POST" action="{{ route('sessione.setAssociazione') }}" id="assocFilterForm">
        @csrf
        <div class="input-group">
            <!-- Campo visibile -->
            <input
                id="assocInput"
                type="text"
                class="form-control text-start"
                placeholder="Seleziona associazione"
                value="{{ optional($associazioni->firstWhere('idAssociazione', $idAssociazione))->Associazione ?? '' }}"
                readonly
            >

            <!-- Bottone per aprire/chiudere -->
            <button type="button" id="assocDropdownToggle" class="btn btn-outline-secondary" aria-expanded="false" title="Mostra elenco">
                <i class="fas fa-chevron-down"></i>
            </button>

            <!-- Hidden input -->
            <input type="hidden" name="idAssociazione" id="assocHidden" value="{{ $idAssociazione ?? '' }}">
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


    @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card-anpas mb-3">
        <div class="card-body d-flex align-items-center">
            <h3 class="mb-0">NUMERO TOTALE AUTOMEZZI: </h3>&nbsp;
            <h3 class="mb-0 fw-bold text-anpas-green"> {{ $numeroAutomezzi }} </h3>
        </div>
    </div>

    <div class="mb-3 d-flex justify-content-end">
        <a href="{{ route('ripartizioni.costi_radio.editTotale') }}" class="btn btn-anpas-edit">
            <i class="fas fa-edit me-1"></i> Modifica Totali a Bilancio
        </a>
    </div>
    

    <div class="card-anpas">
        <div class="card-body">
            <table id="costiRadioTable" class="common-css-dataTable table table-hover table-bordered w-100 table-striped-anpas">
                <thead class="thead-anpas text-center">
                    <tr>
                        <th>Targa</th>
                        <th>MANUTENZIONE APPARATI RADIO</th>
                        <th>MONTAGGIO/SMONTAGGIO RADIO 118</th>
                        <th>LOCAZIONE PONTE RADIO</th>
                        <th>AMMORTAMENTO IMPIANTI RADIO</th>
                        <th>Azioni</th>
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
    $(function() {
        let totaleRow = null; // Dichiariamo fuori

        $('#costiRadioTable').DataTable({
            stateDuration: -1,
            stateSave: true,  
            ajax: {
                url: '{{ route("ripartizioni.costi_radio.getData") }}',
                dataSrc: function(res) {
                    let data = res.data || [];

                    // Sposta la riga "TOTALE" in fondo
                    totaleRow = data.find(r => r.is_totale === -1); // Salviamo qui
                    data = data.filter(r => r.is_totale !== -1);
                    // if (totaleRow) data.push(totaleRow);

                    return data;
                }
            },
            processing: true,
            serverSide: false,
            paging: true,
            searching: false,
            ordering: true,
            order: [],
            info: false,
            stripeClasses: ['odd', 'even'],
            columns: [
                { data: 'Targa', title: 'Automezzo' },
                { data: 'ManutenzioneApparatiRadio', className: 'text-end' },
                { data: 'MontaggioSmontaggioRadio118', className: 'text-end' },
                { data: 'LocazionePonteRadio', className: 'text-end' },
                { data: 'AmmortamentoImpiantiRadio', className: 'text-end' },
                {
                    data: null,
                    className: 'text-center',
                    render: function(row) {
                        return (row.is_totale === -1)
                            ? `<a href="{{ route('ripartizioni.costi_radio.editTotale') }}" class="btn btn-anpas-edit"><i class="fas fa-edit"></i></a>`
                            : '-';
                    }
                },
                { data: 'is_totale', visible: false, searchable: false }
            ],
            rowCallback: function(row, data, index) {
                if (data.is_totale === -1) {
                    $(row).addClass('table-warning fw-bold');
                }

                $(row).removeClass('odd even').addClass(index % 2 === 0 ? 'even' : 'odd');
            },
drawCallback: function(settings) {
    const api = this.api();
    const pageRows = api.rows({ page: 'current' }).nodes();

    $(pageRows).filter('.totale-row').remove();

    if (totaleRow) {
        const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

        api.columns().every(function(index) {
            const col = api.settings()[0].aoColumns[index];
            if (!col.bVisible) return;

            let cellValue = '';
            if (typeof col.mRender === 'function') {
                cellValue = col.mRender(totaleRow, 'display', null, { row: -1, col: index, settings });
            } else if (col.mData) {
                cellValue = totaleRow[col.mData] ?? '';
            }

            const alignmentClass = col.sClass || ''; // className come text-end, text-center ecc.
            $lastRow.append(`<td class="${alignmentClass}">${cellValue}</td>`);
        });

        $(api.table().body()).append($lastRow);
    }
},
            language: {
                url: '/js/i18n/Italian.json',
                                paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
            }
        });
    });
</script>





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

