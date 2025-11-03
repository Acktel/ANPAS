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

  // ===== Formatter a 2 decimali (locale it-IT) =====
  const moneyKeys = new Set([
    'LeasingNoleggio','Assicurazione','ManutenzioneOrdinaria','ManutenzioneStraordinaria',
    'RimborsiAssicurazione','PuliziaDisinfezione','Carburanti','Additivi','RimborsiUTF',
    'InteressiPassivi','AltriCostiMezzi','ManutenzioneSanitaria','LeasingSanitaria',
    'AmmortamentoMezzi','AmmortamentoSanitaria'
  ]);

  function fmt2(v){
    const n = Number(v);
    return Number.isFinite(n)
      ? n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      : (v ?? '');
  }

  const columns = [];

  function colDef(key){
    const def = { data: key };
    if (moneyKeys.has(key)) {
      def.render = function(data){ return fmt2(data); };
      def.className = (def.className ? def.className + ' ' : '') + 'text-end';
    }
    return def;
  }

  if (showAssociazione) {
    columns.push({ data: 'Associazione' });
  }

  columns.push(
    colDef('Targa'),
    colDef('CodiceIdentificativo'),
    colDef('LeasingNoleggio'),
    colDef('Assicurazione'),
    colDef('ManutenzioneOrdinaria'),
    colDef('ManutenzioneStraordinaria'),
    colDef('RimborsiAssicurazione'),
    colDef('PuliziaDisinfezione'),
    colDef('Carburanti'),
    colDef('Additivi'),
    colDef('RimborsiUTF'),
    colDef('InteressiPassivi'),
    colDef('AltriCostiMezzi'),
    colDef('ManutenzioneSanitaria'),
    colDef('LeasingSanitaria'),
    colDef('AmmortamentoMezzi'),
    colDef('AmmortamentoSanitaria'),
    {
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
    }
  );

  $(function() {
    // usa l'hidden reale
    const selectedAssoc = document.getElementById('assocHidden')?.value || '';
    const url = '{{ route('ripartizioni.costi_automezzi.data') }}' +
                (selectedAssoc ? `?idAssociazione=${encodeURIComponent(selectedAssoc)}` : '');

    let totaleRow = null;

    $('#table-costi-automezzi').DataTable({
      stateDuration: -1,
      stateSave: true,
      ajax: {
        url: url,
        dataSrc: function(res) {
          let data = res.data || [];
          // isola TOTALE
          totaleRow = data.find(r => r.is_totale === -1) || null;
          data = data.filter(r => r.is_totale !== -1);
          return data;
        }
      },
      columns: columns,
      // sorting numerico corretto anche con render localizzato
      columnDefs: [{
        targets: columns.map((c,i)=> moneyKeys.has(c.data) ? i : -1).filter(i=> i>=0),
        render: {
          _: function(data){ return fmt2(data); },
          sort: function(data){ return Number(data) || 0; }
        }
      }],
      paging: true,
      searching: true,
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

        // pulisci eventuali TOTALE precedenti
        $(pageRows).filter('.totale-row').remove();

        if (totaleRow) {
          const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

          api.columns().every(function(index) {
            const col = columns[index];
            if (col.visible === false) return;

            let cellValue = '';
            if (col.data) {
              // forza formattazione a 2 decimali sulle colonne money
              const raw = totaleRow[col.data];
              cellValue = moneyKeys.has(col.data) ? fmt2(raw) : (raw ?? '');
            } else if (typeof col.render === 'function') {
              cellValue = col.render(null, 'display', totaleRow, { row: -1, col: index, settings });
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
    const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
    if (!flash) return;

    setTimeout(() => {
      flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
      flash.style.opacity = '0';
      flash.style.maxHeight = flash.scrollHeight + 'px';
      // forza repaint
      // eslint-disable-next-line no-unused-expressions
      flash.offsetHeight;
      flash.style.maxHeight = '0';
      flash.style.paddingTop = '0';
      flash.style.paddingBottom = '0';
      flash.style.marginTop = '0';
      flash.style.marginBottom = '0';

      setTimeout(() => {
        if (flash.parentNode) flash.parentNode.removeChild(flash);
      }, 600);
    }, 3500);
  })();
</script>
@endpush
