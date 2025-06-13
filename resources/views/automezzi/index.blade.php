@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="mb-4">Elenco Automezzi - Anno {{ $anno }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('automezzi.create') }}" class="btn btn-primary mb-3">
    + Nuovo Automezzo
  </a>

  {{-- Messaggio di duplicazione --}}
  <div id="noDataMessage" class="alert alert-info d-none">
    Nessun automezzo presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare gli automezzi dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-success">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  {{-- DataTable dinamica --}}
  <table id="automezziTable" class="table table-bordered table-striped table-hover w-100">
    <thead class="table-light">
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
@endsection

@push('scripts')
{{-- DataTables CDN --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Inizializza DataTable dinamico
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
  }
    });

    // Check duplicazione iniziale
    fetch("{{ route('automezzi.checkDuplicazione') }}")
      .then(res => res.json())
      .then(data => {
        if (data.mostraMessaggio) {
          document.getElementById('noDataMessage')?.classList.remove('d-none');
        }
      })
      .catch(err => console.error('Errore duplicazione:', err));

    // Listener duplicazione SI
    document.getElementById('btn-duplica-si')?.addEventListener('click', function () {
      const btn = this;
      btn.disabled = true;
      btn.innerText = 'Duplicazione...';

      fetch("{{ route('automezzi.duplica') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        }
      })
        .then(async res => {
          if (!res.ok) {
            const err = await res.json();
            throw new Error(err.message || 'Errore duplicazione');
          }
          location.reload(); // ricarica se successo
        })
        .catch(err => {
          alert(err.message || 'Errore duplicazione');
          btn.disabled = false;
          btn.innerText = 'Sì';
        });
    });

    // Listener duplicazione NO
    document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
      document.getElementById('noDataMessage')?.classList.add('d-none');
    });
  });
</script>
@endpush
