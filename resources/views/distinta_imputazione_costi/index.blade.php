@extends('layouts.app')

@php
$user = Auth::user();
@endphp

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Distinta Imputazione Costi − Anno {{ $anno }}
    </h1>
  </div>

  @if($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating)
  <form method="POST" action="{{ route('sessione.setAssociazione') }}" class="mb-3 d-flex align-items-center gap-2">
    @csrf
    <label for="assocSelect" class="mb-0 fw-bold">Associazione:</label>
    <select id="assocSelect" name="idAssociazione" class="form-select w-auto" onchange="this.form.submit()">
      @foreach($associazioni as $assoc)
      <option value="{{ $assoc->IdAssociazione }}"
        {{ session('associazione_selezionata') == $assoc->IdAssociazione ? 'selected' : '' }}>
        {{ $assoc->Associazione }}
      </option>
      @endforeach
    </select>
  </form>
  @endif

  @php
  $sezioni = [
    2 => 'Automezzi e Attrezzature Sanitarie',
    3 => 'Attrezzatura Sanitaria',
    4 => 'Telecomunicazioni',
    5 => 'Costi gestione struttura',
    6 => 'Costo del personale',
    7 => 'Materiale sanitario di consumo',
    8 => 'Costi amministrativi',
    9 => 'Quote di ammortamento',
    10 => 'Beni Strumentali inferiori a 516,00 euro',
    11 => 'Altri costi'
  ];
  @endphp

  <div class="accordion" id="accordionDistinta">
    @foreach ($sezioni as $id => $titolo)
    <div class="accordion-item mb-2">
      <h2 class="accordion-header" id="heading-{{ $id }}">
        <button class="accordion-button collapsed" type="button"
          data-bs-toggle="collapse"
          data-bs-target="#collapse-{{ $id }}"
          aria-expanded="false"
          aria-controls="collapse-{{ $id }}">
          <div class="row w-100 text-start gx-2">
            <div class="col-6 fw-bold">{{ $titolo }}</div>
            <div class="col-2" id="summary-bilancio-{{ $id }}">-</div>
            <div class="col-2" id="summary-diretta-{{ $id }}">-</div>
            <div class="col-2" id="summary-totale-{{ $id }}">-</div>
          </div>
        </button>
      </h2>
      <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionDistinta">
        <div class="accordion-body">
          <div class="mb-2 text-end">
            <a href="#" class="btn btn-sm btn-anpas-green">
              <i class="fas fa-plus me-1"></i> Aggiungi Voce
            </a>
          </div>
          <div class="table-responsive">
          <table id="table-distinta-{{ $id }}"
            class="common-css-dataTable table table-hover table-striped-anpas table-bordered w-100 mb-0">
            <thead class="thead-anpas">
              <tr id="header-main-{{ $id }}">
                <th rowspan="2">Voce</th>
                <th rowspan="2">Importo Totale da Bilancio Consuntivo</th>
                <th rowspan="2">Costi di Diretta Imputazione</th>
                <th rowspan="2">Totale Costi Ripartiti</th>
              </tr>
              <tr id="header-sub-{{ $id }}">
              </tr>
            </thead>
            <tbody class="sortable" data-sezione="{{ $id }}"></tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
    @endforeach

    <div class="accordion-item mt-4">
      <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
        <div class="row w-100 text-start gx-2">
          <div class="col-6">Totale generale</div>
          <div class="col-2" id="tot-bilancio">-</div>
          <div class="col-2" id="tot-diretta">-</div>
          <div class="col-2" id="tot-totale">-</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  window.distintaCosti = {
    sezioni: @json($sezioni),
    csrf: '{{ csrf_token() }}'
  };

  $(function() {
    const intestazioniAggiunte = new Set();

    $.ajax({
      url: '{{ route("distinta.imputazione.data") }}',
      method: 'GET',
      success: function(response) {
        const convenzioni = response.convenzioni;
        const righe = response.data;

        const totaliGenerali = {
          bilancio: 0,
          diretta: 0,
          totale: 0
        };

        const totaliPerSezione = {};

        Object.keys(window.distintaCosti.sezioni).forEach(idSezione => {
          const headerMain = $(`#header-main-${idSezione}`);
          const headerSub = $(`#header-sub-${idSezione}`);

          if (!intestazioniAggiunte.has(idSezione)) {
            convenzioni.forEach(conv => {
              headerMain.append(`<th colspan="2" class="text-center">${conv}</th>`);
              headerSub.append(`<th class="text-center">Diretti</th><th class="text-center">Indiretti</th>`);
            });
            intestazioniAggiunte.add(idSezione);
          }

          // Inizializza i totali di sezione
          totaliPerSezione[idSezione] = {
            bilancio: 0,
            diretta: 0,
            totale: 0
          };
        });

        righe.forEach(riga => {
          const idSezione = riga.sezione_id;
          if (!idSezione) return;

          const $tbody = $(`tbody[data-sezione="${idSezione}"]`);
          if ($tbody.length === 0) return;

          let html = `<tr>
            <td>${riga.voce}</td>
            <td class="text-end">${riga.bilancio?.toFixed(2) ?? '0.00'}</td>
            <td class="text-end">${riga.diretta?.toFixed(2) ?? '0.00'}</td>
            <td class="text-end">${riga.totale?.toFixed(2) ?? '0.00'}</td>`;

          Object.keys(convenzioni).forEach(key => {
            const valore = riga[convenzioni[key]] || {};
            const diretti = valore.diretti ?? 0;
            const indiretti = valore.indiretti ?? 0;

            html += `<td class="text-end">${parseFloat(diretti).toFixed(2)}</td>`;
            html += `<td class="text-end">${parseFloat(indiretti).toFixed(2)}</td>`;
          });

          html += `</tr>`;
          $tbody.append(html);

          // Aggiorna i totali di sezione
          totaliPerSezione[idSezione].bilancio += parseFloat(riga.bilancio || 0);
          totaliPerSezione[idSezione].diretta += parseFloat(riga.diretta || 0);
          totaliPerSezione[idSezione].totale += parseFloat(riga.totale || 0);

          // Aggiorna i totali generali
          totaliGenerali.bilancio += parseFloat(riga.bilancio || 0);
          totaliGenerali.diretta += parseFloat(riga.diretta || 0);
          totaliGenerali.totale += parseFloat(riga.totale || 0);
        });

        // Popola riepilogo sezioni
        Object.keys(totaliPerSezione).forEach(id => {
          $(`#summary-bilancio-${id}`).text(totaliPerSezione[id].bilancio.toFixed(2));
          $(`#summary-diretta-${id}`).text(totaliPerSezione[id].diretta.toFixed(2));
          $(`#summary-totale-${id}`).text(totaliPerSezione[id].totale.toFixed(2));
        });

        // Totali finali
        $('#tot-bilancio').text(totaliGenerali.bilancio.toFixed(2));
        $('#tot-diretta').text(totaliGenerali.diretta.toFixed(2));
        $('#tot-totale').text(totaliGenerali.totale.toFixed(2));
      },
      error: function(xhr) {
        console.error("Errore caricamento distinta costi", xhr);
      }
    });
  });
</script>
@endpush
