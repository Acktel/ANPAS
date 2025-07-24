@extends('layouts.app')

@section('title', 'Costi Radio')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Distinta Rilevazione Analitica Costi Radio</h1>

    @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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
            ajax: '{{ route("ripartizioni.costi_radio.getData") }}',
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            ordering: true,
            order: [[6, 'asc']], // is_totale
            orderFixed: [[6, 'asc']],
            info: false,
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
                            ? `<a href="{{ route('ripartizioni.costi_radio.editTotale') }}" class="btn btn-sm btn-anpas-edit"><i class="fas fa-edit"></i></a>`
                            : '-';
                    }
                },
                { data: 'is_totale', visible: false, searchable: false }
            ],
            rowCallback: function(row, data) {
                if (data.is_totale === -1) {
                    $(row).addClass('table-warning fw-bold');
                }
            },
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
            }
        });
    });
</script>
@endpush
