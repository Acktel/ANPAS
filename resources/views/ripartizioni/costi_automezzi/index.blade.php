@extends('layouts.app')

@section('content')
<div class="container-fluid">


    <h1 class="container-title mb-4">
        Distinta Rilevazione Analitica Costi Automezzi e Attrezzatura Sanitaria − Anno {{ $anno }}
    </h1>

        @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
<div class="d-flex mb-3 position-relative" style="max-width:400px">
    <form id="assocFilterForm" action="{{ route('sessione.setAssociazione') }}" method="POST" class="w-100">
        @csrf
        <div class="input-group">
            <!-- Campo visibile -->
            <input type="text" id="assocInput" class="form-control text-start" placeholder="Seleziona associazione"
                   value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}" readonly>

            <!-- Bottone -->
            <button type="button" id="assocDropdownToggle" class="btn btn-outline-secondary" aria-expanded="false" title="Mostra elenco">
                <i class="fas fa-chevron-down"></i>
            </button>

            <!-- Hidden input -->
            <input type="hidden" name="idAssociazione" id="assocHidden" value="{{ $selectedAssoc ?? '' }}">
        </div>

        <!-- Dropdown -->
        <ul id="assocDropdown" class="list-group position-absolute w-100" style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0; background-color:#fff;">
            @foreach($associazioni as $assoc)
                <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
                    {{ $assoc->Associazione }}
                </li>
            @endforeach
        </ul>
    </form>
</div>
    @endif

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
            stateDuration: -1,
            stateSave: true,  
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
                url: '/js/i18n/Italian.json',
                                paginate: {
            first: '<i class="fas fa-angle-double-left"></i>',
            last: '<i class="fas fa-angle-double-right"></i>',
            next: '<i class="fas fa-angle-right"></i>',
            previous: '<i class="fas fa-angle-left"></i>'
        },
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

    <script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('assocDropdownToggle');
    const dropdown = document.getElementById('assocDropdown');
    const assocInput = document.getElementById('assocInput');
    const assocHidden = document.getElementById('assocHidden');
    const form = document.getElementById('assocFilterForm');

    if (!toggleBtn || !dropdown) return;

    // Mostra/nasconde dropdown
    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
    });

    // Click su un elemento
    document.querySelectorAll('.assoc-item').forEach(item => {
        item.addEventListener('click', function () {
            const text = this.textContent.trim();
            const id = this.dataset.id;

            assocInput.value = text;
            assocHidden.value = id;

            dropdown.style.display = 'none';
            assocInput.style.textAlign = 'left';

            form.submit();
        });
    });

    // Chiude dropdown se clicchi fuori
    document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});
</script>

  <script>
    (function () {
      // cerca prima un elemento con id, altrimenti prende il primo .alert.alert-success
      const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
      if (!flash) return;

      // aspetta 3500ms (3.5s) poi fa fade + collapse e rimuove l'elemento
      setTimeout(() => {
        // animazione: opacità + altezza
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
        flash.style.opacity = '0';
        // per lo "slide up" imposta max-height e padding a 0
        flash.style.maxHeight = flash.scrollHeight + 'px'; // inizializza
        // forza repaint per sicurezza
        // eslint-disable-next-line no-unused-expressions
        flash.offsetHeight;
        flash.style.maxHeight = '0';
        flash.style.paddingTop = '0';
        flash.style.paddingBottom = '0';
        flash.style.marginTop = '0';
        flash.style.marginBottom = '0';

        // rimuovi dal DOM dopo che l'animazione è finita
        setTimeout(() => {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 600); // lascia un po' di tempo alla transizione
      }, 3500);
    })();
  </script>
@endpush