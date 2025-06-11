@extends('layouts.app')

@section('content')
<div class="container-fluid">
  {{-- Titolo dinamico a seconda della rotta --}}
  <h1>{{ $titolo }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @php
    $user = Auth::user();
    $isImpersonating = session()->has('impersonate');
    // Determina se siamo sulla rotta "altro"
    $isAltro = Route::currentRouteName() === 'dipendenti.altro';
    // Ruoli per modifica
    $hasEditRoles = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor','AdminUser']) || $isImpersonating;
  @endphp

  @if($hasEditRoles)
    <a href="{{ route('dipendenti.create') }}" class="btn btn-primary mb-3">
      + Nuovo Dipendente
    </a>
  @endif

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessun dipendente presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare i dipendenti dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-success">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  <table id="dipendentiTable" class="table table-bordered table-striped table-hover dt-responsive nowrap w-100">
    <thead>
      <tr>
        <th>ID</th>
        <th>Associazione</th>
        <th>Anno</th>
        <th>Nome</th>
        <th>Cognome</th>
        <th>Qualifica</th>
        <th>Contratto</th>
        <th>Livello Mansione</th>
        <th>Creato il</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
  const isAltro = @json($isAltro);
  const canEdit = @json($hasEditRoles);
  const duplicazioneCheck = await fetch("{{ route('dipendenti.checkDuplicazione') }}");
  const duplicazioneData = await duplicazioneCheck.json();

  const ajaxUrl = isAltro
    ? "{{ route('dipendenti.altro.data') }}"
    : "{{ route('dipendenti.data') }}";

  const table = $('#dipendentiTable').DataTable({
    processing: true,
    serverSide: false,
    ajax: {
      url: ajaxUrl,
      dataSrc: function (json) {
        // Assicura array sequenziale da oggetto keyed
        let data = json.data;
        if (data && !Array.isArray(data)) {
          data = Object.values(data);
        }
        if (data.length === 0 && duplicazioneData.mostraMessaggio) {
          document.getElementById('noDataMessage').classList.remove('d-none');
        }
        return data;
      }
    },
    columns: [
      { data: 'idDipendente' },
      { data: 'Associazione' },
      { data: 'idAnno' },
      { data: 'DipendenteNome' },
      { data: 'DipendenteCognome' },
      { data: 'Qualifica' },
      { data: 'ContrattoApplicato' },
      { data: 'LivelloMansione' },
      {
        data: 'created_at',
        render: date => moment(date).format('DD/MM/YYYY HH:mm')
      },
      {
        data: 'idDipendente',
        orderable: false,
        searchable: false,
        render: id => {
          let html = `<a href="/dipendenti/${id}" class="btn btn-sm btn-info">Dettagli</a> `;
          if (canEdit) {
            html += `<a href="/dipendenti/${id}/edit" class="btn btn-sm btn-warning">Modifica</a> `;
            html += `
              <form action="/dipendenti/${id}" method="POST" style="display:inline-block; margin-left:4px;">
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo dipendente?')">Elimina</button>
              </form>
            `;
          }
          return html;
        }
      }
    ],
    paging: true,
    searching: true,
    ordering: true
  });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione in corso...';

    try {
      const res = await fetch("{{ route('dipendenti.duplica') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
      });
      if (res.ok) {
        document.getElementById('noDataMessage').classList.add('d-none');
        table.ajax.reload();
      } else {
        const err = await res.json();
        alert(err.message || 'Errore durante la duplicazione.');
      }
    } catch (e) {
      alert('Errore di rete o duplicazione fallita.');
      console.error(e);
    } finally {
      btn.disabled = false;
      btn.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
    document.getElementById('noDataMessage').classList.add('d-none');
  });
});
</script>
@endpush
