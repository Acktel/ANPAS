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

            {{-- Nome (PRIMO CAMPO) --}}
            <div class="mb-3">
              <label for="Nome" class="form-label">Nome Azienda</label>
              <input type="text" name="Nome" id="Nome" class="form-control" required value="{{ old('Nome', $azienda->Nome) }}">
            </div>

            {{-- Provincia + Città --}}
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="provincia" class="form-label">Provincia</label>
                <select class="form-control" id="provincia" name="provincia">
                  <option value="" {{ old('provincia', $azienda->provincia ?? '') ? '' : 'selected' }} disabled>-- Seleziona provincia --</option>
                  @php
                    $allowedProvinces = ['VC','AL','AT','BI','CN','NO','TO','VB'];
                    $provinceUniche = collect($cities)->pluck('sigla_provincia')->unique()
                      ->filter(function($sigla) use ($allowedProvinces){ return in_array($sigla, $allowedProvinces); })
                      ->sort()->values();
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

            {{-- CAP --}}
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="cap_select" class="form-label">CAP</label>
                <select id="cap_select" name="cap" class="form-control" {{ old('cap', $azienda->cap ?? '') ? '' : '' }}>
                  <option value="">-- Seleziona CAP --</option>
                  {{-- Le opzioni vengono popolate via JS in base a Provincia + Città --}}
                </select>
              </div>
            </div>

            {{-- Indirizzo --}}
            <div class="mb-3">
              <label for="Indirizzo" class="form-label">Indirizzo</label>
              <input type="text" name="Indirizzo" id="Indirizzo" class="form-control" value="{{ old('Indirizzo', $azienda->Indirizzo ?? '') }}">
            </div>

            {{-- Email --}}
            <div class="mb-3">
              <label for="mail" class="form-label">Email</label>
              <input type="email" name="mail" id="mail" class="form-control" value="{{ old('mail', $azienda->mail ?? '') }}">
            </div>

            {{-- Note --}}
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

            <div class="d-flex justify-content-center mt-5 myborder-button">
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
  'use strict';
  var LNP = 'LOTTI NON PRESENTI';

  // ====== ELEMENTI ======
  var tabAnag  = document.getElementById('tab-anagrafica');
  var tabLotti = document.getElementById('tab-lotti');
  var tabConv  = document.getElementById('tab-conv');

  var btnGoLotti   = document.getElementById('goToLotti');
  var btnBackAnag  = document.getElementById('backToAnagrafica');
  var btnGoConv    = document.getElementById('goToConvenzioni');
  var btnBackLotti = document.getElementById('backToLotti');

  var radioYes = document.getElementById('lottiYes');
  var radioNo  = document.getElementById('lottiNo');

  var nomeAziendaInput = document.getElementById('Nome');

  // LOTTI
  var tableBody = document.querySelector('#lottiTable tbody');
  var addBtn    = document.getElementById('addLottoBtn');
  var inputNome = document.getElementById('newLottoNome');
  var inputDesc = document.getElementById('newLottoDesc');

  // CONVENZIONI
  var convTbody = document.querySelector('#convTable tbody');

  // Dati server
  var ASSOCS = @json($associazioni->map(function($a){ return ['id'=>$a->idAssociazione,'text'=>$a->Associazione]; }));
  var CONV_ASSOC_BY_LOTTO = @json($convAssocByLotto ?? []);
  var OLD_CONV_ASSOC      = @json(old('conv_assoc', []));

  // ====== UTILS ======
  function escapeHtml(s){
    s = (s || '').toString();
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function getVisibleLottiRows(){
    if (!tableBody) return [];
    var rows = tableBody.querySelectorAll('tr[data-row-index]');
    var out = [];
    for (var i=0;i<rows.length;i++){
      if (rows[i].style.display !== 'none') out.push(rows[i]);
    }
    return out;
  }
  function isNoMode(){ return !!(radioNo && radioNo.checked); }

  // nextIndex
  var nextIndex = (function(){
    var max = -1;
    var rows = getVisibleLottiRows();
    for (var i=0;i<rows.length;i++){
      var n = parseInt(rows[i].getAttribute('data-row-index'),10);
      if (!isNaN(n) && n > max) max = n;
    }
    return max + 1;
  })();

  function renumberRows(){
    var trs = getVisibleLottiRows();
    for (var i=0;i<trs.length;i++){
      var tr = trs[i];
      tr.setAttribute('data-row-index', i);
      var td = tr.querySelector('td');
      if (td) td.textContent = i + 1;

      var idH  = tr.querySelector('input[name^="lotti["][name$="[id]"]');
      var delH = tr.querySelector('input[name^="lotti["][name$="[_delete]"]');
      var nm   = tr.querySelector('input[name^="lotti["][name$="[nomeLotto]"]');
      var ds   = tr.querySelector('input[name^="lotti["][name$="[descrizione]"]');

      if (idH)  idH.name  = 'lotti['+i+'][id]';
      if (delH) delH.name = 'lotti['+i+'][_delete]';
      if (nm)   nm.name   = 'lotti['+i+'][nomeLotto]';
      if (ds)   ds.name   = 'lotti['+i+'][descrizione]';
    }
    nextIndex = trs.length;
  }

  function addRow(nomeVal, descVal){
    nomeVal = nomeVal || '';
    descVal = descVal || '';
    var i = nextIndex++;
    var tr = document.createElement('tr');
    tr.setAttribute('data-row-index', i);
    tr.innerHTML =
      '<input type="hidden" name="lotti['+i+'][id]" value="">' +
      '<input type="hidden" name="lotti['+i+'][_delete]" value="0" class="lotto-delete">' +
      '<td class="text-muted">'+(i + 1)+'</td>' +
      '<td><input type="text" name="lotti['+i+'][nomeLotto]" class="form-control" value="'+escapeHtml(nomeVal)+'"></td>' +
      '<td><input type="text" name="lotti['+i+'][descrizione]" class="form-control" value="'+escapeHtml(descVal)+'"></td>' +
      '<td class="text-center">' +
        '<button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">' +
          '<i class="fas fa-trash-alt"></i>' +
        '</button>' +
      '</td>';
    if (tableBody) tableBody.appendChild(tr);
  }

  function clearLotti(){ if (tableBody) tableBody.innerHTML = ''; nextIndex = 0; }

  function ensureNoLottiMode(){ clearLotti(); addRow(LNP, ''); renumberRows(); }

  function enableConvTab(){
    if (!tabConv) return;
    tabConv.removeAttribute('disabled');
    tabConv.classList.remove('disabled');
    tabConv.setAttribute('aria-disabled','false');
  }
  function disableConvTab(){
    if (!tabConv) return;
    tabConv.setAttribute('disabled','true');
    tabConv.classList.add('disabled');
    tabConv.setAttribute('aria-disabled','true');
  }
  function maybeToggleConvTab(){
    var hasLotti = getVisibleLottiRows().length > 0;
    if (hasLotti || isNoMode()) enableConvTab(); else disableConvTab();
  }
  function activateTab(btnEl){
    if (!btnEl) return;
    try {
      if (window.bootstrap && bootstrap.Tab){ new bootstrap.Tab(btnEl).show(); return; }
    } catch(_){}
    btnEl.click();
    var target = btnEl.getAttribute('data-bs-target');
    if (!target) return;
    var panes = document.querySelectorAll('.tab-pane');
    for (var i=0;i<panes.length;i++){ panes[i].classList.remove('show'); panes[i].classList.remove('active'); }
    var pane = document.querySelector(target);
    if (pane){ pane.classList.add('show'); pane.classList.add('active'); }
  }

  // ====== RADIO ======
  if (radioNo){
    radioNo.addEventListener('change', function(){
      if (radioNo.checked){
        ensureNoLottiMode();
        maybeToggleConvTab();
        buildConvenzioniPreview();
        activateTab(tabConv);
      }
    });
  }
  if (radioYes){
    radioYes.addEventListener('change', function(){
      if (radioYes.checked){ maybeToggleConvTab(); }
    });
  }

  // ====== LOTTI ======
  if (addBtn){
    addBtn.addEventListener('click', function(){
      var nome = (inputNome && inputNome.value) ? inputNome.value.trim() : '';
      var desc = (inputDesc && inputDesc.value) ? inputDesc.value.trim() : '';
      if (!nome){ if (inputNome) inputNome.focus(); return; }
      addRow(nome, desc);
      if (inputNome) inputNome.value = '';
      if (inputDesc) inputDesc.value = '';
      if (radioYes) radioYes.checked = true;
      maybeToggleConvTab();
    });
  }

  if (tableBody){
    tableBody.addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('.js-remove-row') : null;
      if (!btn) return;
      var tr = btn.closest('tr');
      var idInput  = tr ? tr.querySelector('input[name$="[id]"]') : null;
      var delInput = tr ? tr.querySelector('input[name$="[_delete]"]') : null;

      if (idInput && idInput.value){
        if (delInput) delInput.value = '1';
        tr.style.display = 'none';
      } else if (tr && tr.parentNode){
        tr.parentNode.removeChild(tr);
      }
      renumberRows();
      maybeToggleConvTab();
    });
  }

  // ====== NAV ======
  if (btnGoLotti){
    btnGoLotti.addEventListener('click', function(){
      if (!nomeAziendaInput || !(nomeAziendaInput.value||'').trim()){ if (nomeAziendaInput) nomeAziendaInput.focus(); return; }
      activateTab(tabLotti);
    });
  }
  if (btnBackAnag)  btnBackAnag.addEventListener('click', function(){ activateTab(tabAnag); });
  if (btnBackLotti) btnBackLotti.addEventListener('click', function(){ activateTab(tabLotti); });

  if (btnGoConv){
    btnGoConv.addEventListener('click', function(){
      if (isNoMode()){
        if (getVisibleLottiRows().length === 0) ensureNoLottiMode();
        enableConvTab(); buildConvenzioniPreview(); activateTab(tabConv); return;
      }
      if (getVisibleLottiRows().length === 0){ alert('Aggiungi almeno un lotto oppure seleziona "Lotti presenti: No".'); return; }
      enableConvTab(); buildConvenzioniPreview(); activateTab(tabConv);
    });
  }

  if (tabConv){
    tabConv.addEventListener('show.bs.tab', function(){ buildConvenzioniPreview(); });
  }

  // ====== Convenzioni preview ======
  function buildConvenzioniPreview(){
    if (!convTbody) return;
    convTbody.innerHTML = '';
    var azienda = ((nomeAziendaInput && nomeAziendaInput.value) ? nomeAziendaInput.value.trim() : '') || 'Azienda';

    if (isNoMode()){
      var idx = 0;
      var selectedIds = (OLD_CONV_ASSOC && OLD_CONV_ASSOC[String(idx)]) ? OLD_CONV_ASSOC[String(idx)].map(String) : [];
      var opts = '';
      for (var k=0;k<ASSOCS.length;k++){
        var a = ASSOCS[k];
        var sel = selectedIds.indexOf(String(a.id)) !== -1 ? ' selected' : '';
        opts += '<option value="'+a.id+'"'+sel+'>'+escapeHtml(a.text)+'</option>';
      }
      var row = document.createElement('tr');
      row.innerHTML =
        '<td class="text-muted">1</td>' +
        '<td><input type="text" class="form-control" value="'+escapeHtml(azienda)+'" readonly></td>' +
        '<td><select name="conv_assoc['+idx+'][]" class="form-select" multiple size="6">'+opts+'</select>' +
        '<div class="form-text">Seleziona una o più associazioni</div></td>';
      convTbody.appendChild(row);
      return;
    }

    var lotti = getVisibleLottiRows();
    for (var i=0;i<lotti.length;i++){
      var tr = lotti[i];
      var idxRow = parseInt(tr.getAttribute('data-row-index'),10);
      if (isNaN(idxRow)) continue;
      var idLInp = tr.querySelector('input[name="lotti['+idxRow+'][id]"]');
      var nmInp  = tr.querySelector('input[name="lotti['+idxRow+'][nomeLotto]"]');
      var idL    = idLInp ? idLInp.value : '';
      var nomeL  = nmInp ? nmInp.value : '';
      var convNm = azienda + ' \u2013 ' + nomeL;

      var selectedIds2 = [];
      if (OLD_CONV_ASSOC && Object.prototype.hasOwnProperty.call(OLD_CONV_ASSOC, String(idxRow))){
        selectedIds2 = (OLD_CONV_ASSOC[String(idxRow)] || []).map(String);
      } else if (idL && CONV_ASSOC_BY_LOTTO[String(idL)]){
        selectedIds2 = (CONV_ASSOC_BY_LOTTO[String(idL)] || []).map(String);
      }

      var opts2 = '';
      for (var j=0;j<ASSOCS.length;j++){
        var a2 = ASSOCS[j];
        var sel2 = selectedIds2.indexOf(String(a2.id)) !== -1 ? ' selected' : '';
        opts2 += '<option value="'+a2.id+'"'+sel2+'>'+escapeHtml(a2.text)+'</option>';
      }

      var row2 = document.createElement('tr');
      row2.innerHTML =
        '<td class="text-muted">'+(i + 1)+'</td>' +
        '<td><input type="text" class="form-control" value="'+escapeHtml(convNm)+'" readonly></td>' +
        '<td><select name="conv_assoc['+idxRow+'][]" class="form-select" multiple size="6">'+opts2+'</select>' +
        '<div class="form-text">Seleziona una o più associazioni per questo lotto</div></td>';
      convTbody.appendChild(row2);
    }
  }

  // INIT
  if (isNoMode() && getVisibleLottiRows().length === 0){ ensureNoLottiMode(); }
  maybeToggleConvTab();
})();
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  'use strict';
  var input = document.getElementById('citta_combo');
  var list  = document.getElementById('citta_list');
  var provinciaSelect = document.getElementById('provincia');

  // ===== CAP handling =====
  var capSelect = document.getElementById('cap_select');
  var OLD_CAP   = @json(old('cap', $azienda->cap ?? ''));
  var CAPS_RAW  = @json($caps ?? []); // [{cap, denominazione_ita, sigla_provincia, ...}]

  // Build map { "PROV|città lower" : [cap,...] }
  var CAP_MAP = {};
  for (var i=0;i<(CAPS_RAW||[]).length;i++){
    var r = CAPS_RAW[i];
    var key = String(r.sigla_provincia||'') + '|' + String(r.denominazione_ita||'').trim().toLowerCase();
    if (!CAP_MAP[key]) CAP_MAP[key] = [];
    CAP_MAP[key].push(String(r.cap));
  }

  function resetCap(){
    if (!capSelect) return;
    capSelect.innerHTML = '<option value="">-- Seleziona CAP --</option>';
    capSelect.disabled = true;
  }
  function populateCaps(){
    if (!capSelect) return;
    var prov = (provinciaSelect && provinciaSelect.value) ? provinciaSelect.value.trim() : '';
    var city = (input && input.value) ? input.value.trim().toLowerCase() : '';
    if (!prov || !city){ resetCap(); return; }
    var key = prov + '|' + city;
    var caps = CAP_MAP[key] || [];
    var html = '<option value="">-- Seleziona CAP --</option>';
    for (var i=0;i<caps.length;i++){ html += '<option value="'+caps[i]+'">'+caps[i]+'</option>'; }
    capSelect.innerHTML = html;
    capSelect.disabled = caps.length === 0;
    if (OLD_CAP && caps.indexOf(String(OLD_CAP)) !== -1){
      capSelect.value = String(OLD_CAP);
    }
  }
  // ===== /CAP handling =====

  function filterList(options){
    options = options || {};
    var ignoreText = !!options.ignoreText;
    var provincia = provinciaSelect ? provinciaSelect.value : '';
    if (!provincia){ if (list) list.style.display = 'none'; resetCap(); return; }
    var text = '';
    if (!ignoreText && input && input.value){ text = input.value.trim().toLowerCase(); }

    var hasVisible = false;
    var items = list ? list.children : [];
    for (var i=0;i<items.length;i++){
      var li = items[i];
      var matchProvincia = (li.getAttribute('data-provincia') === provincia);
      var liTxt = (li.textContent || '').toLowerCase();
      var matchText = text === '' ? true : (liTxt.indexOf(text) !== -1);
      li.style.display = (matchProvincia && matchText) ? 'block' : 'none';
      if (li.style.display === 'block') hasVisible = true;
    }
    if (list) list.style.display = hasVisible ? 'block' : 'none';

    var selectedLi = list ? list.querySelector('[data-selected="true"]') : null;
    if (selectedLi && selectedLi.style.display === 'none'){
      if (input) input.value = '';
      selectedLi.removeAttribute('data-selected');
      resetCap();
    }
  }

  if (input){
    input.addEventListener('focus', function(){ filterList({ ignoreText: true }); });
    input.addEventListener('input', function(){
      var items = list ? list.children : [];
      for (var i=0;i<items.length;i++){ items[i].removeAttribute('data-selected'); }
      filterList({ ignoreText: false });
      resetCap();
    });
    input.addEventListener('keydown', function(e){
      if (e.key === 'Enter'){
        e.preventDefault();
        var v = (input.value || '').trim();
        if (v !== '') input.value = v.charAt(0).toUpperCase() + v.slice(1);
        if (list) list.style.display = 'none';
        populateCaps();
      }
    });
  }

  if (provinciaSelect){
    provinciaSelect.addEventListener('change', function(){
      var selectedLi = list ? list.querySelector('[data-selected="true"]') : null;
      if (selectedLi && selectedLi.getAttribute('data-provincia') !== provinciaSelect.value){
        selectedLi.removeAttribute('data-selected');
        if (input) input.value = '';
      }
      filterList({ ignoreText: true });
      resetCap();
    });
  }

  if (list){
    var items = list.children;
    for (var i=0;i<items.length;i++){
      (function(li){
        li.addEventListener('click', function(){
          var t = (li.textContent || '').trim();
          if (input){ input.value = t.charAt(0).toUpperCase() + t.slice(1); }
          var all = list.children;
          for (var k=0;k<all.length;k++){ all[k].removeAttribute('data-selected'); }
          li.setAttribute('data-selected','true');
          list.style.display = 'none';
          populateCaps();
        });
      })(items[i]);
    }
  }

  document.addEventListener('click', function(e){
    var wrapper = document.querySelector('.position-relative');
    if (!wrapper){ if (list) list.style.display = 'none'; return; }
    if (!wrapper.contains(e.target) && list) list.style.display = 'none';
  });

  // init
  filterList();
  if (list) list.style.display = 'none';
  populateCaps(); // applica OLD_CAP se coerente con i valori iniziali
});
</script>
@endpush
