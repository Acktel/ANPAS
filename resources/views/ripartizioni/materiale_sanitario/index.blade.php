{{-- resources/views/ripartizioni/materiale_sanitario/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="container-title">TABELLA DI CALCOLO DELLE PERCENTUALI INERENTI IL NUMERO DEI SERVIZI SVOLTI AL FINE DELLA RIPARTIZIONE DEI COSTI DI OSSIGENO E MATERIALE SANITARIO																						
 − Anno {{ $anno }}</h1>
    </div>

    <div class="table-responsive">
        <table id="table-materiale" class="table table-bordered w-100 text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th style="display:none;"></th>              {{-- idAutomezzo hidden --}}
                    <th>Automezzo</th>                          {{-- staticColumns[1] --}}
                    <th>Targa</th>                              {{-- staticColumns[2] --}}
                    <th>Codice ID</th>                          {{-- staticColumns[3] --}}
                    <th>Incluso</th>                            {{-- staticColumns[4] --}}
                    @foreach($convenzioni as $conv)              {{-- convenzioni dinamiche --}}
                        <th>{{ $conv->Convenzione }}</th>
                    @endforeach
                    <th>Totale</th>                             {{-- colonna finale --}}
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    {{-- colspan = 1(hidden)+4 statiche+count(convenzioni)+1 totale --}}
                    <td colspan="{{ 5 + count($convenzioni) + 1 }}" class="text-end">
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

    // Costruzione colonne per DataTable
    const columns = [
        { data: 'idAutomezzo', visible: false },
        { data: 'Automezzo' },
        { data: 'Targa' },
        { data: 'CodiceIdentificativo' },
        {
            data: 'incluso_riparto',
            render: (d, t, row) => `
                <select class="form-select form-select-sm switch-inclusione" data-id="${row.idAutomezzo}">
                    <option value="1"${d ? ' selected' : ''}>SI</option>
                    <option value="0"${!d ? ' selected' : ''}>NO</option>
                </select>`
        }
    ];

    convenzioni.forEach(conv => {
        columns.push({
            data: `valori.${conv.idConvenzione}`,
            className: 'valore-servizio',
            defaultContent: 0,
            render: (d, t, row) => row.incluso_riparto ? (d || 0) : 0
        });
    });

    columns.push({
        data: 'totale_riga',
        className: 'totale-riga',
        render: (d, t, row) => row.incluso_riparto ? d : 0
    });

    // Inizializza DataTable
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
        drawCallback: function () {
            let totale = 0;
            this.rows().every(function () {
                const d = this.data();
                if (d.incluso_riparto) totale += parseInt(d.totale_riga || 0, 10);
            });
            $('#totale-inclusi').text(totale);
        }
    });

    // Gestione cambio inclusione
    $('#table-materiale').on('change', '.switch-inclusione', function () {
        const select = $(this);
        const id = select.data('id');
        const inc = select.val() === '1';

        fetch("{{ route('ripartizioni.materiale_sanitario.aggiornaInclusione') }}", {
            method: 'POST',
            credentials: 'same-origin',        // includi i cookie di sessione
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',  // forza risposta JSON
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ idAutomezzo: id, incluso: inc })
        })
        .then(async response => {
            if (!response.ok) {
                // prova a leggere il JSON di errore, altrimenti il testo
                let errMsg;
                try {
                    const errJson = await response.json();
                    errMsg = errJson.message || JSON.stringify(errJson);
                } catch {
                    errMsg = await response.text();
                }
                console.error('Server risponde con errore', response.status, errMsg);
                alert(`Errore ${response.status}: ${errMsg}`);
                throw new Error(errMsg);
            }
            return response.json();
        })
        .then(_ => {
            // aggiornamento riuscito: aggiorno la riga in DataTable
            const idx = dt.rows().eq(0).filter(i => dt.row(i).data().idAutomezzo === id);
            const rd = dt.row(idx).data();
            rd.incluso_riparto = inc;
            dt.row(idx).data(rd).invalidate().draw(false);
        })
        .catch(err => {
            // qui arriva SOLO se il then() sopra ha fatto throw
            console.error('Errore in fetch:', err);
        });

    });
});
</script>
@endpush
