@extends('layouts.app')

@php
$user = Auth::user();
@endphp

@section('content')
<div class="container-fluid">
  <h1>Elenco Automezzi - Anno {{ $anno }}</h1>


  @if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('automezzi.create') }}" class="btn btn-primary mb-3">
    + Nuovo Automezzo
  </a>

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessun automezzo presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare gli automezzi dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-success">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>


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
    <tbody>
      @foreach($automezzi as $a)
      <tr>
        <td>{{ $a->idAutomezzo }}</td>
        <td>{{ $a->Associazione }}</td>
        <td>{{ $a->anno ?? $a->idAnno }}</td>
        <td>{{ $a->Automezzo }}</td>
        <td>{{ $a->Targa }}</td>
        <td>{{ $a->CodiceIdentificativo }}</td>
        <td>{{ $a->AnnoPrimaImmatricolazione }}</td>
        <td>{{ $a->Modello }}</td>
        <td>{{ $a->TipoVeicolo }}</td>
        <td>{{ $a->KmRiferimento }}</td>
        <td>{{ $a->KmTotali }}</td>
        <td>{{ $a->TipoCarburante }}</td>
        <td>{{ optional(\Carbon\Carbon::parse($a->DataUltimaAutorizzazioneSanitaria))->format('d/m/Y') }}</td>
        <td>{{ optional(\Carbon\Carbon::parse($a->DataUltimoCollaudo))->format('d/m/Y') }}</td>
        <td>
          <a href="{{ route('automezzi.show', $a->idAutomezzo) }}"
            class="btn btn-sm btn-primary me-1">
            Dettagli
          </a>

          <a href="{{ route('automezzi.edit', $a->idAutomezzo) }}"
            class="btn btn-sm btn-warning me-1">
            Modifica
          </a>

          <form action="{{ route('automezzi.destroy', $a->idAutomezzo) }}"
            method="POST"
            style="display:inline-block"
            onsubmit="return confirm('Sei sicuro di voler eliminare questo automezzo?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">
              Elimina
            </button>
          </form>
        </td>


      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
      console.error('CSRF token non trovato nel meta tag.');
      return;
    }

    // ✅ Check duplicazione all'avvio
    fetch("{{ route('automezzi.checkDuplicazione') }}")
      .then(res => res.json())
      .then(data => {
        if (data.mostraMessaggio) {
          document.getElementById('noDataMessage')?.classList.remove('d-none');
        }
      })
      .catch(err => console.error('Errore durante il check duplicazione:', err));

    // ✅ Listener bottone "Sì"
    document.getElementById('btn-duplica-si')?.addEventListener('click', function() {
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
            const errJson = await res.json();
            throw new Error(errJson.message || 'Errore durante la duplicazione.');
          }
          location.reload(); // 🔄 reload dopo successo
        })
        .catch(err => {
          alert(err.message || 'Errore di rete.');
          console.error('Duplicazione fallita:', err);
          btn.disabled = false;
          btn.innerText = 'Sì';
        });
    });

    // ✅ Listener bottone "No"
    document.getElementById('btn-duplica-no')?.addEventListener('click', function() {
      document.getElementById('noDataMessage')?.classList.add('d-none');
    });
  });
</script>
@endpush