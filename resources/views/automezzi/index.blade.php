@extends('layouts.app')

@section('content')
<div class="container-fluid container-margin">

  {{-- Titolo + Bottone --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">
      Elenco Automezzi – Anno {{ $anno }}
    </h1>
    <a 
      href="{{ route('automezzi.create') }}" 
      class="btn btn-anpas-green"
    >
      <i class="fas fa-plus me-1"></i> Nuovo Automezzo
    </a>
  </div>

  {{-- Success message --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Messaggio di “no data” --}}
  <div id="noDataMessage" class="alert alert-info d-none">
    Nessun automezzo presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare gli automezzi dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  {{-- Tabella in card --}}
  <div class="card-anpas mb-4 automezzi-card">
    <div class="card-body bg-anpas-white p-0">
      <table 
        id="automezziTable" 
        class="common-css-dataTable table table-hover table-striped-anpas table-bordered mb-0 w-100"
      >
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Associazione</th>
            <th>Anno</th>
            <th>Veicolo</th>
            <th>Targa</th>
            <th>Codice ID</th>
            <th>Immatricolazione</th>
            <th>Modello</th>
            <th>Tipo Veicolo</th>
            <th>Km Rif.</th>
            <th>Km Totali</th>
            <th>Carburante</th>
            <th>Ult. Aut. Sanitaria</th>
            <th>Ult. Collaudo</th>
            <th>Azioni</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
  
</div>
@endsection

@push('styles')
<link 
  rel="stylesheet" 
  href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" 
/>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script 
  src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"
></script>
<script 
  src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"
></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  // Inizializza DataTable
  $('#automezziTable').DataTable({
    processing: true,
    serverSide: false,
    ajax: '{{ route("automezzi.datatable") }}',
    columns: [
      { data: 'idAutomezzo' },
      { data: 'Associazione' },
      { data: 'idAnno' },
      { data: 'Automezzo' },
      { data: 'Targa' },
      { data: 'CodiceIdentificativo' },
      { data: 'AnnoPrimaImmatricolazione' },
      { data: 'Modello' },
      { data: 'TipoVeicolo' },
      { data: 'KmRiferimento' },
      { data: 'KmTotali' },
      { data: 'TipoCarburante' },
      { data: 'DataUltimaAutorizzazioneSanitaria' },
      { data: 'DataUltimoCollaudo' },
      { data: 'Azioni', orderable: false, searchable: false }
    ],
    language: {
      url: '/js/i18n/Italian.json'
    },
    stripeClasses: ['table-striped-anpas','']  // usa la tua classe per l’alternanza
  });

  // Mostra/Nascondi messaggio “no data”
  fetch("{{ route('automezzi.checkDuplicazione') }}")
    .then(res => res.json())
    .then(data => {
      if (data.mostraMessaggio) {
        document.getElementById('noDataMessage').classList.remove('d-none');
      }
    })
    .catch(console.error);

  // Duplica – “Sì”
  document.getElementById('btn-duplica-si').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione…';

    fetch("{{ route('automezzi.duplica') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      }
    })
    .then(async res => {
      if (!res.ok) throw new Error((await res.json()).message);
      location.reload();
    })
    .catch(err => {
      alert(err.message || 'Errore duplicazione');
      btn.disabled = false;
      btn.innerText = 'Sì';
    });
  });

  // Duplica – “No”
  document.getElementById('btn-duplica-no').addEventListener('click', () => {
    document.getElementById('noDataMessage').classList.add('d-none');
  });

});
</script>
@endpush
