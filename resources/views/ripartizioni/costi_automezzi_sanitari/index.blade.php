@extends('layouts.app')

@section('title', 'Ripartizione Costi Automezzi e Radio')
@section('content')



<div class="container-fluid">
    <h1 class="container-title mb-4">Tabella di riepilogo ripartizione costi automezzi - materiale ed attrezzatura sanitaria - costi radio</h1>

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
                    {{ $auto->Targa }} - {{ $auto->CodiceIdentificativo }}
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


    <div id="regimiBtnBar" class="d-flex justify-content-end mb-3 d-none">
        <div class="btn-group" role="group" aria-label="Regimi mezzi">
            <button id="btnRotazione" type="button" class="btn btn-anpas-green d-none">
                <i class="fas fa-sync-alt me-1"></i> Rotazione mezzi
            </button>
            <button id="btnSostitutivi" type="button" class="btn btn-anpas-green d-none">
                <i class="fas fa-exchange-alt me-1"></i> Mezzi sostitutivi
            </button>
        </div>
    </div>

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
    document.addEventListener('DOMContentLoaded', function() {
        function formatEuro(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '€ 0,00';
            return '€ ' + num.toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function caricaTabellaCosti(idAutomezzo) {
            AnpasLoader.show();
            const params = {
                idAutomezzo
            };
            @if($isElevato) params.idAssociazione = $('#selectAssociazione').val();
            @endif

            $.get("{{ route('ripartizioni.costi_automezzi_sanitari.tabellaFinale') }}", params, function(res) {
                const righe = res.data || [];
                const colonne = res.colonne || (righe.length ? Object.keys(righe[0]) : []);
                const meta = res.meta || {};
                const rotMeta = meta.rotazione || {
                    colonne: [],
                    voci: [],
                    showBtn: false
                };
                const sosMeta = meta.sostitutivi || {
                    colonne: [],
                    voci: [],
                    showBtn: false
                };

                if (!colonne.length) {
                    $('#tabellaCosti').hide();
                    $('#regimiBtnBar').addClass('d-none');
                    AnpasLoader.hide();
                    return;
                }

                // Header
                const headerHtml = colonne.map(c => {
                    const isConv = (c !== 'voce' && c !== 'totale');
                    let cls = '';
                    if (isConv && rotMeta.colonne.includes(c)) cls += ' th-rotazione';
                    if (isConv && sosMeta.colonne.includes(c)) cls += ' th-sostitutivi';
                    return `<th class="${cls.trim()}">${c.toUpperCase()}</th>`;
                }).join('');
                $('#tabellaCosti thead tr#headerFinale').html(headerHtml);

                // Body
                // Body
                const bodyHtml = righe.length ?
                    righe.map(riga => {
                        const voce = (riga['voce'] || '').toString();
                        const isTotRow = voce.toUpperCase() === 'TOTALI';
                        const trCls = isTotRow ? 'fw-bold bg-light' : '';

                        return `<tr class="${trCls}">` + colonne.map(col => {
                            const v = riga[col];

                            // prima colonna: descrizione voce
                            if (col === 'voce') {
                                return `<td class="text-start">${(v || v === 0) ? v : '-'}</td>`;
                            }

                            // calcolo eventuali classi di evidenziazione per la cella
                            let cls = '';
                            const isColConvenzione = (col !== 'voce' && col !== 'totale');
                            const isRotVoce = rotMeta.voci.includes(voce);
                            const isSosVoce = sosMeta.voci.includes(voce);
                            if (isColConvenzione) {
                                if (isRotVoce && rotMeta.colonne.includes(col)) cls = 'cell-rotazione';
                                if (isSosVoce && sosMeta.colonne.includes(col)) cls = (cls ? cls + ' ' : '') + 'cell-sostitutivi';
                            }

                            // colonna totale (destra, formattata)
                            if (col === 'totale') {
                                const num = parseFloat(v);
                                return `<td class="text-end ${cls}">${isNaN(num) ? '€ 0,00' : formatEuro(num)}</td>`;
                            }

                            // convenzioni: numerico -> euro; altrimenti '-'
                            const num = parseFloat(v);
                            if (typeof v === 'number' || (!isNaN(num) && v !== '')) {
                                return `<td class="text-end ${cls}">${formatEuro(num)}</td>`;
                            }
                            return `<td class="${cls}">${(v || v === 0) ? v : '-'}</td>`;
                        }).join('') + '</tr>';
                    }).join('') :
                    `<tr>${colonne.map(() => '<td>€ 0,00</td>').join('')}</tr>`;



                $('#tabellaCosti tbody').html(bodyHtml);
                $('#tabellaCosti tbody tr').each((i, el) => $(el).removeClass('odd even').addClass(i % 2 === 0 ? 'even' : 'odd'));
                $('#tabellaCosti').show();

                // BTN group regime: mostra solo se automezzo specifico e almeno una colonna attiva nel relativo regime
                const isMezzoSpecifico = (idAutomezzo && idAutomezzo !== 'TOT');
                const showBar = isMezzoSpecifico && (rotMeta.showBtn || sosMeta.showBtn);
                $('#regimiBtnBar').toggleClass('d-none', !showBar);

                // Wiring pulsanti
                if (showBar) {
                    const routeRot = (meta.routeDettaglio && meta.routeDettaglio.rotazione) || '#';
                    const routeSos = (meta.routeDettaglio && meta.routeDettaglio.sostitutivi) || '#';

                    // set/disable visibilità singoli
                    $('#btnRotazione').toggleClass('d-none', !rotMeta.showBtn);
                    $('#btnSostitutivi').toggleClass('d-none', !sosMeta.showBtn);

                    // click -> naviga con query
                    $('#btnRotazione').off('click').on('click', function() {
                        const qs = new URLSearchParams({
                            idAutomezzo: idAutomezzo,
                            @if($isElevato) idAssociazione: $('#selectAssociazione').val() || '',
                            @endif
                        }).toString();
                        window.location = routeRot + '?' + qs;
                    });

                    $('#btnSostitutivi').off('click').on('click', function() {
                        const qs = new URLSearchParams({
                            idAutomezzo: idAutomezzo,
                            @if($isElevato) idAssociazione: $('#selectAssociazione').val() || '',
                            @endif
                        }).toString();
                        window.location = routeSos + '?' + qs;
                    });
                }

                AnpasLoader.hide();
            });
        }


        // Cambio AUTOMEZZO
        $('#selectAutomezzo').on('change', function() {
            const idAutomezzo = $(this).val();
            if (!idAutomezzo) {
                $('#tabellaCosti').hide();
                return;
            }
            caricaTabellaCosti(idAutomezzo);
        });

        // Cambio ASSOCIAZIONE (solo utenti elevati)
        $('#selectAssociazione').on('change', function() {
            $('#selectAutomezzo').prop('disabled', true).html('<option value="TOT" selected>TOTALE</option>');
            $('#tabellaCosti').hide();

            const idAss = $(this).val();
            if (!idAss) return;

            // Ricarico elenco automezzi per l’associazione selezionata
            $.get('/get-automezzi/' + idAss, function(data) {
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