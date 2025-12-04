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
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', async function() {
    const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;
    const canEdit = @json($hasEditRoles);
    const impersonatorName = @json($impersonatorName);
    const ajaxUrl = @json($ajaxUrl);

    const dupRes = await fetch(@json(route('dipendenti.checkDuplicazione')));
    const dupData = await dupRes.json();

    $('#dipendentiTable').DataTable({
      processing: true,
      serverSide: false,
      stateDuration: -1,
      stateSave: true,  
      ajax: {
        url: ajaxUrl,
        dataSrc(json) {
          const data = Array.isArray(json.data) ? json.data : Object.values(json.data || {});
          if (data.length === 0 && dupData.mostraMessaggio) {
            document.getElementById('noDataMessage').classList.remove('d-none');
          }
          return data;
        }
      },
      columns: [
        { data: 'idAnno' },
        { data: 'DipendenteNome' },
        { data: 'DipendenteCognome' },
        { data: 'Qualifica', defaultContent: '' },
        { data: 'LivelloMansione', defaultContent: '' },
        {
          data: null,
          render: function(data, type, row) {
            const nome = impersonatorName || row.updated_by_name || row.created_by_name || '—';
            const dataMod = row.updated_at || row.created_at;
            const when = dataMod ? moment(dataMod).format('DD/MM/YYYY HH:mm') : '—';
            return `<div>${nome}<br><small>${when}</small></div>`;
          }
        },
        {
          data: 'idDipendente',
          orderable: false,
          searchable: false,
          render(id) {
            let html = `
              <a href="/dipendenti/${id}" class="btn btn-sm btn-anpas-green me-1" title="Visualizza">
                <i class="fas fa-info-circle"></i>
              </a>`;
            if (canEdit) {
              html += `
                <a href="/dipendenti/${id}/edit" class="btn btn-sm btn-anpas-edit me-1" title="Modifica">
                  <i class="fas fa-edit"></i>
                </a>
                <form action="/dipendenti/${id}" method="POST" class="d-inline-block" onsubmit="return confirm('Sei sicuro di voler eliminare questo dipendente?')">
                  <input type="hidden" name="_token" value="${csrfToken}">
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit" class="btn btn-sm btn-anpas-delete" title="Elimina">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>`;
            }
            return html;
          }
        }
      ],
      language: {
        url: '/js/i18n/Italian.json',
        paginate: {
          first:    '<i class="fas fa-angle-double-left"></i>',
          previous: '<i class="fas fa-angle-left"></i>',
          next:     '<i class="fas fa-angle-right"></i>',
          last:     '<i class="fas fa-angle-double-right"></i>'
        }
      },
      rowCallback: function(row, data, index) {
        $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
      },
      stripeClasses: ['table-white', 'table-striped-anpas'],
    });

    // Duplica dall'anno precedente
    document.getElementById('btn-duplica-si')?.addEventListener('click', async function() {
      const btn = this;
      btn.disabled = true;
      btn.innerText = 'Duplicazione in corso…';
      try {
        const res = await fetch(@json(route('dipendenti.duplica')), {
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('assocInput');
  const dropdown = document.getElementById('assocDropdown');
  const hidden = document.getElementById('assocHidden');
  const form = document.getElementById('assocForm');
  const btn = document.getElementById('assocDropdownBtn');
  const items = dropdown.querySelectorAll('.assoc-item');

  btn.addEventListener('click', function () {
    dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
  });

  input.addEventListener('input', function () {
    const filter = input.value.toLowerCase();
    let visible = false;
    items.forEach(item => {
      if (item.textContent.toLowerCase().includes(filter)) { item.style.display = ''; visible = true; }
      else { item.style.display = 'none'; }
    });
    dropdown.style.display = visible ? 'block' : 'none';
  });

  items.forEach(item => {
    item.addEventListener('click', function () {
      input.value = this.textContent.trim();
      hidden.value = this.dataset.id;
      dropdown.style.display = 'none';
      form.submit();
    });
  });

  document.addEventListener('click', function (e) {
    if (!form.contains(e.target)) dropdown.style.display = 'none';
  });
});
</script>

<script>
(function () {
  const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
  if (!flash) return;
  setTimeout(() => {
    flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
    flash.style.opacity = '0';
    flash.style.maxHeight = flash.scrollHeight + 'px';
    flash.offsetHeight;
    flash.style.maxHeight = '0';
    flash.style.paddingTop = '0';
    flash.style.paddingBottom = '0';
    flash.style.marginTop = '0';
    flash.style.marginBottom = '0';
    setTimeout(() => { if (flash.parentNode) flash.parentNode.removeChild(flash); }, 600);
  }, 3500);
})();
</script>
@endpush
