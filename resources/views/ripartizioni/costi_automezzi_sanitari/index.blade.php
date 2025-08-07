@extends('layouts.app')

@section('title', 'Ripartizione Costi Automezzi e Radio')
@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Ripartizione Costi Automezzi e Radio - Anno {{ $anno }}</h1>

    @if($isElevato)
    <div class="row mb-3">
        <div class="col-md-6">
            <label>Associazione</label>
            <select id="selectAssociazione" class="form-control">
                <option value="">-- Seleziona Associazione --</option>
                @foreach ($associazioni as $assoc)
                <option value="{{ $assoc->id }}">{{ $assoc->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label>Automezzo</label>
            <select id="selectAutomezzo" class="form-control" disabled>
                <option value="">-- Seleziona Automezzo --</option>
            </select>
        </div>
    </div>
    @else
    <div class="row mb-3">
        <div class="col-md-6">
            <label>Automezzo</label>
            <select id="selectAutomezzo" class="form-control">
                <option value="">-- Seleziona Automezzo --</option>
                @foreach ($automezzi as $auto)
                <option value="{{ $auto->idAutomezzo }}">{{ $auto->Targa }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @endif

    <table id="tabellaCosti" class="table table-striped-anpas table-bordered w-100 text-center align-middle" style="display: none;">
        {{-- aggiunta classe thead --}}
        <thead class="thead-anpas">
            <tr id="headerFinale"></tr>
        </thead>
        <tbody></tbody>
        <tfoot></tfoot>
    </table>
</div>
@endsection
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // recuperiamo il CSRF token, se serve in futuro
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content;

        // se sei “elevato”, quando cambio associazione resetto l’automezzo e la tabella
        @if($isElevato)
        $('#selectAssociazione').on('change', function() {
            $('#selectAutomezzo')
                .prop('disabled', true)
                .html('<option value="">-- Seleziona Automezzo --</option>');
            $('#tabellaCosti').hide();
            const idAss = $(this).val();
            if (!idAss) return;
            $.get('/get-automezzi/' + idAss, function(data) {
                data.forEach(a => {
                    $('#selectAutomezzo').append(
                        `<option value="${a.id}">${a.text}</option>`
                    );
                });
                $('#selectAutomezzo').prop('disabled', false);
            });
        });
        @endif

        // ad ogni selezione di automezzo carico la tabella
        $('#selectAutomezzo').on('change', function() {
            const idAutomezzo = $(this).val();
            if (!idAutomezzo) {
                $('#tabellaCosti').hide();
                return;
            }
            caricaTabellaCosti(idAutomezzo);
        });

        function formatEuro(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '€ 0,00';
            return '€ ' + num.toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function caricaTabellaCosti(idAutomezzo) {
            // setto i parametri
            const params = {
                idAutomezzo: idAutomezzo
            };
            @if($isElevato)
            params.idAssociazione = $('#selectAssociazione').val();
            @endif

            // chiamo il controller
            $.get("{{ route('ripartizioni.costi_automezzi_sanitari.tabellaFinale') }}", params, function(res) {
                const righe = res.data || [];
                let colonne = [];

                // se ho righe uso le chiavi del primo oggetto
                if (righe.length) {
                    colonne = Object.keys(righe[0]);
                } else {
                    // fallback: uso il ’colonne’ che ho passato da PHP
                    colonne = res.colonne || [];
                }

                // se ancora non ci sono colonne non mostro nulla
                if (!colonne.length) {
                    $('#tabellaCosti').hide();
                    return;
                }

                // costruisco l’header (uppercase)
                const headerHtml = colonne
                    .map(c => `<th>${c.toUpperCase()}</th>`)
                    .join('');
                $('#tabellaCosti thead tr#headerFinale')
                    .html(headerHtml);

                // costruisco il body (righe)
                let bodyHtml = '';
                if (!righe.length) {
                    // riga di zeri
                    const vuoti = colonne.map(_ => '€ 0,00').map(v => `<td>${v}</td>`).join('');
                    bodyHtml = `<tr>${vuoti}</tr>`;
                } else {
                    bodyHtml = righe.map(riga => {
                        return '<tr>' + colonne.map(col => {
                            const v = riga[col];
                            // se è numero lo formatto
                            if (typeof v === 'number' || (!isNaN(v) && v !== '')) {
                                return `<td class="text-end">${formatEuro(v)}</td>`;
                            }
                            // altrimenti testo o trattino
                            return `<td>${(v||v===0)? v : '-'}</td>`;
                        }).join('') + '</tr>';
                    }).join('');
                }
                $('#tabellaCosti tbody').html(bodyHtml);
                $('#tabellaCosti tbody tr').each(function(i) {
                    // fila 0,2,4... = even (sfondo bianco), 1,3,5... = odd (verde chiaro)
                    $(this)
                        .removeClass('odd even')
                        .addClass(i % 2 === 0 ? 'even' : 'odd');
                });
                $('#tabellaCosti').show();
            });
        }
    });
</script>
@endpush