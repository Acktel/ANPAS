{{-- resources/views/aziende_sanitarie/edit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Azienda Sanitaria</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('aziende-sanitarie.update', $azienda->idAziendaSanitaria) }}" method="POST" id="aziendaWizardForm">
    @csrf
    @method('PATCH')

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
            <button class="nav-link disabled" id="tab-conv" data-bs-toggle="tab" data-bs-target="#pane-conv" type="button" role="tab" disabled aria-disabled="true">
              Convenzioni
            </button>
          </li>
        </ul>

        <div class="tab-content">

          {{-- TAB 1: ANAGRAFICA --}}
          <div class="tab-pane fade show active" id="pane-anagrafica" role="tabpanel" aria-labelledby="tab-anagrafica">
            <div class="mb-3">
              <label for="Nome" class="form-label">Nome Azienda</label>
              <input type="text" name="Nome" id="Nome" class="form-control" required value="{{ old('Nome', $azienda->Nome) }}">
            </div>

            <div class="mb-3">
              <label for="Indirizzo" class="form-label">Indirizzo</label>
              <input type="text" name="Indirizzo" id="Indirizzo" class="form-control" value="{{ old('Indirizzo', $azienda->Indirizzo ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="mail" class="form-label">Email</label>
              <input type="email" name="mail" id="mail" class="form-control" value="{{ old('mail', $azienda->mail ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="4">{{ old('note', $azienda->note ?? '') }}</textarea>
            </div>

            <div class="d-flex justify-content-between">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Annulla
              </a>
              <button type="button" class="btn btn-primary" id="goToLotti">Avanti</button>
            </div>
          </div>

          {{-- TAB 2: LOTTI --}}
          <div class="tab-pane fade" id="pane-lotti" role="tabpanel" aria-labelledby="tab-lotti">
            <div class="card-header bg-anpas-primary">
              <label class="form-label">Inserire/modificare i lotti per l’azienda</label>
            </div>

            {{-- Add lotto inline --}}
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
                  <th style="width:70px">#</th>
                  <th>Nome Lotto</th>
                  <th>Descrizione</th>
                  <th style="width:90px">Azioni</th>
                </tr>
              </thead>
              <tbody>
                @php
                  // Se ritorno da validazione fallita, uso gli old(); altrimenti i lotti dal DB
                  $oldLotti = old('lotti', []);
                  $rows = count($oldLotti) ? collect($oldLotti) : $lotti;
                @endphp

                @foreach($rows as $i => $lotto)
                  @php
                    $id   = is_array($lotto) ? ($lotto['id'] ?? null)  : ($lotto->id ?? null);
                    $nome = is_array($lotto) ? ($lotto['nomeLotto'] ?? '') : ($lotto->nomeLotto ?? '');
                    $desc = is_array($lotto) ? ($lotto['descrizione'] ?? '') : ($lotto->descrizione ?? '');
                  @endphp
                  <tr data-row-index="{{ $i }}">
                    <input type="hidden" name="lotti[{{ $i }}][id]" value="{{ $id }}">
                    <input type="hidden" name="lotti[{{ $i }}][_delete]" value="0" class="lotto-delete">
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td><input type="text" name="lotti[{{ $i }}][nomeLotto]" class="form-control" value="{{ $nome }}"></td>
                    <td><input type="text" name="lotti[{{ $i }}][descrizione]" class="form-control" value="{{ $desc }}"></td>
                    <td class="text-center">
                      <button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>

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

          {{-- TAB 3: CONVENZIONI --}}
          <div class="tab-pane fade" id="pane-conv" role="tabpanel" aria-labelledby="tab-conv">
            <div class="alert alert-info">
              Per ogni <b>lotto</b> verranno create/aggiornate una o più <b>convenzioni</b> con nome
              <code>«&lt;Nome Azienda&gt; – &lt;Nome Lotto&gt;»</code>. Se selezioni più associazioni, verrà creata una convenzione per ognuna.
            </div>

            <table id="convTable" class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th style="width:70px">#</th>
                  <th>Convenzione (anteprima)</th>
                  <th>Associazioni da collegare</th>
                </tr>
              </thead>
              <tbody>
                {{-- generato via JS dai lotti visibili --}}
              </tbody>
            </table>

            <div class="d-flex justify-content-between mt-3">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Annulla
              </a>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" id="backToLotti">Indietro</button>
                <button type="submit" class="btn btn-anpas-green">
                  <i class="fas fa-check me-1"></i> Salva Modifiche
                </button>
              </div>
            </div>
          </div>

        </div> {{-- /tab-content --}}
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  // ====== ELEMENTI ======
  const tabAnag  = document.getElementById('tab-anagrafica');
  const tabLotti = document.getElementById('tab-lotti');
  const tabConv  = document.getElementById('tab-conv');

  const btnGoLotti   = document.getElementById('goToLotti');
  const btnBackAnag  = document.getElementById('backToAnagrafica');
  const btnGoConv    = document.getElementById('goToConvenzioni');
  const btnBackLotti = document.getElementById('backToLotti');

  const nomeAziendaInput = document.getElementById('Nome');

  // LOTTI
  const tableBody = document.querySelector('#lottiTable tbody');
  const addBtn    = document.getElementById('addLottoBtn');
  const inputNome = document.getElementById('newLottoNome');
  const inputDesc = document.getElementById('newLottoDesc');

  // CONVENZIONI
  const convTbody = document.querySelector('#convTable tbody');

  // Dati dal server
  const ASSOCS = @json($associazioni->map(fn($a) => ['id' => $a->idAssociazione, 'text' => $a->Associazione]));
  const CONV_ASSOC_BY_LOTTO = @json($convAssocByLotto ?? []);
  const OLD_CONV_ASSOC = @json(old('conv_assoc', []));

  // ====== UTILS ======
  function escapeHtml(s) {
    return (s ?? '').toString()
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function getVisibleLottiRows() {
    return Array.from(tableBody.querySelectorAll('tr[data-row-index]'))
      .filter(tr => tr.style.display !== 'none'); // gli "eliminati" vengono nascosti
  }

  // Calcolo nextIndex iniziale (anche con old())
  let nextIndex = (function () {
    let max = -1;
    getVisibleLottiRows().forEach(tr => {
      const i = parseInt(tr.getAttribute('data-row-index'), 10);
      if (!isNaN(i) && i > max) max = i;
    });
    return max + 1;
  })();

  function renumberRows() {
    const trs = getVisibleLottiRows();
    trs.forEach((tr, idx) => {
      tr.setAttribute('data-row-index', idx);
      tr.querySelector('td').textContent = idx + 1;

      const idH  = tr.querySelector('input[name^="lotti["][name$="[id]"]');
      const delH = tr.querySelector('input[name^="lotti["][name$="[_delete]"]');
      const nm   = tr.querySelector('input[name^="lotti["][name$="[nomeLotto]"]');
      const ds   = tr.querySelector('input[name^="lotti["][name$="[descrizione]"]');

      if (idH)  idH.name  = `lotti[${idx}][id]`;
      if (delH) delH.name = `lotti[${idx}][_delete]`;
      if (nm)   nm.name   = `lotti[${idx}][nomeLotto]`;
      if (ds)   ds.name   = `lotti[${idx}][descrizione]`;
    });
    nextIndex = trs.length;
  }

  function addRow(nomeVal = '', descVal = '') {
    const i = nextIndex++;
    const tr = document.createElement('tr');
    tr.setAttribute('data-row-index', i);
    tr.innerHTML = `
      <input type="hidden" name="lotti[${i}][id]" value="">
      <input type="hidden" name="lotti[${i}][_delete]" value="0" class="lotto-delete">
      <td class="text-muted">${i + 1}</td>
      <td><input type="text" name="lotti[${i}][nomeLotto]" class="form-control" value="${escapeHtml(nomeVal)}"></td>
      <td><input type="text" name="lotti[${i}][descrizione]" class="form-control" value="${escapeHtml(descVal)}"></td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">
          <i class="fas fa-trash-alt"></i>
        </button>
      </td>
    `;
    tableBody.appendChild(tr);
    updateConvTabState();
  }

  // Add lotto
  addBtn?.addEventListener('click', function () {
    const nome = (inputNome.value || '').trim();
    const desc = (inputDesc.value || '').trim();
    if (!nome) { inputNome.focus(); return; }
    addRow(nome, desc);
    inputNome.value = '';
    inputDesc.value = '';
  });

  // Delete lotto (soft per esistenti)
  tableBody.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-remove-row');
    if (!btn) return;

    const tr       = btn.closest('tr');
    const idInput  = tr.querySelector('input[name$="[id]"]');
    const delInput = tr.querySelector('input[name$="[_delete]"]');

    if (idInput && idInput.value) {
      // Lotto esistente: marca per delete e nascondi
      delInput.value = '1';
      tr.style.display = 'none';
    } else {
      // Lotto nuovo: rimuovi
      tr.remove();
    }
    renumberRows();
    updateConvTabState();
  });

  // ====== Wizard nav ======
  function activateTab(btnEl) {
    if (!btnEl) return;
    new bootstrap.Tab(btnEl).show();
  }

  document.getElementById('goToLotti')?.addEventListener('click', function () {
    if (!(nomeAziendaInput.value || '').trim()) { nomeAziendaInput.focus(); return; }
    activateTab(tabLotti);
  });

  document.getElementById('backToAnagrafica')?.addEventListener('click', function () {
    activateTab(tabAnag);
  });

  document.getElementById('goToConvenzioni')?.addEventListener('click', function () {
    if (getVisibleLottiRows().length === 0) {
      inputNome?.focus();
      return;
    }
    buildConvenzioniPreview();
    activateTab(tabConv);
  });

  document.getElementById('backToLotti')?.addEventListener('click', function () {
    activateTab(tabLotti);
  });

  // ====== Convenzioni: build + preselezione ======
  function buildConvenzioniPreview() {
    convTbody.innerHTML = '';
    const azienda = (nomeAziendaInput.value || '').trim();

    getVisibleLottiRows().forEach((tr, i) => {
      const idx    = parseInt(tr.getAttribute('data-row-index'), 10);
      const idL    = tr.querySelector(`input[name="lotti[${idx}][id]"]`)?.value || '';
      const nomeL  = tr.querySelector(`input[name="lotti[${idx}][nomeLotto]"]`)?.value || '';
      const convNm = `${azienda} – ${nomeL}`;

      // Scelte da old() (prioritarie) o da mappa lato server per lotto esistente
      let selectedIds = [];
      if (Object.prototype.hasOwnProperty.call(OLD_CONV_ASSOC, String(idx))) {
        selectedIds = (OLD_CONV_ASSOC[String(idx)] || []).map(x => String(x));
      } else if (idL && CONV_ASSOC_BY_LOTTO[String(idL)]) {
        selectedIds = (CONV_ASSOC_BY_LOTTO[String(idL)] || []).map(x => String(x));
      }

      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="text-muted">${i + 1}</td>
        <td><input type="text" class="form-control" value="${escapeHtml(convNm)}" readonly></td>
        <td>
          <select name="conv_assoc[${idx}][]" class="form-select" multiple size="6">
            ${ASSOCS.map(a => {
              const sel = selectedIds.includes(String(a.id)) ? 'selected' : '';
              return `<option value="${a.id}" ${sel}>${escapeHtml(a.text)}</option>`;
            }).join('')}
          </select>
          <div class="form-text">Seleziona una o più associazioni per questo lotto</div>
        </td>
      `;
      convTbody.appendChild(row);
    });
  }

  // ====== Abilita/Disabilita tab Convenzioni ======
  function updateConvTabState() {
    const hasLotti = getVisibleLottiRows().length > 0;
    if (tabConv) {
      tabConv.disabled = !hasLotti;
      tabConv.classList.toggle('disabled', !hasLotti);
      tabConv.setAttribute('aria-disabled', String(!hasLotti));
    }
  }
  // Quando l'utente APRE il tab "Convenzioni" cliccando la tab stessa,
  // se ci sono lotti costruiamo l’anteprima; se non ci sono, blocchiamo.
  tabConv?.addEventListener('show.bs.tab', function (e) {
    if (getVisibleLottiRows().length === 0) {
      e.preventDefault();
      inputNome?.focus();
      return;
    }
    buildConvenzioniPreview(); 
  });

  // Init
  updateConvTabState();
})();
</script>
@endpush
