@extends('layouts.app')

@section('title', 'Imputazione Materiale Sanitario di Consumo')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Imputazione Costi Materiale Sanitario di Consumo</h1>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card-anpas mb-3">
    <div class="card-body d-flex flex-column align-items-start">
      <h4 class="mb-2">
        NUMERO TOTALE SERVIZI EFFETTUATI NELL'ESERCIZIO<br>
        <span class="text-danger fw-bold">
          (al netto dei servizi effettuati per convenzioni MSA, MSAB e ASA <u>SE PRESENTI</u>):
        </span>
        <span id="totaleServizi" class="fw-bold text-anpas-green align-items-center">
          {{ isset($totale_inclusi) ? number_format($totale_inclusi, 0, ',', '.') : 'N/A' }}
        </span>
      </h4>
    </div>      
  </div>
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
  

  <div class="mb-3 d-flex justify-content-end">
    <a href="{{ route('imputazioni.materiale_sanitario.editTotale') }}" class="btn btn-anpas-edit">
      <i class="fas fa-edit me-1"></i> Modifica Totale a Bilancio
    </a>
  </div>

  <div class="card-anpas">
    <div class="card-body">
      <table id="materialeSanitarioTable" class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap w-100 mb-0 text-center align-middle">
        <thead class="thead-anpas text-center">
          <tr>
            <th>Targa</th>
            <th>N. SERVIZI SINGOLO AUTOMEZZO</th>
            <th>PERCENTUALE DI RIPARTO</th>
            <th>IMPORTO</th>
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
$(function () {
  
    let totaleRow = null; // definito qui fuori, visibile ovunque nel DataTable

    let storedTotaleRow = null;


    $('#materialeSanitarioTable').DataTable({
        stateDuration: -1,
        stateSave: true,  
        ajax: {
            url: '{{ route("imputazioni.materiale_sanitario.getData") }}',

dataSrc: function(res) {
  console.log('AJAX response:', res);
    let data = res.data || [];

    // Estrai e salva la riga 'TOTALE'
    storedTotaleRow = data.find(r => r.is_totale === -1);
    console.log('storedTotaleRow:', storedTotaleRow); 
    data = data.filter(r => r.is_totale !== -1); // rimuovi la riga totale dai dati normali

    return data;
}
        },
        processing: true,
        serverSide: false,
        paging: true,
        searching: false,
        ordering: true,
        stripeClasses: ['odd', 'even'],
        order: [],
        info: false,
        columns: [
            { data: 'Targa' },
            { data: 'n_servizi', className: 'text-end' },
            {
                data: 'percentuale',
                className: 'text-end',
                render: d => d + '%'
            },
            {
                data: 'importo',
                className: 'text-end',
                render: d => parseFloat(d).toFixed(2).replace('.', ',')
            },
            {
                data: 'is_totale',
                visible: false,
                searchable: false
            }
        ],
        rowCallback: function (row, data, index) {
            if (data.is_totale === -1) {
                $(row).addClass('table-warning fw-bold');
            }
            $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    
            
        },
drawCallback: function(settings) {
    const api = this.api();
    const pageRows = api.rows({ page: 'current' }).nodes();

    // Rimuove eventuali duplicati
    $(pageRows).filter('.totale-row').remove();

    $('.dt-paging').addClass('margin-top-footer-paginate');

    // Inserisce la riga TOTALE salvata solo se esiste
    if (storedTotaleRow) {
        const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

        api.columns().every(function(index) {
            const col = api.settings()[0].aoColumns[index];
            if (!col.bVisible) return;

            let cellValue = '';
            const key = col.data;
            if (typeof col.render === 'function') {
                cellValue = col.render(storedTotaleRow[key], 'display', storedTotaleRow, { row: -1, col: index, settings });
            } else if (key) {
                cellValue = storedTotaleRow[key] ?? '';
            }

            const alignmentClass = col.sClass || '';
            $lastRow.append(`<td class="${alignmentClass}">${cellValue}</td>`);
        });

        $(api.table().body()).append($lastRow);
    }

    
},

        language: {
            url: '/js/i18n/Italian.json',
                        paginate: {
        first: '<i class="fas fa-angle-double-left"></i>',
        last: '<i class="fas fa-angle-double-right"></i>',
        next: '<i class="fas fa-angle-right"></i>',
        previous: '<i class="fas fa-angle-left"></i>'
      }
        }
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
@endpush
