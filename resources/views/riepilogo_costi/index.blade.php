@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="mb-4">Riepilogo Costi - Anno {{ $anno }}</h1>

  <div id="noDataMessage" class="alert alert-info d-none">
      Nessuna voce presente per l’anno {{ $anno }}.<br>
      Vuoi importare le voci dall’anno precedente?
      <div class="mt-2">
          <button id="btn-duplica-si" class="btn btn-sm btn-success">Sì</button>
          <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
      </div>
  </div>

  @php
      $sezioni = [
          2 => 'Automezzi',
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

  <div class="accordion" id="accordionRiep">
    @foreach ($sezioni as $id => $titolo)
      <div class="accordion-item border mb-2">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed bg-light" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-{{ $id }}"
                  aria-expanded="false" aria-controls="collapse-{{ $id }}">
            <div class="container-fluid">
              <div class="row text-start">
                <div class="col-md-4 fw-bold">{{ $titolo }}</div>
                <div class="col-md-2" id="summary-prev-{{ $id }}">-</div>
                <div class="col-md-2" id="summary-cons-{{ $id }}">-</div>
                <div class="col-md-2" id="summary-scos-{{ $id }}">-</div>
              </div>
            </div>
          </button>
        </h2>
        <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionRiep">
          <div class="accordion-body">
            <div class="d-flex justify-content-between align-items-center mb-2">              
              <a href="{{ route('riepilogo.costi.create', $id) }}" class="btn btn-sm btn-info">
                ➕ Aggiungi Voce
              </a>
            </div>
            <table id="table-sezione-{{ $id }}" class="table table-bordered table-striped w-100"></table>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection

@push('scripts')
<script>
const italian = "https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json";
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

document.addEventListener('DOMContentLoaded', function () {
    const sezioni = @json($sezioni);

    Object.entries(sezioni).forEach(([id, titolo]) => {
        const selector = `#table-sezione-${id}`;

        $(selector).DataTable({
            ajax: `/riepilogo-costi/sezione/${id}`,
            columns: [
                { title: 'Voce', data: 'descrizione' },
                { title: 'Preventivo', data: 'preventivo' },
                { title: 'Consuntivo', data: 'consuntivo' },
                { title: '% Scostamento', data: 'scostamento' },
                {
                    title: 'Azioni',
                    data: 'actions',
                    orderable: false,
                    searchable: false
                }
            ],
            language: { url: italian },
            initComplete: function (settings, json) {
                let totalPrev = 0, totalCons = 0;
                json.data.forEach(r => {
                    totalPrev += parseFloat(r.preventivo);
                    totalCons += parseFloat(r.consuntivo);
                });

                const scost = totalPrev !== 0
                    ? (((totalCons - totalPrev) / totalPrev) * 100).toFixed(2) + '%'
                    : '0%';

                document.getElementById(`summary-prev-${id}`).textContent = '€' + totalPrev.toFixed(2);
                document.getElementById(`summary-cons-${id}`).textContent = '€' + totalCons.toFixed(2);
                document.getElementById(`summary-scos-${id}`).textContent = scost;
            }
        });
    });

    // Duplicazione logica
    fetch("{{ route('riepilogo.costi.checkDuplicazione') }}")
        .then(res => res.json())
        .then(data => {
            if (data.mostraMessaggio) {
                document.getElementById('noDataMessage')?.classList.remove('d-none');
            }
        });

    document.getElementById('btn-duplica-si')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.innerText = 'Duplicazione...';

        fetch("{{ route('riepilogo.costi.duplica') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        }).then(res => {
            if (!res.ok) throw new Error('Errore duplicazione');
            location.reload();
        }).catch(err => {
            alert(err.message);
            btn.disabled = false;
            btn.innerText = 'Sì';
        });
    });

    document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
        document.getElementById('noDataMessage')?.classList.add('d-none');
    });
});
</script>
@endpush
