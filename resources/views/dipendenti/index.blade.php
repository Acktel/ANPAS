@extends('layouts.app')

@section('content')
<div class="container-fluid">
  {{-- Titolo dinamico a seconda della rotta --}}
  <h1>{{ $titolo }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Se SuperAdmin/Admin/Supervisor, mostro link “+Nuovo Dipendente” --}}
  @php
    $user = Auth::user();
    $isImpersonating = session()->has('impersonate');
  @endphp

  @if(! $isImpersonating && $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <a href="{{ route('dipendenti.create') }}" class="btn btn-primary mb-3">
      + Nuovo Dipendente
    </a>
  @endif

  <table id="dipendentiTable" class="table table-striped">
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
  {{-- Import di Moment.js (se non lo carichi globalmente dal layout) --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const csrfToken     = document.head.querySelector('meta[name="csrf-token"]').content;
    const isImpersonating = @json(session()->has('impersonate'));
    const canEdit       = @json(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']));

    $('#dipendentiTable').DataTable({
      processing: true,
      serverSide: false,
      ajax: {
        url: "{{ route('dipendenti.data') }}",
        dataSrc: 'data'
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
          render: function(date) {
            return moment(date).format('DD/MM/YYYY HH:mm');
          }
        },
        {
          data: 'idDipendente',
          orderable: false,
          searchable: false,
          render: function(id) {
            let html = `<a href="/dipendenti/${id}" class="btn btn-sm btn-info">Dettagli</a> `;
            if (!isImpersonating && canEdit) {
              html += `<a href="/dipendenti/${id}/edit" class="btn btn-sm btn-warning">Modifica</a> `;
              html += `
                <form action="/dipendenti/${id}" method="POST" style="display:inline-block;">
                  <input type="hidden" name="_token" value="${csrfToken}">
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit" class="btn btn-sm btn-danger"
                          onclick="return confirm('Sei sicuro di voler eliminare questo dipendente?')">
                    Elimina
                  </button>
                </form>
              `;
            }
            return html;
          }
        }
      ],
      // se hai il file di traduzione locale, decommenta e punta al tuo file:
      // language: { url: '/js/i18n/Italian.json' },
      paging:    true,
      searching: true,
      ordering:  true
    });
  });
  </script>
@endpush
