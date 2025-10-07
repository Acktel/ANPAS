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
                    <option value="{{ $assoc->idAssociazione }}" {{ (string)$assoc->idAssociazione === (string)$selectedAssoc ? 'selected' : '' }}>
                        {{ $assoc->Associazione }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label>Automezzo</label>
            <select id="selectAutomezzo" class="form-control" {{ empty($selectedAssoc) ? 'disabled' : '' }}>
                <option value="TOT" {{ $selectedAutomezzo === 'TOT' ? 'selected' : '' }}>TOTALE</option>
                @foreach ($automezziAssoc as $auto)
                    <option value="{{ $auto->idAutomezzo }}" {{ (string)$selectedAutomezzo === (string)$auto->idAutomezzo ? 'selected' : '' }}>
                        {{ $auto->Targa }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    @else
    <div class="row mb-3">
        <div class="col-md-6">
            <label>Automezzo</label>
            <select id="selectAutomezzo" class="form-control">
                <option value="TOT" {{ $selectedAutomezzo === 'TOT' ? 'selected' : '' }}>TOTALE</option>
                @foreach ($automezziAssoc as $auto)
                    <option value="{{ $auto->idAutomezzo }}" {{ (string)$selectedAutomezzo === (string)$auto->idAutomezzo ? 'selected' : '' }}>
                        {{ $auto->Targa }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    @endif

    <table id="tabellaCosti" class="table table-striped-anpas table-bordered w-100 text-center align-middle" style="display:none;">
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
document.addEventListener('DOMContentLoaded', function () {
    function formatEuro(val) {
        const num = parseFloat(val);
        if (isNaN(num)) return '€ 0,00';
        return '€ ' + num.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function caricaTabellaCosti(idAutomezzo) {
        const params = { idAutomezzo };
        @if($isElevato)
        params.idAssociazione = $('#selectAssociazione').val();
        @endif

        $.get("{{ route('ripartizioni.costi_automezzi_sanitari.tabellaFinale') }}", params, function (res) {
            const righe = res.data || [];
            const colonne = res.colonne || (righe.length ? Object.keys(righe[0]) : []);

            if (!colonne.length) {
                $('#tabellaCosti').hide();
                return;
            }

            const headerHtml = colonne.map(c => `<th>${c.toUpperCase()}</th>`).join('');
            $('#tabellaCosti thead tr#headerFinale').html(headerHtml);

            const bodyHtml = righe.length
                ? righe.map(riga => '<tr>' + colonne.map(col => {
                    const v = riga[col];
                    return (typeof v === 'number' || (!isNaN(v) && v !== ''))
                        ? `<td class="text-end">${formatEuro(v)}</td>`
                        : `<td>${(v || v === 0) ? v : '-'}</td>`;
                }).join('') + '</tr>').join('')
                : `<tr>${colonne.map(() => '<td>€ 0,00</td>').join('')}</tr>`;

            $('#tabellaCosti tbody').html(bodyHtml);
            $('#tabellaCosti tbody tr').each((i, el) => $(el).removeClass('odd even').addClass(i % 2 === 0 ? 'even' : 'odd'));
            $('#tabellaCosti').show();
        });
    }

    // Cambio AUTOMEZZO
    $('#selectAutomezzo').on('change', function () {
        const idAutomezzo = $(this).val();
        if (!idAutomezzo) {
            $('#tabellaCosti').hide();
            return;
        }
        caricaTabellaCosti(idAutomezzo);
    });

    // Cambio ASSOCIAZIONE (solo utenti elevati)
    $('#selectAssociazione').on('change', function () {
        $('#selectAutomezzo').prop('disabled', true).html('<option value="TOT" selected>TOTALE</option>');
        $('#tabellaCosti').hide();

        const idAss = $(this).val();
        if (!idAss) return;

        // Ricarico elenco automezzi per l’associazione selezionata
        $.get('/get-automezzi/' + idAss, function (data) {
            data.forEach(a => {
                $('#selectAutomezzo').append(`<option value="${a.id}">${a.text}</option>`);
            });
            $('#selectAutomezzo').prop('disabled', false);

            // carico tabella con TOTALE (salverà anche in sessione tramite getTabellaFinale)
            caricaTabellaCosti('TOT');
        });
    });

    // Avvio: se c’è già un’associazione selezionata e (eventualmente) un automezzo, carico subito
    @if($isElevato)
        const assocIniz = $('#selectAssociazione').val();
        if (assocIniz) {
            // se abbiamo già opzioni automezzo (dal server), usa la selezione corrente; altrimenti verranno caricate via ajax al change
            const autoIniz = $('#selectAutomezzo').val() || 'TOT';
            caricaTabellaCosti(autoIniz);
        }
    @else
        const autoIniz = $('#selectAutomezzo').val() || 'TOT';
        caricaTabellaCosti(autoIniz);
    @endif
});
</script>
@endpush
