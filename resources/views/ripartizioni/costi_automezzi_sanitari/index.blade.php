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

    <table id="tabellaCosti" class="table table-bordered table-striped w-100" style="display: none;">
        <thead>
            <tr id="headerFinale"></tr>
        </thead>
        <tbody></tbody>
        <tfoot></tfoot>
    </table>
</div>
@endsection
@push('scripts')
<script>
function formatEuro(val) {
    const num = parseFloat(val);
    if (isNaN(num)) return '€ 0,00';
    return '€ ' + num.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function caricaTabellaCosti(idAutomezzo) {
    // Svuota tabella
    $('#tabellaCosti thead tr#headerFinale').empty();
    $('#tabellaCosti tbody').empty();
    $('#tabellaCosti tfoot').empty();

    $.get(`{{ route('ripartizioni.costi_automezzi_sanitari.tabellaFinale') }}?idAutomezzo=${idAutomezzo}`, function (data) {
        let righe = data.data || [];

        // Ricava sempre le colonne, anche se righe è vuoto
        let colonne = [];
        if (righe.length > 0) {
            colonne = Object.keys(righe[0]);
        } else if (data.colonne) {
            // fallback: struttura fissa dal backend
            colonne = data.colonne;
        }

        // Se ancora non ci sono colonne, non mostrare la tabella
        if (colonne.length === 0) {
            $('#tabellaCosti').hide();
            return;
        }

        // Header
        const headerHtml = colonne.map(col => `<th>${col.toUpperCase()}</th>`).join('');
        $('#tabellaCosti thead tr#headerFinale').html(headerHtml);

        // Se non ci sono righe, genera una riga vuota con zeri
        if (righe.length === 0) {
            const emptyRow = colonne.map(col => '<td>€ 0,00</td>').join('');
            $('#tabellaCosti tbody').html(`<tr>${emptyRow}</tr>`);
        } else {
            const bodyHtml = righe.map(riga => {
                return '<tr>' + colonne.map(col => {
                    const val = riga[col];
                    const isNumero = typeof val === 'number' || (!isNaN(val) && val !== '');
                    const display = isNumero ? formatEuro(val) : (val ?? '-');
                    return `<td>${display}</td>`;
                }).join('') + '</tr>';
            }).join('');
            $('#tabellaCosti tbody').html(bodyHtml);
        }

        $('#tabellaCosti').show();
    });
}

$(document).ready(function () {
    @if($isElevato)
    $('#selectAssociazione').on('change', function () {
        const idAssociazione = $(this).val();
        $('#selectAutomezzo').prop('disabled', true).html('<option value="">-- Seleziona Automezzo --</option>');
        $('#tabellaCosti').hide();

        if (!idAssociazione) return;

        $.get('/get-automezzi/' + idAssociazione, function (data) {
            data.forEach(auto => {
                $('#selectAutomezzo').append(`<option value="${auto.id}">${auto.text}</option>`);
            });
            $('#selectAutomezzo').prop('disabled', false);
        });
    });
    @endif

    $('#selectAutomezzo').on('change', function () {
        const idAutomezzo = $(this).val();
        if (idAutomezzo) {
            caricaTabellaCosti(idAutomezzo);
        } else {
            $('#tabellaCosti').hide();
        }
    });
});
</script>
@endpush
