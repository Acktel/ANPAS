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
            {{-- Provincia + Città (SOLO nella tab Anagrafica) --}}
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="provincia" class="form-label">Provincia</label>
                <select class="form-control" id="provincia" name="provincia">
                  <option value="" disabled {{ old('provincia', $azienda->provincia ?? '') === null ? 'selected' : '' }}>-- Seleziona provincia --</option>
                  @php
                    $allowedProvinces = ['VC','AL','AT','BI','CN','NO','TO','VB'];
                    $provinceUniche = collect($cities)->pluck('sigla_provincia')->unique()
                      ->filter(fn($sigla) => in_array($sigla, $allowedProvinces))->sort()->values();
                  @endphp
                  @foreach($provinceUniche as $sigla)
                    <option value="{{ $sigla }}" {{ old('provincia', $azienda->provincia ?? '') == $sigla ? 'selected' : '' }}>
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
                         value="{{ old('citta', isset($azienda->citta) ? ucfirst(trim($azienda->citta)) : '') }}">
                  <ul id="citta_list" class="list-group position-absolute w-100"
                      style="z-index:1000; display:none; max-height:200px; overflow-y:auto;">
                    @foreach($cities as $city)
                      <li class="list-group-item list-group-item-action"
                          data-provincia="{{ $city->sigla_provincia }}"
                          {{ old('citta', $azienda->citta ?? '') == trim($city->denominazione_ita) ? 'data-selected=true' : '' }}>
                        {{ ucfirst(trim($city->denominazione_ita)) }}
                      </li>
                    @endforeach
                  </ul>
                </div>
              </div>
            </div>

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

            <div class="d-flex justify-content-center">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">
                <i class="fas fa-times me-2"></i> Annulla
              </a>
              <button type="button" class="btn btn-anpas-green" id="goToLotti"><i class="fas fa-check me-1"></i> Avanti</button>
            </div>
          </div>

          {{-- TAB 2: LOTTI --}}
          <div class="tab-pane fade" id="pane-lotti" role="tabpanel" aria-labelledby="tab-lotti">
            <div class="card-header bg-anpas-primary d-flex align-items-center justify-content-between flex-wrap gap-2">
              <label class="form-label mb-0">Inserire/modificare i lotti per l’azienda</label>
              <div>                
                <span class="me-2">Lotti presenti:</span>
                @php $lp = old('lotti_presenti', '1'); @endphp
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="lotti_presenti" id="lottiYes" value="1" {{ $lp == '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="lottiYes">Sì</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="lotti_presenti" id="lottiNo" value="0" {{ $lp == '0' ? 'checked' : '' }}>
                  <label class="form-check-label" for="lottiNo">No</label>
                </div>
              </div>
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
                  $oldLotti = old('lotti', []);
                  $rows = count($oldLotti) ? collect($oldLotti) : $lotti;
                @endphp
                @foreach($rows as $i => $lotto)
                  @php
                    $id   = is_array($lotto) ? ($lotto['id'] ?? null) : ($lotto->id ?? null);
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

            <div class="d-flex justify-content-center mt-5">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">
                <i class="fas fa-times me-2"></i> Annulla
              </a>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" id="backToAnagrafica"><i class="fas fa-arrow-left"></i>Indietro</button>
                <button type="button" class="btn btn-anpas-green" id="goToConvenzioni"><i class="fas fa-check me-1"></i>Avanti</button>
              </div>
            </div>
          </div>

          {{-- TAB 3: CONVENZIONI --}}
          <div class="tab-pane fade" id="pane-conv" role="tabpanel" aria-labelledby="tab-conv">
            <div class="alert alert-info">
              Per ogni <b>lotto</b> verranno create/aggiornate una o più <b>convenzioni</b> con nome
              <code>«&lt;Nome Azienda&gt; – &lt;Nome Lotto&gt;»</code>. Se selezioni più associazioni, verrà creata una convenzione per ognuna.<br>
              Se hai indicato <b>“Lotti presenti: No”</b>, la convenzione avrà come nome solo <b>il nome dell’azienda</b>.
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
                {{-- generato via JS --}}
              </tbody>
            </table>

            <div class="d-flex justify-content-center mt-5">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Annulla
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
(function(){
  const LNP = 'LOTTI NON PRESENTI';

  // ====== ELEMENTI ======
  const tabAnag  = document.getElementById('tab-anagrafica');
  const tabLotti = document.getElementById('tab-lotti');
  const tabConv  = document.getElementById('tab-conv');

  const btnGoLotti   = document.getElementById('goToLotti');
  const btnBackAnag  = document.getElementById('backToAnagrafica');
  const btnGoConv    = document.getElementById('goToConvenzioni');
  const btnBackLotti = document.getElementById('backToLotti');

  const radioYes = document.getElementById('lottiYes');
  const radioNo  = document.getElementById('lottiNo');

  const nomeAziendaInput = document.getElementById('Nome');

  // LOTTI
  const tableBody = document.querySelector('#lottiTable tbody');
  const addBtn    = document.getElementById('addLottoBtn');
  const inputNome = document.getElementById('newLottoNome');
  const inputDesc = document.getElementById('newLottoDesc');

  // CONVENZIONI
  const convTbody = document.querySelector('#convTable tbody');

  // Dati dal server
  const ASSOCS = @json($associazioni->map(function($a){ return ['id'=>$a->idAssociazione,'text'=>$a->Associazione]; }));
  const CONV_ASSOC_BY_LOTTO = @json($convAssocByLotto ?? []);
  const OLD_CONV_ASSOC      = @json(old('conv_assoc', []));

  // ====== UTILS ======
  function escapeHtml(s){
    return (s ?? '').toString()
      .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
      .replaceAll('"','&quot;').replaceAll("'","&#039;");
  }
  function getVisibleLottiRows(){
    return Array.from(tableBody.querySelectorAll('tr[data-row-index]'))
      .filter(tr => tr.style.display !== 'none');
  }
  function isNoMode(){ return radioNo?.checked; }

  // nextIndex
  let nextIndex = (() => {
    let max = -1;
    getVisibleLottiRows().forEach(tr => {
      const i = parseInt(tr.getAttribute('data-row-index'),10);
      if (!isNaN(i) && i > max) max = i;
    });
    return max + 1;
  })();

  function renumberRows(){
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

  function addRow(nomeVal = '', descVal = ''){
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
  }

  function clearLotti(){
    tableBody.innerHTML = '';
    nextIndex = 0;
  }

  function ensureNoLottiMode(){
    // azzero e creo UN SOLO lotto placeholder
    clearLotti();
    addRow(LNP, '');
    renumberRows();
  }

  function enableConvTab(){
    if (!tabConv) return;
    tabConv.removeAttribute('disabled');
    tabConv.classList.remove('disabled');
    tabConv.setAttribute('aria-disabled','false');
  }
  function maybeToggleConvTab(){
    // conv abilitata se almeno un lotto oppure NO
    const hasLotti = getVisibleLottiRows().length > 0;
    if (hasLotti || isNoMode()) enableConvTab();
    else {
      tabConv?.setAttribute('disabled','true');
      tabConv?.classList.add('disabled');
      tabConv?.setAttribute('aria-disabled','true');
    }
  }
  function activateTab(btnEl){ if (btnEl) new bootstrap.Tab(btnEl).show(); }

  // ====== HANDLER RADIO ======
  radioNo?.addEventListener('change', () => {
    if (radioNo.checked) {
      ensureNoLottiMode();
      maybeToggleConvTab();
      buildConvenzioniPreview();
      activateTab(tabConv); // vai avanti di un tab
    }
  });
  radioYes?.addEventListener('change', () => {
    if (radioYes.checked) {
      // modalità “Sì”: resta come sei; conv si sblocca solo se hai lotti
      maybeToggleConvTab();
    }
  });

  // ====== HANDLERS LOTTI ======
  addBtn?.addEventListener('click', function(){
    const nome = (inputNome.value || '').trim();
    const desc = (inputDesc.value || '').trim();
    if (!nome) { inputNome.focus(); return; }
    addRow(nome, desc);
    inputNome.value = '';
    inputDesc.value = '';
    radioYes.checked = true; // se aggiungi un lotto, metti Sì
    maybeToggleConvTab();
  });

  tableBody.addEventListener('click', function(e){
    const btn = e.target.closest('.js-remove-row');
    if (!btn) return;
    const tr = btn.closest('tr');
    const idInput  = tr.querySelector('input[name$="[id]"]');
    const delInput = tr.querySelector('input[name$="[_delete]"]');

    if (idInput && idInput.value) {
      delInput.value = '1';
      tr.style.display = 'none';
    } else {
      tr.remove();
    }
    renumberRows();
    maybeToggleConvTab();
  });

  // ====== Wizard nav ======
  btnGoLotti?.addEventListener('click', function(){
    // solo qui richiedi Nome per UX
    if (!(nomeAziendaInput.value || '').trim()) { nomeAziendaInput.focus(); return; }
    activateTab(tabLotti);
  });
  btnBackAnag?.addEventListener('click', () => activateTab(tabAnag));

  btnGoConv?.addEventListener('click', function(){
    if (isNoMode()) {
      if (getVisibleLottiRows().length === 0) ensureNoLottiMode();
      enableConvTab();
      buildConvenzioniPreview();
      activateTab(tabConv);
      return;
    }
    // Sì: serve almeno un lotto
    if (getVisibleLottiRows().length === 0) {
      // blocco “gentile”
      alert('Aggiungi almeno un lotto oppure seleziona "Lotti presenti: No".');
      return;
    }
    enableConvTab();
    buildConvenzioniPreview();
    activateTab(tabConv);
  });

  btnBackLotti?.addEventListener('click', () => activateTab(tabLotti));

  tabConv?.addEventListener('show.bs.tab', buildConvenzioniPreview);

  // ====== Convenzioni preview ======
  function buildConvenzioniPreview(){
    convTbody.innerHTML = '';
    const azienda = ((nomeAziendaInput.value || '').trim()) || 'Azienda';

    if (isNoMode()) {
      // un’unica riga, nome = solo azienda
      // usa l’indice 0 (abbiamo forzato il placeholder)
      const idx = 0;
      const selectedIds = (OLD_CONV_ASSOC[String(idx)] || []).map(x => String(x));
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="text-muted">1</td>
        <td><input type="text" class="form-control" value="${escapeHtml(azienda)}" readonly></td>
        <td>
          <select name="conv_assoc[${idx}][]" class="form-select" multiple size="6">
            ${ASSOCS.map(a => {
              const sel = selectedIds.includes(String(a.id)) ? 'selected' : '';
              return `<option value="${a.id}" ${sel}>${escapeHtml(a.text)}</option>`;
            }).join('')}
          </select>
          <div class="form-text">Seleziona una o più associazioni</div>
        </td>
      `;
      convTbody.appendChild(row);
      return;
    }

    // Modalità Sì -> una riga per lotto
    const lotti = getVisibleLottiRows();
    lotti.forEach((tr, i) => {
      const idx   = parseInt(tr.getAttribute('data-row-index'), 10);
      const idL   = tr.querySelector(`input[name="lotti[${idx}][id]"]`)?.value || '';
      const nomeL = tr.querySelector(`input[name="lotti[${idx}][nomeLotto]"]`)?.value || '';
      const convNm = `${azienda} – ${nomeL}`;

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

  // ====== INIT ======
  // Se la pagina arriva con “No” già selezionato (old input), prepariamo subito
  if (isNoMode() && getVisibleLottiRows().length === 0) {
    ensureNoLottiMode();
  }
  maybeToggleConvTab();
})();
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const input = document.getElementById('citta_combo');
  const list  = document.getElementById('citta_list');
  const provinciaSelect = document.getElementById('provincia');

  function filterList(options = {}){
    const ignoreText = !!options.ignoreText;
    const provincia = provinciaSelect.value;
    if (!provincia) { list.style.display = 'none'; return; }
    const text = ignoreText ? '' : input.value.trim().toLowerCase();

    let hasVisible = false;
    Array.from(list.children).forEach(li => {
      const matchProvincia = li.dataset.provincia === provincia;
      const matchText = text === '' ? true : li.textContent.toLowerCase().includes(text);
      li.style.display = (matchProvincia && matchText) ? 'block' : 'none';
      hasVisible = hasVisible || (li.style.display === 'block');
    });

    list.style.display = hasVisible ? 'block' : 'none';

    const selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.style.display === 'none') {
      input.value = '';
      selectedLi.removeAttribute('data-selected');
    }
  }

  input.addEventListener('focus', () => filterList({ ignoreText: true }));
  input.addEventListener('input', () => {
    Array.from(list.children).forEach(li => li.removeAttribute('data-selected'));
    filterList({ ignoreText: false });
  });
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      if (input.value.trim() !== '') {
        input.value = input.value.trim();
        input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
      }
      list.style.display = 'none';
    }
  });
  provinciaSelect.addEventListener('change', function(){
    const selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.dataset.provincia !== provinciaSelect.value) {
      selectedLi.removeAttribute('data-selected');
      input.value = '';
    }
    filterList({ ignoreText: true });
  });
  Array.from(list.children).forEach(li => {
    li.addEventListener('click', () => {
      input.value = li.textContent.trim();
      input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
      Array.from(list.children).forEach(i => i.removeAttribute('data-selected'));
      li.setAttribute('data-selected','true');
      list.style.display = 'none';
    });
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.position-relative')) list.style.display = 'none';
  });

  // init
  filterList();
  list.style.display = 'none';
});
</script>
@endpush
