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
              <td colspan="{{ 5 + count($convenzioni) + 1 }}" class="text-end">
                Totale incluso nel riparto: <span id="totale-inclusi">0</span>
              </td>
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

  const data = Object.entries(json.righe).map(([id, riga]) => ({
    ...riga,
    idAutomezzo: parseInt(id, 10),
    totale_riga: riga.totale ?? 0
  }));

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
    paging: false,
    searching: false,
    ordering: false,
    info: false,
    responsive: true,
    language: {
      url: '/js/i18n/Italian.json'
    },
    rowCallback: (row, data, index) => {
      $(row).toggleClass('table-secondary', !data.incluso_riparto);
      if (index % 2 === 0) {
        $(row).removeClass('even odd').addClass('even');
      } else {
        $(row).removeClass('even odd').addClass('odd');
      }
    },
    stripeClasses: ['table-white', 'table-striped-anpas'],
    drawCallback: function () {
      let totale = 0;
      this.rows().every(function () {
        const row = this.data();
        if (row.incluso_riparto) {
          totale += parseInt(row.totale_riga || 0, 10);
        }
      });
      $('#totale-inclusi').text(totale);
    }
  });
});
</script>
@endpush
