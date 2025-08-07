@extends('layouts.app')


@section('title', 'Costi Radio')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title mb-0">Distinta Rilevazione Analitica Costi Radio − Anno {{ $anno }}</h1>
    </div>

    @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
        <div class="mb-3">
            <form method="POST" action="{{ route('sessione.setAssociazione') }}">
                @csrf
                <select name="idAssociazione" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
                    @foreach($associazioni as $assoc)
                        <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == $idAssociazione ? 'selected' : '' }}>
                            {{ $assoc->Associazione }}
                        </option>
                    @endforeach
                </select>
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

        $('#costiRadioTable').DataTable({
            ajax: {
    url: '{{ route("ripartizioni.costi_radio.getData") }}',
    dataSrc: function(res) {
        let data = res.data || [];

        // Sposta la riga "TOTALE" in fondo
        const totaleRow = data.find(r => r.is_totale === -1);
        data = data.filter(r => r.is_totale !== -1);
        if (totaleRow) data.push(totaleRow);

        return data;
    }
},
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            ordering: true,
            order: [], // is_totale
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
                
                    //Serve a forzare l'aggiunta delle classi odd/even per zebra striping se non basta "stripeClasses: ['odd', 'even']"
                    $(row).removeClass('odd even').addClass(index % 2 === 0 ? 'even' : 'odd');
                },
            language: {
                url: '/js/i18n/Italian.json'
            }
        });
    });
</script>
@endpush
