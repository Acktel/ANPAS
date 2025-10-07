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

            {{-- Indirizzo (Provincia + Città SOLO qui) --}}
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="provincia" class="form-label">Provincia</label>
                <select class="form-control" id="provincia" name="provincia">
                  <option value="" disabled {{ old('provincia') ? '' : 'selected' }}>-- Seleziona provincia --</option>
                  @php
                    $allowedProvinces = ['VC', 'AL', 'AT', 'BI', 'CN', 'NO', 'TO', 'VB'];
                    $provinceUniche = collect($cities)
                      ->pluck('sigla_provincia')
                      ->unique()
                      ->filter(fn($sigla) => in_array($sigla, $allowedProvinces))
                      ->sort()
                      ->values();
                  @endphp
                  @foreach($provinceUniche as $sigla)
                    <option value="{{ $sigla }}" {{ old('provincia') == $sigla ? 'selected' : '' }}>
                      {{ $sigla }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-6 mb-3">
                <label for="citta_combo" class="form-label">Città</label>
                <div class="position-relative">
                  <input type="text" id="citta_combo" name="citta" class="form-control"
                         placeholder="Inizia a scrivere..." autocomplete="off"
                         value="{{ old('citta') }}">
                  <ul id="citta_list" class="list-group position-absolute w-100"
                      style="z-index:1000; display:none; max-height:200px; overflow-y:auto;">
                    @foreach($cities as $city)
                      <li class="list-group-item list-group-item-action"
                          data-provincia="{{ $city->sigla_provincia }}"
                          {{ old('citta') == trim($city->denominazione_ita) ? 'data-selected="true"' : '' }}>
                        {{ ucfirst(trim($city->denominazione_ita)) }}
                      </li>
                    @endforeach
                  </ul>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="Indirizzo" class="form-label">Indirizzo</label>
              <input type="text" name="Indirizzo" id="Indirizzo" class="form-control" value="{{ old('Indirizzo') }}">
            </div>

            <div class="mb-3">
              <label for="mail" class="form-label">Email</label>
              <input type="email" name="mail" id="mail" class="form-control" value="{{ old('mail') }}">
            </div>

            <div class="mb-3">
              <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="4">{{ old('note') }}</textarea>
            </div>

            <div class="d-flex justify-content-center">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">
                <i class="fas fa-times me-1"></i> Annulla
              </a>
              <button type="button" class="btn btn-anpas-green" id="goToLotti"><i class="fas fa-check me-1"></i> Avanti</button>
            </div>
          </div>

          {{-- PANE 2: LOTTI --}}
          <div class="tab-pane fade" id="pane-lotti" role="tabpanel" aria-labelledby="tab-lotti">

            {{-- Switch Lotti presenti Sì/No --}}
            @php $oldLottiPresenti = old('lotti_presenti','1'); @endphp
            <div class="p-3 border-bottom">
              <label class="form-label me-3">Lotti presenti?</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="lotti_presenti" id="lottiYes" value="1" {{ $oldLottiPresenti == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="lottiYes">Sì</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="lotti_presenti" id="lottiNo" value="0" {{ $oldLottiPresenti === '0' ? 'checked' : '' }}>
                <label class="form-check-label" for="lottiNo">No</label>
              </div>
              <small class="text-muted d-block mt-1">
                Se imposti “No”, non inserirai lotti: useremo internamente <b>“LOTTI NON PRESENTI”</b> e potrai comunque creare convenzioni (il nome sarà solo quello dell’azienda).
              </small>
            </div>

            {{-- Editor lotti: visibile solo se lotti_presenti = Sì --}}
            <div id="lottiEditor">
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
            </div>

            {{-- Bottoni: Annulla / Indietro + Avanti --}}
            <div class="d-flex justify-content-center mt-5">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">
                <i class="fas fa-times me-1"></i> Annulla
              </a>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" id="backToAnagrafica"><i class="fas fa-arrow-left"></i>Indietro</button>
                <button type="button" class="btn btn-anpas-green" id="goToConvenzioni"><i class="fas fa-check me-1"></i>Avanti</button>
              </div>
            </div>
          </div>

          {{-- PANE 3: CONVENZIONI --}}
          <div class="tab-pane fade" id="pane-conv" role="tabpanel" aria-labelledby="tab-conv">

            <div class="alert alert-info">
              <div><b>Modalità con lotti (Sì):</b> per ogni lotto verranno create una o più convenzioni chiamate <code>&lt;Nome Azienda&gt; – &lt;Nome Lotto&gt;</code>.</div>
              <div class="mt-1"><b>Modalità senza lotti (No):</b> verranno create convenzioni con il solo nome azienda.</div>
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
                {{-- righe generate via JS --}}
              </tbody>
            </table>

            <div class="d-flex justify-content-between mt-3">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i> Annulla
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
  // ======= ELEMENTI =======
  const tabAnag  = document.getElementById('tab-anagrafica');
  const tabLotti = document.getElementById('tab-lotti');
  const tabConv  = document.getElementById('tab-conv');

  const btnGoLotti   = document.getElementById('goToLotti');
  const btnBackAnag  = document.getElementById('backToAnagrafica');
  const btnGoConv    = document.getElementById('goToConvenzioni');
  const btnBackLotti = document.getElementById('backToLotti');

  const nomeAziendaInput = document.getElementById('Nome');

  // Radio lotti presenti
  const radioYes = document.getElementById('lottiYes');
  const radioNo  = document.getElementById('lottiNo');

  // LOTTI editor
  const lottiEditor = document.getElementById('lottiEditor');
  const tableBody   = document.querySelector('#lottiTable tbody');
  const addBtn      = document.getElementById('addLottoBtn');
  const inputNome   = document.getElementById('newLottoNome');
  const inputDesc   = document.getElementById('newLottoDesc');

  // CONVENZIONI
  const convTbody = document.querySelector('#convTable tbody');

  // Dati server
  const ASSOCS = @json($associazioni->map(function($a) { return ['id' => $a->idAssociazione, 'text' => $a->Associazione]; }));
  const OLD_CONV_ASSOC = @json(old('conv_assoc', []));

  // ======= UTILS =======
  function escapeHtml(s) {
    return (s ?? '').toString()
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function setDisabledIn(container, disabled) {
    container.querySelectorAll('input, select, textarea, button').forEach(el => {
      el.disabled = !!disabled;
    });
  }

  function getRows() {
    return Array.from(tableBody.querySelectorAll('tr[data-row-index]'));
  }

  function getRealLottiCount() {
    return getRows().filter(tr => {
      const idx = parseInt(tr.getAttribute('data-row-index'), 10);
      const v = tr.querySelector(`input[name="lotti[${idx}][nomeLotto]"]`)?.value || '';
      return v.trim() !== '';
    }).length;
  }

  let nextIndex = (function() {
    let max = -1;
    getRows().forEach(tr => {
      const i = parseInt(tr.getAttribute('data-row-index'), 10);
      if (!isNaN(i) && i > max) max = i;
    });
    return max + 1;
  })();

  function renumberRows() {
    const trs = getRows();
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

  // ======= Eventi LOTTI =======
  addBtn?.addEventListener('click', function() {
    const nome = (inputNome.value || '').trim();
    const desc = (inputDesc.value || '').trim();
    if (!nome) { inputNome.focus(); return; }
    addRow(nome, desc);
    inputNome.value = ''; inputDesc.value = '';
  });

  tableBody.addEventListener('click', function(e) {
    const btn = e.target.closest('.js-remove-row');
    if (!btn) return;
    btn.closest('tr').remove();
    renumberRows();
    updateConvTabState();
  });

  // Quando scrivo nei campi nome lotto aggiorno lo stato della tab
  tableBody.addEventListener('input', function(e) {
    if (e.target && e.target.name && e.target.name.endsWith('[nomeLotto]')) {
      updateConvTabState();
    }
  });

  // ======= Modalità lotti Sì/No =======
  function isNoMode() { return radioNo?.checked; }

  function applyLottiModeUI() {
    if (isNoMode()) {
      // Nascondi & disabilita editor lotti
      lottiEditor.style.opacity = '0.5';
      lottiEditor.style.pointerEvents = 'none';
      setDisabledIn(lottiEditor, true);
    } else {
      lottiEditor.style.opacity = '1';
      lottiEditor.style.pointerEvents = 'auto';
      setDisabledIn(lottiEditor, false);
    }
  }

  radioYes?.addEventListener('change', () => { applyLottiModeUI(); updateConvTabState(); });
  radioNo ?.addEventListener('change', () => { applyLottiModeUI(); updateConvTabState(); });

  // ======= Wizard nav =======
  function activateTab(btnEl) { if (btnEl) new bootstrap.Tab(btnEl).show(); }

  btnGoLotti?.addEventListener('click', function() {
    if (!(nomeAziendaInput.value || '').trim()) { nomeAziendaInput.focus(); return; }
    activateTab(tabLotti);
  });

  btnBackAnag?.addEventListener('click', function() { activateTab(tabAnag); });

  btnGoConv?.addEventListener('click', function() {
    if (!(nomeAziendaInput.value || '').trim()) { nomeAziendaInput.focus(); return; }

    // Se No -> vai sempre; Se Sì -> serve almeno 1 lotto con nome
    if (!isNoMode() && getRealLottiCount() === 0) {
      // niente blocchi strani: resta in lotti e mostra un hint
      inputNome?.focus();
      return;
    }
    buildConvenzioniPreview();
    activateTab(tabConv);
  });

  btnBackLotti?.addEventListener('click', function() { activateTab(tabLotti); });

  // ======= Convenzioni (anteprima) =======
  function buildConvenzioniPreview() {
    convTbody.innerHTML = '';
    const azienda = (nomeAziendaInput.value || '').trim();

    if (isNoMode()) {
      // Modalità senza lotti: una riga unica, conv name = <Azienda>
      const selectedIds = (OLD_CONV_ASSOC['0'] || []).map(String);
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="text-muted">1</td>
        <td>
          <input type="text" class="form-control" value="${escapeHtml(azienda)}" readonly>
          <div class="form-text">Modalità senza lotti (LOTTI NON PRESENTI)</div>
        </td>
        <td>
          <select name="conv_assoc[0][]" class="form-select" multiple size="6">
            ${ASSOCS.map(a => {
              const sel = selectedIds.includes(String(a.id)) ? 'selected' : '';
              return `<option value="${a.id}" ${sel}>${escapeHtml(a.text)}</option>`;
            }).join('')}
          </select>
        </td>
      `;
      convTbody.appendChild(row);
      return;
    }

    // Modalità con lotti: una riga per ogni lotto con nome valorizzato
    const rows = getRows().filter(tr => {
      const idx = parseInt(tr.getAttribute('data-row-index'), 10);
      const v = tr.querySelector(`input[name="lotti[${idx}][nomeLotto]"]`)?.value || '';
      return v.trim() !== '';
    });

    rows.forEach((tr, i) => {
      const idx = parseInt(tr.getAttribute('data-row-index'), 10);
      const nomeL = tr.querySelector(`input[name="lotti[${idx}][nomeLotto]"]`)?.value || '';
      const convName = `${azienda} – ${nomeL}`;

      let selectedIds = [];
      if (Object.prototype.hasOwnProperty.call(OLD_CONV_ASSOC, String(idx))) {
        selectedIds = (OLD_CONV_ASSOC[String(idx)] || []).map(x => String(x));
      }

      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="text-muted">${i + 1}</td>
        <td><input type="text" class="form-control" value="${escapeHtml(convName)}" readonly></td>
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

  // ======= Abilita/Disabilita tab Convenzioni =======
  function updateConvTabState() {
    const enable = isNoMode() || getRealLottiCount() > 0;
    if (tabConv) {
      tabConv.disabled = !enable;
      tabConv.classList.toggle('disabled', !enable);
      tabConv.setAttribute('aria-disabled', String(!enable));
    }
  }

  tabConv?.addEventListener('show.bs.tab', function (e) {
    if (!(isNoMode() || getRealLottiCount() > 0)) {
      e.preventDefault();
      return;
    }
    buildConvenzioniPreview();
  });

  // ======= Init =======
  applyLottiModeUI();
  updateConvTabState();

})();
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('citta_combo');
  const list = document.getElementById('citta_list');
  const provinciaSelect = document.getElementById('provincia');

  function filterList(options = {}) {
    const provincia = provinciaSelect.value;
    const text = (input.value || '').toLowerCase();

    if (!provincia) { list.style.display = 'none'; return; }

    let hasVisible = false;
    Array.from(list.children).forEach(li => {
      const matchProvincia = li.dataset.provincia === provincia;
      const matchText = li.textContent.toLowerCase().includes(text);
      if (matchProvincia && matchText) {
        li.style.display = 'block'; hasVisible = true;
      } else {
        li.style.display = 'none';
      }
    });

    list.style.display = hasVisible ? 'block' : 'none';

    const selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.style.display === 'none') {
      input.value = '';
      selectedLi.removeAttribute('data-selected');
    }
  }

  input.addEventListener('focus', () => filterList());
  input.addEventListener('input', () => { 
    Array.from(list.children).forEach(li => li.removeAttribute('data-selected'));
    filterList();
  });

  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (input.value.trim() !== '') {
        input.value = input.value.trim();
        input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
      }
      list.style.display = 'none';
    }
  });

  provinciaSelect.addEventListener('change', function() {
    const selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.dataset.provincia !== provinciaSelect.value) {
      selectedLi.removeAttribute('data-selected');
      input.value = '';
    }
    filterList();
  });

  Array.from(list.children).forEach(li => {
    li.addEventListener('click', () => {
      input.value = li.textContent.trim();
      input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
      Array.from(list.children).forEach(i => i.removeAttribute('data-selected'));
      li.setAttribute('data-selected', 'true');
      list.style.display = 'none';
    });
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.position-relative')) {
      list.style.display = 'none';
    }
  });

  // init
  filterList();
  list.style.display = 'none';
});
</script>
@endpush
