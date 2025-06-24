@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Riepilogo Costi − Anno {{ $anno }}
  </h1>

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna voce presente per l’anno {{ $anno }}.<br>
    Vuoi importare le voci dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  @php
    $sezioni = [
      2  => 'Automezzi',
      3  => 'Attrezzatura Sanitaria',
      4  => 'Telecomunicazioni',
      5  => 'Costi gestione struttura',
      6  => 'Costo del personale',
      7  => 'Materiale sanitario di consumo',
      8  => 'Costi amministrativi',
      9  => 'Quote di ammortamento',
      10 => 'Beni Strumentali inferiori a 516,00 euro',
      11 => 'Altri costi'
    ];
  @endphp

  <div class="accordion" id="accordionRiep">
    @foreach ($sezioni as $id => $titolo)
      <div class="accordion-item mb-2">
        <h2 class="accordion-header" id="heading-{{ $id }}">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapse-{{ $id }}"
                  aria-expanded="false"
                  aria-controls="collapse-{{ $id }}">
            <div class="row w-100 text-start gx-2">
              <div class="col-5 fw-bold">{{ $titolo }}</div>
              <div class="col-2" id="summary-prev-{{ $id }}">-</div>
              <div class="col-2" id="summary-cons-{{ $id }}">-</div>
              <div class="col-2" id="summary-scos-{{ $id }}">-</div>
            </div>
          </button>
        </h2>
        <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionRiep">
          <div class="accordion-body">
            <div class="mb-2 text-end">
              <a href="{{ route('riepilogo.costi.create', $id) }}"
                 class="btn btn-sm btn-anpas-green">
                <i class="fas fa-plus me-1"></i> Aggiungi Voce
              </a>
            </div>
            <table id="table-sezione-{{ $id }}"
                   class="common-css-dataTable table table-hover table-striped-anpas table-bordered w-100 mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th>Voce</th>
                  <th>Preventivo</th>
                  <th>Consuntivo</th>
                  <th>% Scostamento</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    @endforeach

    {{-- Totale Generale --}}
<div class="accordion-item mt-4">
  <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
    <div class="row w-100 text-start gx-2">
      <div class="col-5">Totale generale</div>
      <div class="col-2" id="tot-prev">€0.00</div>
      <div class="col-2" id="tot-cons">€0.00</div>
      <div class="col-2" id="tot-scos">0%</div>
    </div>
  </div>
</div>


  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const italian = "https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json";
  const csrf = document.head.querySelector('meta[name="csrf-token"]').content;

  let totalePreventivo = 0;
  let totaleConsuntivo = 0;

  const sezioni = @json($sezioni);
  Object.keys(sezioni).forEach(id => {
    $('#table-sezione-' + id).DataTable({
      ajax: `/riepilogo-costi/sezione/${id}`,
      columns: [
        { data: 'descrizione' },
        { data: 'preventivo' },
        { data: 'consuntivo' },
        { data: 'scostamento' },
        { data: 'actions', orderable: false, searchable: false }
      ],
      language: { url: italian },
      stripeClasses: ['table-striped-anpas',''],
      initComplete(settings, json) {
        let prev = 0, cons = 0;
        json.data.forEach(r => {
          prev += parseFloat(r.preventivo) || 0;
          cons += parseFloat(r.consuntivo) || 0;
        });

        const sc = prev ? (((cons - prev) / prev) * 100).toFixed(2) + '%' : '0%';

        // Sezione corrente
        document.getElementById(`summary-prev-${id}`).textContent = '€' + prev.toFixed(2);
        document.getElementById(`summary-cons-${id}`).textContent = '€' + cons.toFixed(2);
        document.getElementById(`summary-scos-${id}`).textContent = sc;

        // Totali generali
        totalePreventivo += prev;
        totaleConsuntivo += cons;
        const totScostamento = totalePreventivo
          ? (((totaleConsuntivo - totalePreventivo) / totalePreventivo) * 100).toFixed(2) + '%'
          : '0%';

        document.getElementById('tot-prev').textContent = '€' + totalePreventivo.toFixed(2);
        document.getElementById('tot-cons').textContent = '€' + totaleConsuntivo.toFixed(2);
        document.getElementById('tot-scos').textContent = totScostamento;
      }
    });
  });

  // Duplicazione voci
  fetch("{{ route('riepilogo.costi.checkDuplicazione') }}")
    .then(r => r.json())
    .then(d => {
      if (d.mostraMessaggio) {
        document.getElementById('noDataMessage').classList.remove('d-none');
      }
    });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    this.disabled = true;
    this.innerText = 'Duplicazione…';

    const res = await fetch("{{ route('riepilogo.costi.duplica') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      }
    });

    if (res.ok) location.reload();
    else {
      alert('Errore duplicazione');
      this.disabled = false;
      this.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', () => {
    document.getElementById('noDataMessage').classList.add('d-none');
  });
});
</script>
@endpush
