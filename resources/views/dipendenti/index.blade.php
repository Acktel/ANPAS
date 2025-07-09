{{-- resources/views/dipendenti/index.blade.php --}}
@extends('layouts.app')
@php
  $user = Auth::user();
  $isImpersonating = session()->has('impersonate');
  $isAltro = Route::currentRouteName() === 'dipendenti.altro';
  $hasEditRoles = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor','AdminUser']) || $isImpersonating;
@endphp

@section('content')
<div class="container-fluid container-margin">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">{{ $titolo }}</h1>
    @if($hasEditRoles)
      <a href="{{ route('dipendenti.create') }}" class="btn btn-anpas-green">
        <i class="fas fa-plus me-1"></i> Nuovo Dipendente
      </a>
    @endif
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessun dipendente presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare i dipendenti dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white p-0">
      <table
        id="dipendentiTable"
        class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap w-100 mb-0"
      >
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Associazione</th>
            <th>Anno</th>
            <th>Nome</th>
            <th>Cognome</th>
            <th>Qualifica</th>
            <th>Livello Mansione</th>
            <th>Creato il</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
  const isAltro     = @json($isAltro);
  const canEdit     = @json($hasEditRoles);
  const dupRes      = await fetch("{{ route('dipendenti.checkDuplicazione') }}");
  const dupData     = await dupRes.json();

  const ajaxUrl = isAltro
    ? "{{ route('dipendenti.altro.data') }}"
    : "{{ route('dipendenti.data') }}";

  $('#dipendentiTable').DataTable({
    processing:    true,
    serverSide:    false,
    ajax: {
      url: ajaxUrl,
      dataSrc(json) {
        let data = Array.isArray(json.data) ? json.data : Object.values(json.data || {});
        if (data.length === 0 && dupData.mostraMessaggio) {
          document.getElementById('noDataMessage').classList.remove('d-none');
        }
        return data;
      }
    },
    columns: [
      { data: 'idDipendente' },
      { data: 'Associazione'  },
      { data: 'idAnno'        },
      { data: 'DipendenteNome' },
      { data: 'DipendenteCognome' },
      { data: 'Qualifica',       defaultContent: '' },
      { data: 'LivelloMansione', defaultContent: '' },
      {
        data: 'created_at',
        render: date => moment(date).format('DD/MM/YYYY HH:mm')
      },
      {
        data: 'idDipendente',
        orderable: false,
        searchable: false,
        render(id) {
          let html = `<a href="/dipendenti/${id}" class="btn btn-sm btn-anpas-impersonate me-1">
                        <i class="fas fa-info-circle"></i>
                      </a>`;
          if (canEdit) {
            html += `<a href="/dipendenti/${id}/edit" class="btn btn-sm btn-anpas-edit me-1">
                       <i class="fas fa-edit"></i>
                     </a>
                     <form action="/dipendenti/${id}" method="POST"
                           style="display:inline-block; margin-left:4px;">
                       <input type="hidden" name="_token" value="${csrfToken}">
                       <input type="hidden" name="_method" value="DELETE">
                       <button type="submit" class="btn btn-sm btn-anpas-delete"
                               onclick="return confirm('Sei sicuro di voler eliminare questo dipendente?')">
                         <i class="fas fa-trash-alt"></i>
                       </button>
                     </form>`;
          }
          return html;
        }
      }
    ],
    language:     { url: '/js/i18n/Italian.json' },
    stripeClasses:['table-striped-anpas','']
  });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione in corso…';
    try {
      const res = await fetch("{{ route('dipendenti.duplica') }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
      });
      if (!res.ok) throw await res.json();
      $('#dipendentiTable').DataTable().ajax.reload();
      document.getElementById('noDataMessage').classList.add('d-none');
    } catch (err) {
      alert(err.message || 'Errore durante la duplicazione.');
    } finally {
      btn.disabled = false;
      btn.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', () => {
    document.getElementById('noDataMessage').classList.add('d-none');
  });
});
</script>
@endpush
