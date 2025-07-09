@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title">Ripartizione materiale sanitario − Anno {{ $anno }}</h1>
    </div>

    <div class="table-responsive">
        <table id="table-materiale" class="table table-bordered w-100 text-center align-middle">
            <thead class="table-light">
                <tr id="table-header-row"></tr>
            </thead>
            <tbody></tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <!-- Colspan deve essere 13, non 999 -->
                    <td colspan="13" class="text-end">
                        Totale incluso nel riparto: <span id="totale-inclusi">0</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(async function () {
    const res = await fetch("{{ route('ripartizioni.materiale_sanitario.data') }}");
    const json = await res.json();
    const data = Object.entries(json.righe).map(([id, riga]) => ({
        ...riga,
        idAutomezzo: parseInt(id, 10),
        totale_riga: riga.totale ?? 0
    }));
    const convenzioni = json.convenzioni;

    const staticColumns = [
        { key: 'idAutomezzo', label: 'ID', hidden: true },
        { key: 'Automezzo', label: 'Automezzo', hidden: false },
        { key: 'Targa', label: 'Targa', hidden: false },
        { key: 'CodiceIdentificativo', label: 'Codice ID', hidden: false },
        {
            key: 'incluso_riparto',
            label: 'Incluso',
            hidden: false,
            render: (d,t,row) => `
                <select class="form-select form-select-sm switch-inclusione" data-id="${row.idAutomezzo}">
                    <option value="1" ${d ? 'selected' : ''}>SI</option>
                    <option value="0" ${!d ? 'selected' : ''}>NO</option>
                </select>`
        }
    ];

    let headerHtml = '';
    const columns = [];

    staticColumns.forEach(col => {
        headerHtml += col.hidden
            ? `<th style="display:none;"></th>`
            : `<th>${col.label}</th>`;

        columns.push({
            data: col.key,
            visible: !col.hidden,
            render: col.render || undefined
        });
    });

    convenzioni.forEach(conv => {
        headerHtml += `<th>${conv.Convenzione}</th>`;
        columns.push({
            data: `valori.${conv.idConvenzione}`,
            className: 'valore-servizio',
            defaultContent: 0,
            render: (d,t,row) => row.incluso_riparto ? (d||0) : 0
        });
    });

    headerHtml += `<th>Totale</th>`;
    columns.push({
        data: 'totale_riga',
        className: 'totale-riga',
        render: (d,t,row) => row.incluso_riparto ? d : 0
    });

    $('#table-header-row').html(headerHtml);

    // Debug (opzionale)
    console.log("Colonne:", columns.length, "TH:", $('#table-header-row th').length);

    const dt = $('#table-materiale').DataTable({
        data,
        columns,
        paging: false,
        searching: false,
        ordering: false,
        info: false,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
        },
        rowCallback: (row, data) => {
            $(row).toggleClass('table-secondary', !data.incluso_riparto);
        },
        drawCallback: ricalcolaTotale
    });

    function ricalcolaTotale() {
        let tot = 0;
        dt.rows().every(function () {
            const d = this.data();
            if (d.incluso_riparto) tot += parseInt(d.totale_riga||0,10);
        });
        $('#totale-inclusi').text(tot);
    }

    $('#table-materiale').on('change', '.switch-inclusione', function () {
        const select = $(this);
        const id = select.data('id');
        const inc = select.val()==='1';
        fetch("{{ route('ripartizioni.materiale_sanitario.aggiornaInclusione') }}", {
            method: 'POST',
            headers: {
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':'{{ csrf_token() }}'
            },
            body: JSON.stringify({ idAutomezzo: id, incluso: inc })
        })
        .then(_=> {
            const idx = dt.rows().eq(0).filter(i=>dt.row(i).data().idAutomezzo===id);
            const rd = dt.row(idx).data();
            rd.incluso_riparto = inc;
            dt.row(idx).data(rd).invalidate().draw(false);
        })
        .catch(err => {
            console.error(err);
            alert("Errore durante il salvataggio");
        });
    });
});
</script>
@endpush
