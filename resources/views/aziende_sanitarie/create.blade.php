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
            <button class="nav-link disabled" id="tab-conv" data-bs-toggle="tab" data-bs-target="#pane-conv" type="button" role="tab" disabled aria-disabled="true">
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

            {{-- Indirizzo (Provincia + Città + CAP SOLO qui) --}}
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="provincia" class="form-label">Provincia</label>
                <select class="form-control" id="provincia" name="provincia">
                  <option value="" disabled {{ old('provincia') ? '' : 'selected' }}>-- Seleziona provincia --</option>
                  @php
                    $allowedProvinces = ['VC','AL','AT','BI','CN','NO','TO','VB'];
                    $provinceUniche = collect($cities)->pluck('sigla_provincia')->unique()
                      ->filter(fn($sigla) => in_array($sigla, $allowedProvinces))->sort()->values();
                  @endphp
                  @foreach($provinceUniche as $sigla)
                    <option value="{{ $sigla }}" {{ old('provincia') == $sigla ? 'selected' : '' }}>
                      {{ $sigla }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-4 mb-3">
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
                          {{ old('citta') == trim($city->denominazione_ita) ? 'data-selected=true' : '' }}>
                        {{ ucfirst(trim($city->denominazione_ita)) }}
                      </li>
                    @endforeach
                  </ul>
                </div>
              </div>

              {{-- NEW: CAP --}}
              <div class="col-md-4 mb-3">
                <label for="cap_select" class="form-label">CAP</label>
                <select id="cap_select" name="cap" class="form-select" {{ old('citta') ? '' : 'disabled' }}>
                  <option value="">-- Seleziona CAP --</option>
                </select>
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

            <div class="d-flex justify-content-center myborder-button">
              <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">
                <i class="fas fa-times me-1"></i> Annulla
              </a>
              <button type="button" class="btn btn-anpas-green" id="goToLotti">
                <i class="fas fa-check me-1"></i> Avanti
              </button>
            </div>
          </div>

          {{-- PANE 2: LOTTI --}}
          <div class="tab-pane fade" id="pane-lotti" role="tabpanel" aria-labelledby="tab-lotti">
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
                Se imposti “No”, non inserirai lotti: useremo internamente <b>“LOTTI NON PRESENTI”</b> e potrai comunque creare convenzioni.
              </small>
            </div>

            <div id="lottiEditor">
              <div class="card-header bg-anpas-primary">
                <label class="form-label">Inserire qui di seguito i lotti per l’azienda</label>
              </div>

              <div class="card-body bg-anpas-white p-0">
                <div class="d-flex p-3 border-bottom align-items-start flex-nowrap gap-2 overflow-auto">
                  <input type="text" id="newLottoNome" class="form-control me-2 flex-shrink-0" style="width:280px" placeholder="Nome lotto">
                  <input type="text" id="newLottoDesc" class="form-control me-2 flex-shrink-0" style="width:420px" placeholder="Descrizione (opzionale)">
                  <button type="button" id="addLottoBtn" class="btn btn-anpas-green flex-shrink-0">
                    <i class="fas fa-plus me-1"></i> Aggiungi
                  </button>
                </div>
              </div>

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

            <div class="d-flex justify-content-center mt-5 myborder-button">
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
              <div><b>Con lotti (Sì):</b> per ogni lotto verranno create convenzioni <code>&lt;Nome Azienda&gt; – &lt;Nome Lotto&gt;</code>.</div>
              <div class="mt-1"><b>Senza lotti (No):</b> convenzioni con il solo nome azienda.</div>
            </div>

            <table id="convTable" class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th style="width:70px">#</th>
                  <th>Convenzione (anteprima)</th>
                  <th>Associazioni da collegare</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>

            <div class="d-flex justify-content-between mt-3 myborder-button">
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
(function () {
  'use strict';
  try {
    // ===== Wizard refs
    var tabAnag  = document.getElementById('tab-anagrafica');
    var tabLotti = document.getElementById('tab-lotti');
    var tabConv  = document.getElementById('tab-conv');

    var btnGoLotti   = document.getElementById('goToLotti');
    var btnBackAnag  = document.getElementById('backToAnagrafica');
    var btnGoConv    = document.getElementById('goToConvenzioni');
    var btnBackLotti = document.getElementById('backToLotti');

    var nomeAziendaInput = document.getElementById('Nome');

    // Lotti
    var radioYes = document.getElementById('lottiYes');
    var radioNo  = document.getElementById('lottiNo');
    var lottiEditor = document.getElementById('lottiEditor');
    var tableBody   = document.querySelector('#lottiTable tbody');
    var addBtn      = document.getElementById('addLottoBtn');
    var inputNome   = document.getElementById('newLottoNome');
    var inputDesc   = document.getElementById('newLottoDesc');

    // Convenzioni
    var convTbody = document.querySelector('#convTable tbody');
    var ASSOCS = @json($associazioni->map(function($a){ return ['id'=>$a->idAssociazione,'text'=>$a->Associazione]; }));
    var OLD_CONV_ASSOC = @json(old('conv_assoc', []));

    function escapeHtml(s){
      s = (s || '').toString();
      return s.replace(/&/g,'&amp;')
              .replace(/</g,'&lt;')
              .replace(/>/g,'&gt;')
              .replace(/"/g,'&quot;')
              .replace(/'/g,'&#039;');
    }

    function setDisabledIn(container, disabled){
      if (!container) return;
      var nodes = container.querySelectorAll('input,select,textarea,button');
      for (var i=0;i<nodes.length;i++) nodes[i].disabled = !!disabled;
    }

    function getRows(){
      return Array.prototype.slice.call(tableBody ? tableBody.querySelectorAll('tr[data-row-index]') : []);
    }

    function getRealLottiCount(){
      var rows = getRows();
      var count = 0;
      for (var i=0;i<rows.length;i++){
        var tr = rows[i];
        var idx = parseInt(tr.getAttribute('data-row-index'), 10);
        if (isNaN(idx)) continue;
        var sel = 'input[name="lotti['+idx+'][nomeLotto]"]';
        var inp = tr.querySelector(sel);
        var v = inp ? inp.value : '';
        if (v && v.trim() !== '') count++;
      }
      return count;
    }

    var nextIndex = (function(){
      var m = -1;
      var rows = getRows();
      for (var i=0;i<rows.length;i++){
        var n = parseInt(rows[i].getAttribute('data-row-index'), 10);
        if (!isNaN(n) && n > m) m = n;
      }
      return m + 1;
    })();

    function renumberRows(){
      var trs = getRows();
      for (var i=0;i<trs.length;i++){
        var tr = trs[i];
        tr.setAttribute('data-row-index', i);
        var td = tr.querySelector('td');
        if (td) td.textContent = (i + 1);
        var nome = tr.querySelector('input[name^="lotti["][name$="[nomeLotto]"]');
        var desc = tr.querySelector('input[name^="lotti["][name$="[descrizione]"]');
        if (nome) nome.name = 'lotti['+i+'][nomeLotto]';
        if (desc) desc.name = 'lotti['+i+'][descrizione]';
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
        '<td class="text-muted">'+(i+1)+'</td>'+
        '<td><input type="text" name="lotti['+i+'][nomeLotto]" class="form-control" value="'+escapeHtml(nomeVal)+'"></td>'+
        '<td><input type="text" name="lotti['+i+'][descrizione]" class="form-control" value="'+escapeHtml(descVal)+'"></td>'+
        '<td class="text-center">'+
          '<button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">'+
            '<i class="fas fa-trash-alt"></i>'+
          '</button>'+
        '</td>';
      if (tableBody) tableBody.appendChild(tr);
      updateConvTabState();
    }

    if (addBtn){
      addBtn.addEventListener('click', function(){
        var n = (inputNome && inputNome.value) ? inputNome.value.trim() : '';
        var d = (inputDesc && inputDesc.value) ? inputDesc.value.trim() : '';
        if (!n) { if (inputNome) inputNome.focus(); return; }
        addRow(n,d);
        if (inputNome) inputNome.value = '';
        if (inputDesc) inputDesc.value = '';
      });
    }

    if (tableBody){
      tableBody.addEventListener('click', function(e){
        var t = e.target;
        var btn = t && t.closest ? t.closest('.js-remove-row') : null;
        if (!btn) return;
        var tr = btn.closest('tr');
        if (tr && tr.parentNode) tr.parentNode.removeChild(tr);
        renumberRows();
        updateConvTabState();
      });

      tableBody.addEventListener('input', function(e){
        var target = e.target || e.srcElement;
        if (!target || !target.name) return;
        if (/\[nomeLotto\]$/.test(target.name)) updateConvTabState();
      });
    }

    function isNoMode(){
      return !!(radioNo && radioNo.checked);
    }

    function applyLottiModeUI(){
      if (!lottiEditor) return;
      if (isNoMode()){
        lottiEditor.style.opacity = '0.5';
        lottiEditor.style.pointerEvents = 'none';
        setDisabledIn(lottiEditor, true);
      } else {
        lottiEditor.style.opacity = '1';
        lottiEditor.style.pointerEvents = 'auto';
        setDisabledIn(lottiEditor, false);
      }
    }

    if (radioYes) radioYes.addEventListener('change', function(){ applyLottiModeUI(); updateConvTabState(); });
    if (radioNo ) radioNo .addEventListener('change', function(){ applyLottiModeUI(); updateConvTabState(); });

    function activateTab(btnEl){
      if (!btnEl) return;
      try {
        if (window.bootstrap && bootstrap.Tab){
          new bootstrap.Tab(btnEl).show();
          return;
        }
      } catch(_){}
      // Fallback senza Bootstrap JS
      btnEl.click();
      var target = btnEl.getAttribute('data-bs-target');
      if (!target) return;
      var panes = document.querySelectorAll('.tab-pane');
      for (var i=0;i<panes.length;i++){
        panes[i].classList.remove('show');
        panes[i].classList.remove('active');
      }
      var pane = document.querySelector(target);
      if (pane){
        pane.classList.add('show');
        pane.classList.add('active');
      }
    }

    var goLottiBtn = document.getElementById('goToLotti');
    if (goLottiBtn){
      goLottiBtn.addEventListener('click', function(){
        if (!nomeAziendaInput || !(nomeAziendaInput.value || '').trim()){
          if (nomeAziendaInput) nomeAziendaInput.focus();
          return;
        }
        activateTab(tabLotti);
      });
    }

    var backToAnag = document.getElementById('backToAnagrafica');
    if (backToAnag){
      backToAnag.addEventListener('click', function(){ activateTab(tabAnag); });
    }

    var goConvBtn = document.getElementById('goToConvenzioni');
    if (goConvBtn){
      goConvBtn.addEventListener('click', function(){
        if (!nomeAziendaInput || !(nomeAziendaInput.value || '').trim()){
          if (nomeAziendaInput) nomeAziendaInput.focus();
          return;
        }
        if (!isNoMode() && getRealLottiCount() === 0){
          if (inputNome) inputNome.focus();
          return;
        }
        buildConvenzioniPreview();
        activateTab(tabConv);
      });
    }

    var backToLottiBtn = document.getElementById('backToLotti');
    if (backToLottiBtn){
      backToLottiBtn.addEventListener('click', function(){ activateTab(tabLotti); });
    }

    function buildConvenzioniPreview(){
      if (!convTbody) return;
      convTbody.innerHTML = '';
      var azienda = (nomeAziendaInput && nomeAziendaInput.value) ? nomeAziendaInput.value.trim() : '';

      if (isNoMode()){
        var selected0 = (OLD_CONV_ASSOC && OLD_CONV_ASSOC['0']) ? OLD_CONV_ASSOC['0'].map(String) : [];
        var row = document.createElement('tr');
        var opts = '';
        for (var i=0;i<ASSOCS.length;i++){
          var a = ASSOCS[i];
          var sel = selected0.indexOf(String(a.id)) !== -1 ? ' selected' : '';
          opts += '<option value="'+a.id+'"'+sel+'>'+escapeHtml(a.text)+'</option>';
        }
        row.innerHTML =
          '<td class="text-muted">1</td>'+
          '<td><input type="text" class="form-control" value="'+escapeHtml(azienda)+'" readonly>'+
          '<div class="form-text">Modalità senza lotti</div></td>'+
          '<td><select name="conv_assoc[0][]" class="form-select" multiple size="6">'+opts+'</select></td>';
        convTbody.appendChild(row);
        return;
      }

      var rows = getRows();
      var showRows = [];
      for (var r=0;r<rows.length;r++){
        var tr = rows[r];
        var idx = parseInt(tr.getAttribute('data-row-index'), 10);
        if (isNaN(idx)) continue;
        var nameInp = tr.querySelector('input[name="lotti['+idx+'][nomeLotto]"]');
        var v = nameInp ? nameInp.value : '';
        if (v && v.trim() !== '') showRows.push({tr: tr, idx: idx, nome: v});
      }

      for (var j=0;j<showRows.length;j++){
        var rec = showRows[j];
        var convName = azienda + ' - ' + rec.nome;
        var selected = (OLD_CONV_ASSOC && OLD_CONV_ASSOC[String(rec.idx)]) ? OLD_CONV_ASSOC[String(rec.idx)].map(String) : [];
        var opts2 = '';
        for (var k=0;k<ASSOCS.length;k++){
          var a2 = ASSOCS[k];
          var sel2 = selected.indexOf(String(a2.id)) !== -1 ? ' selected' : '';
          opts2 += '<option value="'+a2.id+'"'+sel2+'>'+escapeHtml(a2.text)+'</option>';
        }
        var rEl = document.createElement('tr');
        rEl.innerHTML =
          '<td class="text-muted">'+(j+1)+'</td>'+
          '<td><input type="text" class="form-control" value="'+escapeHtml(convName)+'" readonly></td>'+
          '<td><select name="conv_assoc['+rec.idx+'][]" class="form-select" multiple size="6">'+opts2+
          '</select><div class="form-text">Associazioni per questo lotto</div></td>';
        convTbody.appendChild(rEl);
      }
    }

    function updateConvTabState(){
      var enable = isNoMode() || getRealLottiCount() > 0;
      if (!tabConv) return;
      tabConv.disabled = !enable;
      if (enable) {
        tabConv.classList.remove('disabled');
        tabConv.setAttribute('aria-disabled', 'false');
      } else {
        tabConv.classList.add('disabled');
        tabConv.setAttribute('aria-disabled', 'true');
      }
    }

    if (tabConv){
      tabConv.addEventListener('show.bs.tab', function(e){
        if (!(isNoMode() || getRealLottiCount() > 0)){
          if (e && e.preventDefault) e.preventDefault();
          return;
        }
        buildConvenzioniPreview();
      });
    }

    applyLottiModeUI();
    updateConvTabState();
  } catch (err) {
    console.error('Errore init wizard azienda:', err);
  }
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
  var OLD_CAP   = @json(old('cap', ''));
  var CAPS_RAW  = @json($caps); // [{cap, denominazione_ita, sigla_provincia, ...}]

  // Build map { "PROV|città lower" : [cap,...] }
  var CAP_MAP = {};
  if (Array.isArray(CAPS_RAW)) {
    for (var i=0;i<CAPS_RAW.length;i++){
      var r = CAPS_RAW[i] || {};
      var key = String(r.sigla_provincia || '') + '|' + String(r.denominazione_ita || '').trim().toLowerCase();
      if (!CAP_MAP[key]) CAP_MAP[key] = [];
      CAP_MAP[key].push(String(r.cap));
    }
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
    for (var i=0;i<caps.length;i++){
      html += '<option value="'+caps[i]+'">'+caps[i]+'</option>';
    }
    capSelect.innerHTML = html;
    capSelect.disabled = caps.length === 0;
    if (OLD_CAP && caps.indexOf(String(OLD_CAP)) !== -1){
      capSelect.value = String(OLD_CAP);
    }
  }
  // ===== /CAP handling =====

  function filterList(){
    if (!list) return;
    var provincia = provinciaSelect ? provinciaSelect.value : '';
    var text = (input && input.value) ? input.value.toLowerCase() : '';
    if (!provincia){ list.style.display='none'; resetCap(); return; }

    var items = Array.prototype.slice.call(list.children || []);
    var hasVisible = false;
    for (var i=0;i<items.length;i++){
      var li = items[i];
      var matchProvincia = (li.getAttribute('data-provincia') === provincia);
      var matchText = (li.textContent || '').toLowerCase().indexOf(text) !== -1;
      li.style.display = (matchProvincia && matchText) ? 'block' : 'none';
      if (li.style.display === 'block') hasVisible = true;
    }
    list.style.display = hasVisible ? 'block' : 'none';

    var selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.style.display === 'none') {
      if (input) input.value = '';
      selectedLi.removeAttribute('data-selected');
      resetCap();
    }
  }

  if (input){
    input.addEventListener('focus', filterList);
    input.addEventListener('input',  function(){
      var items = Array.prototype.slice.call((list && list.children) || []);
      for (var i=0;i<items.length;i++){ items[i].removeAttribute('data-selected'); }
      filterList();
      resetCap();
    });
    input.addEventListener('keydown', function(e){
      if (e.key === 'Enter'){
        e.preventDefault();
        if (input.value.trim() !== '') {
          var v = input.value.trim();
          input.value = v.charAt(0).toUpperCase() + v.slice(1);
        }
        if (list) list.style.display = 'none';
        populateCaps(); // confermata la città -> popola CAP
      }
    });
  }

  if (provinciaSelect){
    provinciaSelect.addEventListener('change', function(){
      var selectedLi = list ? list.querySelector('[data-selected="true"]') : null;
      if (selectedLi && selectedLi.getAttribute('data-provincia') !== provinciaSelect.value) {
        selectedLi.removeAttribute('data-selected');
        if (input) input.value = '';
      }
      filterList();
      resetCap();
    });
  }

  if (list){
    var items = Array.prototype.slice.call(list.children || []);
    for (var i=0;i<items.length;i++){
      (function(li){
        li.addEventListener('click', function(){
          var txt = (li.textContent || '').trim();
          if (input){
            input.value = txt.charAt(0).toUpperCase() + txt.slice(1);
          }
          var items2 = Array.prototype.slice.call(list.children || []);
          for (var j=0;j<items2.length;j++){ items2[j].removeAttribute('data-selected'); }
          li.setAttribute('data-selected','true');
          list.style.display = 'none';
          populateCaps();
        });
      })(items[i]);
    }
  }

  document.addEventListener('click', function(e){
    if (!list) return;
    var pr = document.querySelector('.position-relative');
    if (!pr) { list.style.display = 'none'; return; }
    if (!pr.contains(e.target)) list.style.display = 'none';
  });

  // init
  filterList();
  if (list) list.style.display = 'none';
  populateCaps(); // se old() aveva già valori
});
</script>
@endpush
