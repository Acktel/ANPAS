@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      TABELLA DI CALCOLO DELLE PERCENTUALI INERENTI IL NUMERO DEI SERVIZI SVOLTI AL FINE DELLA RIPARTIZIONE DEI COSTI DI OSSIGENO E MATERIALE SANITARIO − Anno {{ $anno }}
    </h1>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <div class="table-responsive">
        <table id="table-materiale" class="table table-bordered table-striped-anpas w-100 text-center align-middle">
          <thead class="thead-anpas">
            <tr>
              <th style="display: none;"></th> {{-- idAutomezzo --}}
              <th>Automezzo</th>
              <th>Targa</th>
              <th>Codice ID</th>
              <th>Incluso</th>
              @foreach($convenzioni as $conv)
                <th>{{ $conv->Convenzione }}</th>
              @endforeach
              <th>Totale</th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot class="table-light fw-bold">
            <tr>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(async function () {
  const response = await fetch("{{ route('ripartizioni.materiale_sanitario.data') }}");
  const json = await response.json();

// Estrai e rimuovi la riga dei totali dai dati
let totaleRow = null;

const data = Object.entries(json.righe).reduce((acc, [id, riga]) => {
  if (riga.is_totale === true) {
    totaleRow = {
      ...riga,
      idAutomezzo: parseInt(id, 10),
      totale_riga: riga.totale ?? 0
    };
    return acc; // non aggiungere la riga al dataset
  }

  acc.push({
    ...riga,
    idAutomezzo: parseInt(id, 10),
    totale_riga: riga.totale ?? 0
  });

  return acc;
}, []);

  const convenzioni = json.convenzioni;

  const columns = [
    { data: 'idAutomezzo', visible: false },
    { data: 'Automezzo' },
    { data: 'Targa' },
    { data: 'CodiceIdentificativo' },
    {
      data: 'incluso_riparto',
      render: val => val ? 'SI' : 'NO'
    }
  ];

  convenzioni.forEach(conv => {
    columns.push({
      data: `valori.${conv.idConvenzione}`,
      className: 'valore-servizio',
      defaultContent: 0,
      render: (val, t, row) => row.incluso_riparto ? (val || 0) : 0
    });
  });

  columns.push({
    data: 'totale_riga',
    className: 'totale-riga',
    render: val => val || 0
  });

  $('#table-materiale').DataTable({
    data,
    columns,
    paging: true,
    searching: false,
    ordering: false,
    info: false,
    responsive: true,
    language: {
      url: '/js/i18n/Italian.json'
    },
        rowCallback: (rowEl, rowData, index) => {
      if (rowData.is_totale === true) {
        $(rowEl).addClass('table-warning fw-bold');
      }
      $(rowEl).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
    },

    stripeClass: ['table-striped-anpas'],


drawCallback: function (settings) {
  const api = this.api();
  const pageRows = api.rows({ page: 'current' }).nodes();

  // Rimuovi righe "TOTALE" precedenti per evitare duplicati
  $(pageRows).filter('.totale-row').remove();

  // Trova la riga totale tra i dati
if (!totaleRow) return;

  // Se esiste la riga TOTALE
  if (totaleRow) {
    const $lastRow = $('<tr>').addClass('table-warning fw-bold totale-row');

    api.columns().every(function (index) {
      const col = columns[index];

      // Salta le colonne nascoste
      if (col.visible === false) return;

      let cellValue = '';

      if (typeof col.render === 'function') {
        // Usa il renderer della colonna per il valore del totale
        cellValue = col.render(totaleRow[col.data], 'display', totaleRow, {
          row: -1,
          col: index,
          settings,
        });
      } else if (col.data) {
        // Altrimenti usa il valore direttamente
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
@endpush
