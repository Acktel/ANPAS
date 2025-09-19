{{-- resources/views/aziende_sanitarie/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Nuova Azienda Sanitaria</h1>

  @if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('aziende-sanitarie.store') }}" method="POST" id="aziendaWizardForm">
    @csrf

    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">

        {{-- NAV TABS (wizard) --}}
        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-anagrafica" data-bs-toggle="tab" data-bs-target="#pane-anagrafica" type="button" role="tab">Anagrafica</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-lotti" data-bs-toggle="tab" data-bs-target="#pane-lotti" type="button" role="tab">Lotti</button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link disabled"
              id="tab-conv"
              data-bs-toggle="tab"
              data-bs-target="#pane-conv"
              type="button"
              role="tab"
              disabled
              aria-disabled="true">
              Convenzioni
            </button>
          </li>
        </ul>

        <div class="tab-content">

          {{-- PANE 1: ANAGRAFICA --}}
          <div class="tab-pane fade show active" id="pane-anagrafica" role="tabpanel" aria-labelledby="tab-anagrafica">
            {{-- Nome --}}
            <div class="mb-3">
              <label for="Nome" class="form-label">Nome Azienda</label>
              <input type="text" name="Nome" id="Nome" class="form-control" required value="{{ old('Nome') }}">
            </div>

            {{-- Indirizzo --}}
            <div class="mb-3">
              <label for="Indirizzo" class="form-label">Indirizzo</label>
              <input type="text" name="Indirizzo" id="Indirizzo" class="form-control" value="{{ old('Indirizzo') }}">
            </div>

            {{-- Email --}}
            <div class="mb-3">
              <label for="mail" class="form-label">Email</label>
              <input type="email" name="mail" id="mail" class="form-control" value="{{ old('mail') }}">
            </div>

            {{-- Note --}}
            <div class="mb-3">
              <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="4">{{ old('note') }}</textarea>
            </div>

            {{-- Bottoni: Annulla (sx) / Avanti (dx) --}}
            <div class="d-flex justify-content-between">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Annulla
              </a>
              <button type="button" class="btn btn-primary" id="goToLotti">Avanti</button>
            </div>
          </div>

          {{-- PANE 2: LOTTI --}}
          <div class="tab-pane fade" id="pane-lotti" role="tabpanel" aria-labelledby="tab-lotti">

            {{-- Header coerente --}}
            <div class="card-header bg-anpas-primary">
              <label class="form-label">Inserire qui di seguito i lotti per l’azienda</label>
            </div>

            {{-- Form aggiunta lotto (inline, no wrap) --}}
            <div class="card-body bg-anpas-white p-0">
              <div class="d-flex p-3 border-bottom align-items-start flex-nowrap gap-2 overflow-auto">
                <input type="text" id="newLottoNome" class="form-control me-2 flex-shrink-0" style="width:280px" placeholder="Nome lotto">
                <input type="text" id="newLottoDesc" class="form-control me-2 flex-shrink-0" style="width:420px" placeholder="Descrizione (opzionale)">
                <button type="button" id="addLottoBtn" class="btn btn-anpas-green flex-shrink-0">
                  <i class="fas fa-plus me-1"></i> Aggiungi
                </button>
              </div>
            </div>

            {{-- Tabella Lotti --}}
            <table id="lottiTable" class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th style="width: 70px">#</th>
                  <th>Nome Lotto</th>
                  <th>Descrizione</th>
                  <th style="width: 90px">Azioni</th>
                </tr>
              </thead>
              <tbody>
                @php $oldLotti = array_values(old('lotti', [])); @endphp
                @foreach($oldLotti as $i => $lotto)
                <tr data-row-index="{{ $i }}">
                  <td class="text-muted">{{ $i + 1 }}</td>
                  <td><input type="text" name="lotti[{{ $i }}][nomeLotto]" class="form-control" value="{{ $lotto['nomeLotto'] ?? '' }}"></td>
                  <td><input type="text" name="lotti[{{ $i }}][descrizione]" class="form-control" value="{{ $lotto['descrizione'] ?? '' }}"></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>

            {{-- Bottoni: Annulla (sx) / Indietro+Avanti (dx) --}}
            <div class="d-flex justify-content-between mt-3">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Annulla
              </a>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" id="backToAnagrafica">Indietro</button>
                <button type="button" class="btn btn-primary" id="goToConvenzioni">Avanti</button>
              </div>
            </div>
          </div>

          {{-- PANE 3: CONVENZIONI --}}
          <div class="tab-pane fade" id="pane-conv" role="tabpanel" aria-labelledby="tab-conv">

            <div class="alert alert-info">
              Per ogni <b>lotto</b> verranno create una o più <b>convenzioni</b> con nome
              <code>«&lt;Nome Azienda&gt; – &lt;Nome Lotto&gt;»</code>. Se selezioni più associazioni, verrà creata una convenzione per ognuna.
            </div>

            <table id="convTable" class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th style="width: 70px">#</th>
                  <th>Convenzione (anteprima)</th>
                  <th>Associazioni da collegare</th>
                </tr>
              </thead>
              <tbody>
                {{-- righe generate via JS a partire dai lotti --}}
              </tbody>
            </table>

            {{-- Bottoni: Annulla (sx) / Indietro+Salva (dx) --}}
            <div class="d-flex justify-content-between mt-3">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Annulla
              </a>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" id="backToLotti">Indietro</button>
                <button type="submit" class="btn btn-anpas-green">
                  <i class="fas fa-check me-1"></i> Salva Azienda + Lotti + Convenzioni
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
</form>
</div>
@endsection

@push('scripts')
<script>
(function() {
  // ======= Setup =======
  const tabAnag  = document.getElementById('tab-anagrafica');
  const tabLotti = document.getElementById('tab-lotti');
  const tabConv  = document.getElementById('tab-conv');

  const btnGoLotti   = document.getElementById('goToLotti');
  const btnBackAnag  = document.getElementById('backToAnagrafica');
  const btnGoConv    = document.getElementById('goToConvenzioni');
  const btnBackLotti = document.getElementById('backToLotti');

  const tableBody = document.querySelector('#lottiTable tbody');
  const addBtn    = document.getElementById('addLottoBtn');
  const inputNome = document.getElementById('newLottoNome');
  const inputDesc = document.getElementById('newLottoDesc');

  const convTbody        = document.querySelector('#convTable tbody');
  const nomeAziendaInput = document.getElementById('Nome');

  const ASSOCS = @json(
    $associazioni->map(function($a) {
      return ['id' => $a->idAssociazione, 'text' => $a->Associazione];
    })
  );

  // ======= LOTTI: add/remove/renumber =======
  let nextIndex = (function() {
    const rows = tableBody.querySelectorAll('tr[data-row-index]');
    let max = -1;
    rows.forEach(tr => {
      const i = parseInt(tr.getAttribute('data-row-index'), 10);
      if (!isNaN(i) && i > max) max = i;
    });
    return max + 1;
  })();

  function escapeHtml(s) {
    return (s ?? '').toString()
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function renumberRows() {
    const trs = tableBody.querySelectorAll('tr[data-row-index]');
    trs.forEach((tr, idx) => {
      tr.setAttribute('data-row-index', idx);
      tr.querySelector('td').textContent = idx + 1;
      const nome = tr.querySelector('input[name^="lotti["][name$="[nomeLotto]"]');
      const desc = tr.querySelector('input[name^="lotti["][name$="[descrizione]"]');
      if (nome) nome.name = `lotti[${idx}][nomeLotto]`;
      if (desc) desc.name = `lotti[${idx}][descrizione]`;
    });
    nextIndex = trs.length;
  }

  function addRow(nomeVal = '', descVal = '') {
    const i = nextIndex++;
    const tr = document.createElement('tr');
    tr.setAttribute('data-row-index', i);
    tr.innerHTML = `
      <td class="text-muted">${i + 1}</td>
      <td>
        <input type="text" name="lotti[${i}][nomeLotto]" class="form-control" value="${escapeHtml(nomeVal)}">
      </td>
      <td>
        <input type="text" name="lotti[${i}][descrizione]" class="form-control" value="${escapeHtml(descVal)}">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">
          <i class="fas fa-trash-alt"></i>
        </button>
      </td>
    `;
    tableBody.appendChild(tr);
    updateConvTabState();
  }

  addBtn?.addEventListener('click', function() {
    const nome = (inputNome.value || '').trim();
    const desc = (inputDesc.value || '').trim();
    if (!nome) { inputNome.focus(); return; }
    addRow(nome, desc);
    inputNome.value = '';
    inputDesc.value = '';
  });

  tableBody.addEventListener('click', function(e) {
    const btn = e.target.closest('.js-remove-row');
    if (!btn) return;
    btn.closest('tr').remove();
    renumberRows();
    updateConvTabState();
  });

  // ======= Wizard nav =======
  function activateTab(btnEl) {
    if (!btnEl) return;
    new bootstrap.Tab(btnEl).show();
  }

  btnGoLotti?.addEventListener('click', function() {
    if (!(nomeAziendaInput.value || '').trim()) { nomeAziendaInput.focus(); return; }
    activateTab(tabLotti);
  });

  btnBackAnag?.addEventListener('click', function() { activateTab(tabAnag); });

  btnGoConv?.addEventListener('click', function() {
    if (getLottiCount() === 0) { inputNome.focus(); return; }
    buildConvenzioniPreview();
    activateTab(tabConv);
  });

  btnBackLotti?.addEventListener('click', function() { activateTab(tabLotti); });

  // ======= Convenzioni (anteprima + select associazioni) =======
  function buildConvenzioniPreview() {
    convTbody.innerHTML = '';
    const azienda = (nomeAziendaInput.value || '').trim();
    const lottoRows = tableBody.querySelectorAll('tr[data-row-index]');

    lottoRows.forEach((tr, i) => {
      const idx = parseInt(tr.getAttribute('data-row-index'), 10);
      const nomeL = tr.querySelector(`input[name="lotti[${idx}][nomeLotto]"]`)?.value || '';
      const convName = `${azienda} – ${nomeL}`;
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="text-muted">${i + 1}</td>
        <td><input type="text" class="form-control" value="${escapeHtml(convName)}" readonly></td>
        <td>
          <select name="conv_assoc[${idx}][]" class="form-select" multiple size="6">
            ${ASSOCS.map(a => `<option value="${a.id}">${escapeHtml(a.text)}</option>`).join('')}
          </select>
          <div class="form-text">Seleziona una o più associazioni per questo lotto</div>
        </td>
      `;
      convTbody.appendChild(row);
    });
  }

  // ======= Blocco apertura tab "Convenzioni" senza lotti =======
  function getLottiCount() {
    return tableBody.querySelectorAll('tr[data-row-index]').length;
  }

  function updateConvTabState() {
    const hasLotti = getLottiCount() > 0;
    if (tabConv) {
      tabConv.disabled = !hasLotti;
      tabConv.classList.toggle('disabled', !hasLotti);
      tabConv.setAttribute('aria-disabled', String(!hasLotti));
    }
  }

  tabConv?.addEventListener('show.bs.tab', function (e) {
    if (getLottiCount() === 0) {
      e.preventDefault();
      inputNome?.focus();
    }
  });

  updateConvTabState();
})();
</script>
@endpush
