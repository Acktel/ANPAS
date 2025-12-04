@extends('layouts.app')

@php
use App\Http\Controllers\ConfigurazionePersonaleController;

$user = Auth::user();
$isImpersonating = session()->has('impersonate');
$hasEditRoles = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor','AdminUser']) || $isImpersonating;
$impersonatorName = null;
if ($isImpersonating) {
  $impersonatorId = session('impersonate');
  $impersonator = \App\Models\User::find($impersonatorId);
  $impersonatorName = $impersonator?->username ?? '—';
}

$configPersone = ConfigurazionePersonaleController::getConfigurazionePersonale();
$config = true;
foreach ($configPersone as $key => $value) {
    if ($key === 'qualifiche' && sizeof($value) <= 0) { $config = false; $testo = 'Mancano le configurazioni riguardanti le qualifiche del personale.'; $link = route('configurazioni.personale'); break; }
    if ($key === 'contratti'  && sizeof($value) <= 0) { $config = false; $testo = 'Mancano le configurazioni riguardanti i contratti del personale.';  $link = route('configurazioni.personale'); break; }
    if ($key === 'livelli'    && sizeof($value) <= 0) { $config = false; $testo = 'Mancano le configurazioni riguardanti i livelli di mansione del personale.'; $link = route('configurazioni.personale'); break; }
}

// Determino l'URL per l'AJAX in base alla pagina corrente
if (Route::is('dipendenti.byQualifica') && isset($qualificaId)) {
    $ajaxUrl = route('dipendenti.byQualifica.data', $qualificaId);
} elseif (Route::is('dipendenti.autisti')) {
    $ajaxUrl = route('dipendenti.autisti.data');
} elseif (Route::is('dipendenti.amministrativi')) {
    $ajaxUrl = route('dipendenti.amministrativi.data');
} else {
    $ajaxUrl = route('dipendenti.data');
}
@endphp

@section('content')
<div class="container-fluid container-margin">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="container-title">{{ $titolo }}</h1>

    @if($hasEditRoles)
      @if ($config)
        <a href="{{ route('dipendenti.create', ['idAssociazione' => session('associazione_selezionata')]) }}" class="btn btn-anpas-green">
          <i class="fas fa-plus me-1"></i> Nuovo Dipendente
        </a>
      @else
        <div class="text-end container-warning-create">
          <p class="mb-2 fw-bold">
            Non puoi aggiungere dipendenti se prima<br>
            non completi le configurazioni:
          </p>
          <a href="{{ route('configurazioni.personale') }}" class="btn btn-warning">
            <i class="fas fa-cogs me-1"></i> Vai alle Configurazioni
          </a>
        </div>
      @endif
    @endif
  </div>

  @if($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) || $isImpersonating)
    <form method="POST" action="{{ route('sessione.setAssociazione') }}" id="assocForm" class="mb-3 d-flex align-items-center gap-2">
      @csrf
      <label for="assocInput" class="mb-0 fw-bold">Associazione:</label>

      <div class="input-group" style="width: 350px; position: relative;">
        <input type="text"
               id="assocInput"
               name="assocLabel"
               class="form-control"
               placeholder="Seleziona associazione"
               value="{{ optional(($associazioni ?? collect())->firstWhere('idAssociazione', session('associazione_selezionata')))->Associazione ?? '' }}"
               autocomplete="off">

        <button type="button" class="btn btn-outline-secondary" id="assocDropdownBtn" title="Mostra elenco">
          <i class="fas fa-chevron-down"></i>
        </button>

        <input type="hidden" id="assocHidden" name="idAssociazione" value="{{ session('associazione_selezionata') ?? '' }}">

        <ul id="assocDropdown" class="list-group"
            style="position:absolute; top:100%; left:0; width:100%; z-index:2000; display:none; max-height:240px; overflow:auto; background-color:#fff;">
          @foreach(($associazioni ?? collect()) as $assoc)
            <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">
              {{ $assoc->Associazione }}
            </li>
          @endforeach
        </ul>
      </div>
    </form>
  @endif

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
      <table id="dipendentiTable" class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap w-100 mb-0 text-center align-middle">
        <thead class="thead-anpas">
          <tr>
            <th>Anno</th>
            <th>Nome</th>
            <th>Cognome</th>
            <th>Qualifica</th>
            <th>Livello Mansione</th>
            <th>Ultima modifica</th>
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
<script>
  // File JS da includere con @vite o @push('scripts')
document.addEventListener('DOMContentLoaded', async function () {
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
  const useAjax = @json($useAjax);
  const isElevato = @json($isElevato);
  const hasConvenzioni = @json($convenzioni->isNotEmpty());
  const idConv = document.getElementById('convSelect')?.value ?? 0;

  if (!useAjax || !hasConvenzioni) {
    console.log('[DT] Ajax disabilitato o nessuna convenzione: tabella statica.');
    return;
  }

  const tableId = '#aziendeSanitarieTable';
  const noDataMessage = document.getElementById('noDataMessage');

  const table = $(tableId).DataTable({
    processing: true,
    serverSide: false,
    ajax: {
      url: @json(route('aziende-sanitarie.data')),
      data: d => { d.idConvenzione = idConv; },
      dataSrc: json => {
        if (json.data?.length === 0 && noDataMessage) noDataMessage.classList.remove('d-none');
        return json.data || [];
      }
    },
    stateSave: true,
    stateDuration: -1,
    order: [[0, 'asc']],
    language: { url: '/js/i18n/Italian.json' },
    stripeClasses: ['table-white', 'table-striped-anpas'],
    search: true,
    columns: [
      { data: 'idAziendaSanitaria' },
      { data: 'Nome' },
      {
        data: null,
        render: row => {
          const via = row.indirizzo_via ?? '';
          const civico = row.indirizzo_civico ?? '';
          const fallback = row.Indirizzo ?? '—';
          return `${via} ${civico}`.trim() || fallback;
        }
      },
      { data: 'provincia' },
      { data: 'citta' },
      { data: 'cap' },
      { data: 'mail' },
      {
        data: 'Lotti',
        render: d => Array.isArray(d) && d.length ? d.join(', ') : '<span class="text-muted">—</span>'
      },
      {
        data: 'idAziendaSanitaria',
        orderable: false,
        searchable: false,
        className: 'text-center',
        render: id => `
          <a href="/aziende-sanitarie/${id}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon">
            <i class="fas fa-edit"></i>
          </a>
          <form action="/aziende-sanitarie/${id}" method="POST" class="d-inline" onsubmit="return confirm('Eliminare questa azienda sanitaria?')">
            <input type="hidden" name="_token" value="${csrfToken}">
            <input type="hidden" name="_method" value="DELETE">
            <button class="btn btn-sm btn-anpas-delete btn-icon">
              <i class="fas fa-trash-alt"></i>
            </button>
          </form>`
      }
    ]
  });

  // Cambio convenzione
  if (!isElevato) {
    document.getElementById('convSelect')?.addEventListener('change', function () {
      fetch(@json(route('aziende-sanitarie.sessione.setConvenzione')), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ idConvenzione: this.value })
      }).finally(() => table.ajax.reload());
    });
  }

  // Duplica dati se visibile
  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione in corso…';
    try {
      const res = await fetch(@json(route('aziende-sanitarie.duplica')), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
      });
      if (!res.ok) throw await res.json();
      table.ajax.reload();
      noDataMessage.classList.add('d-none');
    } catch (err) {
      alert(err.message || 'Errore durante la duplicazione.');
    } finally {
      btn.disabled = false;
      btn.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', () => {
    noDataMessage.classList.add('d-none');
  });
});
</script>
@endpush
