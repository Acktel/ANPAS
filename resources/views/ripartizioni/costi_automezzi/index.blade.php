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

    <h1 class="container-title mb-4">
        Distinta Rilevazione Analitica Costi Automezzi e Attrezzatura Sanitaria − Anno {{ $anno }}
    </h1>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <table id="table-costi-automezzi" class="table table-striped-anpas table-bordered w-100 text-center align-middle">
            <thead class="thead-anpas">
                <tr>
                    @if ($showAssociazione)
                    <th>Associazione</th>
                    @endif
                    <th>Targa</th>
                    <th>Codice Identificativo</th>
                    <th>Leasing/Noleggio a lungo termine</th>
                    <th>Assicurazione</th>
                    <th>Manutenzione ordinaria</th>
                    <th>Manutenzione straordinaria</th>
                    <th>Rimborsi da assicurazioni</th>
                    <th>Pulizia e disinfezione automezzi</th>
                    <th>Carburanti</th>
                    <th>Additivi</th>
                    <th>Rimborsi UTF</th>
                    <th>Interessi passivi fin.to/leasing/noleggio</th>
                    <th>Altri costi mezzi</th>
                    <th>Manutenzione attrezzatura sanitaria</th>
                    <th>Leasing attrezzatura sanitaria</th>
                    <th>Ammortamento automezzi</th>
                    <th>Ammortamento attrezzature sanitarie</th>
                    <th>Azioni</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
    const showAssociazione = @json($showAssociazione);

    const columns = [];

    if (showAssociazione) {
        columns.push({
            data: 'Associazione'
        });
    }

    columns.push({
        data: 'Targa'
    }, {
        data: 'CodiceIdentificativo'
    }, {
        data: 'LeasingNoleggio'
    }, {
        data: 'Assicurazione'
    }, {
        data: 'ManutenzioneOrdinaria'
    }, {
        data: 'ManutenzioneStraordinaria'
    }, {
        data: 'RimborsiAssicurazione'
    }, {
        data: 'PuliziaDisinfezione'
    }, {
        data: 'Carburanti'
    }, {
        data: 'Additivi'
    }, {
        data: 'RimborsiUTF'
    }, {
        data: 'InteressiPassivi'
    }, {
        data: 'AltriCostiMezzi'
    }, {
        data: 'ManutenzioneSanitaria'
    }, {
        data: 'LeasingSanitaria'
    }, {
        data: 'AmmortamentoMezzi'
    }, {
        data: 'AmmortamentoSanitaria'
    }, {
        data: 'idAutomezzo',
        className: 'col-actions text-center',
        orderable: false,
        render: function(id, type, row) {
            if (!row || row.is_totale == -1) return '-';
            return `
                <a href="/ripartizioni/costi-automezzi/${id}" class="btn btn-anpas-green me-1 btn-icon" title="Visualizza">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="/ripartizioni/costi-automezzi/${id}/edit" class="btn btn-warning me-1 btn-icon" title="Modifica">
                    <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="/ripartizioni/costi-automezzi/${id}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger btn-icon" title="Elimina"><i class="fas fa-trash"></i></button>
                </form>
            `;
        }
    });

    $(function() {
        const selectedAssoc = document.getElementById('assocSelect')?.value || '';
        const url = '{{ route('ripartizioni.costi_automezzi.data') }}' + 
                    (selectedAssoc ? `?idAssociazione=${selectedAssoc}` : '');

        let totaleRow = null; // variabile per riga totale

        $('#table-costi-automezzi').DataTable({
            ajax: {
                url: url,
                dataSrc: function(res) {
                    let data = res.data || [];

                    // Trova e rimuovi la riga totale
                    totaleRow = data.find(r => r.is_totale === -1);
                    data = data.filter(r => r.is_totale !== -1);

                    return data;
                }
            },
            columns: columns,
            paging: true,
            searching: false,
            info: false,
            language: {
                url: '/js/i18n/Italian.json'
            },
            order: [],

            rowCallback: (rowEl, rowData, index) => {
                if (rowData.is_totale === -1) {
                    $(rowEl).addClass('table-warning fw-bold');
                }
                $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
            },

            drawCallback: function(settings) {
                const api = this.api();
                const pageRows = api.rows({ page: 'current' }).nodes();

                // Rimuovi eventuali righe "TOTALE" precedenti (evita duplicazioni)
                $(pageRows).filter('.totale-row').remove();

                // Aggiungi la riga TOTALE alla fine della pagina
                if (totaleRow) {
                    const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

                    api.columns().every(function(index) {
                        const col = columns[index];
                        if (col.visible === false) return;

                        let cellValue = '';
                        if (typeof col.render === 'function') {
                            cellValue = col.render(
                                totaleRow[col.data],  // singolo dato per la cella
                                'display',            // tipo render
                                totaleRow,            // riga completa
                                { row: -1, col: index, settings }
                            );
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

@endpush