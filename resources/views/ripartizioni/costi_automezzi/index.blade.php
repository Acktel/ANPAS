@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">
        Distinta Rilevazione Analitica Costi Automezzi e Attrezzatura Sanitaria − Anno {{ $anno }}
    </h1>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <table id="table-costi-automezzi" class="table table-striped-anpas table-bordered w-100 text-center align-middle">
            <thead>
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

    columns.push(
        { data: 'Targa' },
        { data: 'CodiceIdentificativo' },
        { data: 'LeasingNoleggio' },
        { data: 'Assicurazione' },
        { data: 'ManutenzioneOrdinaria' },
        { data: 'ManutenzioneStraordinaria' },
        { data: 'RimborsiAssicurazione' },
        { data: 'PuliziaDisinfezione' },
        { data: 'Carburanti' },
        { data: 'Additivi' },
        { data: 'RimborsiUTF' },
        { data: 'InteressiPassivi' },
        { data: 'AltriCostiMezzi' },
        { data: 'ManutenzioneSanitaria' },
        { data: 'LeasingSanitaria' },
        { data: 'AmmortamentoMezzi' },
        { data: 'AmmortamentoSanitaria' },
        {
            data: 'idAutomezzo',
            className: 'col-actions text-center',
            orderable: false,
            render: function (id, type, row) {
                if (row.is_totale == -1) return '-';
                return `
                    <a href="/ripartizioni/costi-automezzi/${id}/edit" class="btn btn-sm btn-anpas-edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form method="POST" action="/ripartizioni/costi-automezzi/${id}" class="d-inline-block" onsubmit="return confirm('Confermi eliminazione?')">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash"></i></button>
                    </form>
                    <a href="/ripartizioni/costi-automezzi/${id}" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i>
                    </a>
                `;
            }
        }
    );

    $(function () {
        $('#table-costi-automezzi').DataTable({
            ajax: '{{ route('ripartizioni.costi_automezzi.data') }}',
            columns: columns,
            paging: false,
            searching: false,
            info: false,
            language: {
                url: '/js/i18n/Italian.json'
            },
            order: [[0, 'asc']],
            orderFixed: [[0, 'asc']],
            rowCallback: function (row, data) {
                if (data.is_totale == -1) {
                    $(row).addClass('table-warning fw-bold');
                }
            }
        });
    });
</script>
@endpush


