{{-- resources/views/riepilogo_costi/index.blade.php --}}
@extends('layouts.app')

@php
$user = Auth::user();
@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Riepilogo Costi − Anno {{ $anno }}
  </h1>

  {{-- Filtri --}}
  <div class="row g-3 align-items-end mb-3">
    @if($isElevato)
    <div class="col-md-4">
      <label for="assocSelect" class="form-label">Associazione</label>
      <select id="assocSelect" class="form-select">
        <option value="">— seleziona —</option>

        @foreach($associazioni as $assoc)
        <option value="{{ $assoc->idAssociazione }}"
          {{ (int)$assoc->idAssociazione === (int)$selectedAssoc ? 'selected' : '' }}>
          {{ $assoc->Associazione }}
        </option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label for="convSelect" class="form-label">Convenzione</label>
      <select id="convSelect" class="form-select" {{ $selectedAssoc ? '' : 'disabled' }}>
        <option value="">— seleziona —</option>
      </select>
    </div>
    @else
    <input type="hidden" id="assocSelect" value="{{ $selectedAssoc }}">
    <div class="col-md-6">
      <label for="convSelect" class="form-label">Convenzione</label>
      <select id="convSelect" class="form-select">
        <option value="TOT">TOTALE</option>
        @foreach($convenzioni as $c)
        <option value="{{ $c->idConvenzione }}"
          {{ (string)$selectedConv === (string)$c->idConvenzione ? 'selected' : '' }}>
          {{ $c->Convenzione }}
        </option>
        @endforeach
      </select>
    </div>
    @endif
  </div>

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna voce presente per l’anno {{ $anno }}.
  </div>

  @php
  // Tipologie di riepilogo (fisse)
  $sezioni = [
  2 => 'Automezzi',
  3 => 'Attrezzatura Sanitaria',
  4 => 'Telecomunicazioni',
  5 => 'Costi gestione struttura',
  6 => 'Costo del personale',
  7 => 'Materiale sanitario di consumo',
  8 => 'Costi amministrativi',
  9 => 'Quote di ammortamento',
  10 => 'Beni Strumentali inferiori a 516,00 euro',
  11 => 'Altri costi'
  ];
  @endphp

  <div class="accordion" id="accordionRiep">
    @foreach ($sezioni as $id => $titolo)
    <div class="accordion-item mb-2">
      <h2 class="accordion-header" id="heading-{{ $id }}">
        <button class="accordion-button collapsed" type="button"
          data-bs-toggle="collapse"
          data-bs-target="#collapse-{{ $id }}"
          aria-expanded="false"
          aria-controls="collapse-{{ $id }}">
          <div class="row w-100 text-start gx-2">
            <div class="col-5 fw-bold">{{ $id-1 }} - {{ $titolo }}</div>
            <div class="col-2" id="summary-prev-{{ $id }}">Preventivo: -</div>
            <div class="col-2" id="summary-cons-{{ $id }}">Consuntivo: -</div>
            <div class="col-2" id="summary-scos-{{ $id }}">Scostamento: -</div>
          </div>
        </button>
      </h2>
      <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionRiep">
        <div class="accordion-body">

          {{-- Barra azioni sezione (Modifica Preventivi) --}}
          <div class="js-bulk-bar d-flex justify-content-end mb-2 d-none">
            <a class="js-edit-prev btn btn-sm btn-anpas-green p-2 me-2"
              data-sezione="{{ $id }}"
              href="#"
              title="Modifica tutti i preventivi della sezione">
              <i class="fas fa-pen"></i> Modifica Preventivi
            </a>
          </div>

          <table id="table-sezione-{{ $id }}"
            class="table table-hover table-striped-anpas table-bordered w-100 mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>Voce</th>
                <th class="text-end" style="width:160px">Preventivo</th>
                <th class="text-end" style="width:160px">Consuntivo</th>
                <th class="text-end" style="width:140px">% Scostamento</th>
                <th class="text-center" style="width:120px">Azioni</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
    @endforeach
    {{-- ============================= --}}
    {{-- SEZIONE MEZZI SOSTITUTIVI --}}
    {{-- ============================= --}}
    <div class="accordion-item mb-2 d-none" id="accordion-mezzi-sostitutivi">
      <h2 class="accordion-header" id="heading-sost">
        <button class="accordion-button collapsed" type="button"
          data-bs-toggle="collapse"
          data-bs-target="#collapse-sost"
          aria-expanded="false"
          aria-controls="collapse-sost">
          <div class="row w-100 text-start gx-2">
            <div class="col-5 fw-bold">11 - Mezzi sostitutivi</div>
            <div class="col-2" id="summary-prev-sost">Massimale mezzi sostitutivi: -</div>
            <div class="col-2" id="summary-cons-sost">Costo netto sostitutivi: -</div>
            <div class="col-2" id="summary-scos-sost">Massimale meno Costo: -</div>
          </div>
        </button>
      </h2>
      <div id="collapse-sost" class="accordion-collapse collapse" data-bs-parent="#accordionRiep">
        <div class="accordion-body">

          {{-- Barra azioni sezione --}}
          <div class="js-bulk-bar-sost d-flex justify-content-end mb-2 d-none">
            <a class="js-edit-costi-sost btn btn-sm btn-anpas-green p-2 me-2"
              href="#"
              title="Modifica costi orari">
              <i class="fas fa-pen"></i> Modifica costi orari
            </a>
          </div>

          <table id="table-mezzi-sostitutivi"
            class="table table-hover table-striped-anpas table-bordered w-100 mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>Convenzione</th>
                <th class="text-end" style="width:180px">Massimale mezzi sostitutivi (€)</th>
                <th class="text-end" style="width:180px">Costo mezzi sostitutivi (€)</th>
                <th class="text-end" style="width:180px">Massimale meno Costo (€)</th>
                <th class="text-center" style="width:100px">Azioni</th>
              </tr>
            </thead>
            <tbody>
              {{-- riempito via JS --}}
            </tbody>
          </table>

        </div>
      </div>
    </div>


    <div class="accordion-item mt-4">
      <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
        <div class="row w-100 text-start gx-2">
          <div class="col-5">Totale generale</div>
          <div class="col-2" id="tot-prev">€0,00</div>
          <div class="col-2" id="tot-cons">€0,00</div>
          <div class="col-2" id="tot-scos">0%</div>
        </div>
      </div>
    </div>

    <div class="accordion-item mt-2 d-none" id="totale-netto-sost-wrapper">
      <div class="accordion-header bg-light text-dark fw-bold py-3 px-4 border rounded">
        <div class="row w-100 text-start gx-2">
          <div class="col-5">Totale Generale al netto dei mezzi sostitutivi</div>
          <div class="col-2" id="tot-prev-netto-sost">€0,00</div>
          <div class="col-2" id="tot-cons-netto-sost">€0,00</div>
          <div class="col-2" id="tot-scos-netto-sost">0%</div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
@push('scripts')
<script>
  const ID_TEL_FISSA  = 5010;
  const ID_TEL_MOBILE = 5011;

  (function () {
    const $loader = $('#pageLoader');
    const show = () => $loader.stop(true, true).fadeIn(120).attr({'aria-hidden':'false','aria-busy':'true'});
    const hide = () => $loader.stop(true, true).fadeOut(120).attr({'aria-hidden':'true','aria-busy':'false'});

    // Loader globale per $.ajax (se ti resta qualche chiamata jquery)
    $(document).ajaxStart(show);
    $(document).ajaxStop(hide);

    window.AnpasLoader = { show, hide };

    const csrfToken = (document.head.querySelector('meta[name="csrf-token"]')?.content) || '';
    const isElevato = @json($isElevato);
    const anno = @json($anno);
    const selectedAssocServer = @json((int)($selectedAssoc ?? 0));
    const selectedConvServer  = @json($selectedConv ?? 'TOT');

    const $assoc = document.getElementById('assocSelect');
    const $conv  = document.getElementById('convSelect');

    // se NON elevato, blocco la select associazione
    if (!isElevato && $assoc) $assoc.setAttribute('disabled', 'disabled');

    // ===== Loader wrapper per fetch (anti flicker + anti doppio hide) =====
    let __loaderDepth = 0;
    let __activeControllers = [];
    let __loaderTimer = null;
    let __loaderShown = false;

    function _loaderMaybeShow() {
      // evita flash: mostra solo se dura un minimo
      if (__loaderShown) return;
      if (__loaderTimer) return;
      __loaderTimer = setTimeout(function () {
        __loaderTimer = null;
        if (__loaderDepth > 0 && !__loaderShown) {
          __loaderShown = true;
          AnpasLoader.show();
        }
      }, 120);
    }

    function _loaderMaybeHide() {
      if (__loaderDepth !== 0) return;
      if (__loaderTimer) { clearTimeout(__loaderTimer); __loaderTimer = null; }
      if (__loaderShown) {
        __loaderShown = false;
        AnpasLoader.hide();
      } else {
        // se non è mai stato mostrato, assicurati sia nascosto
        AnpasLoader.hide();
      }
    }

    function abortAllFetches() {
      __activeControllers.forEach(c => { try { c.abort(); } catch (e) {} });
      __activeControllers = [];
      __loaderDepth = 0;
      _loaderMaybeHide();
    }

    let __ctxVersion = 0;
    function bumpContext() { __ctxVersion++; }

    function closeAllAccordions() {
      document.querySelectorAll('#accordionRiep .accordion-collapse.show').forEach(el => {
        try {
          const c = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
          c.hide();
        } catch (e) {
          el.classList.remove('show');
        }
      });
    }

    async function fetchWithLoader(url, options = {}) {
      const ctrl = new AbortController();
      __activeControllers.push(ctrl);

      __loaderDepth++;
      _loaderMaybeShow();

      try {
        const res = await fetch(url, Object.assign({}, options, { signal: ctrl.signal }));
        if (!res.ok) {
          const t = await res.text().catch(() => '');
          throw new Error('HTTP ' + res.status + ': ' + (t || res.statusText));
        }
        return res;
      } finally {
        __activeControllers = __activeControllers.filter(x => x !== ctrl);
        __loaderDepth = Math.max(0, __loaderDepth - 1);
        _loaderMaybeHide();
      }
    }

    async function fetchJsonWithLoader(url, options = {}) {
      const res = await fetchWithLoader(url, options);
      return await res.json();
    }

    // Totali per "netto" sostitutivi
    let __totPrevGenerale = 0;
    let __totConsGenerale = 0;
    let __costoFasciaSost = 0;
    let __costoMezziSost  = 0;

    // ===== utils =====
    const eur  = v => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v || 0));
    const pct  = v => `${(Number(v)||0).toFixed(2)}%`;
    const norm = s => String(s || '').trim().replace(/\s+/g, ' ').toUpperCase();

    function toNum(v) {
      if (typeof v === 'number') return v;
      const s = String(v ?? '').trim();
      if (!s) return 0;
      if (/,\d{1,2}$/.test(s)) return parseFloat(s.replace(/\./g, '').replace(',', '.')) || 0;
      return parseFloat(s.replace(/,/g, '')) || 0;
    }

    function isTelefoniaRow(row) {
      const d = norm(row?.descrizione);
      return d === 'UTENZE TELEFONICHE' || row?.meta?.telefonia === true;
    }

    function isMergedRow(row) {
      if (!row) return false;
      if (row?.meta?.merged === true) return true;
      const idv = row.idVoceConfig;
      return typeof idv === 'string' && /_MERGE$/.test(idv);
    }

    function currentAssociazione() {
      let v = ($assoc?.value || '').trim();
      if (!v) v = String(selectedAssocServer || '');
      return v;
    }

    // ===== Box "Totale generale al netto dei mezzi sostitutivi" =====
    function updateTotaleNettoSostitutiviRow() {
      const wrap   = document.getElementById('totale-netto-sost-wrapper');
      const outPrev = document.getElementById('tot-prev-netto-sost');
      const outCons = document.getElementById('tot-cons-netto-sost');
      const outScos = document.getElementById('tot-scos-netto-sost');
      if (!wrap || !outPrev || !outCons || !outScos) return;

      const showNetto = (__costoMezziSost > __costoFasciaSost);
      if (!showNetto) {
        wrap.classList.add('d-none');
        outPrev.textContent = '€0,00';
        outCons.textContent = '€0,00';
        outScos.textContent = '0%';
        return;
      }

      const diffSost = Math.max(0, __costoMezziSost - __costoFasciaSost);

      const prevNet = Math.max(0, __totPrevGenerale);
      const consNet = Math.max(0, __totConsGenerale - diffSost);
      const scosNet = prevNet !== 0 ? ((consNet - prevNet) / prevNet * 100) : 0;

      outPrev.textContent = eur(prevNet);
      outCons.textContent = eur(consNet);
      outScos.textContent = `${scosNet.toFixed(2)}%`;
      wrap.classList.remove('d-none');
    }

    // ===== Totali subito (header sezioni + totale generale) =====
    async function loadSummaryTotals() {
      const myCtx = __ctxVersion;

      const ass  = currentAssociazione();
      const conv = ($conv?.value || '').trim() || 'TOT';
      if (!ass) return;

      const url = `{{ route('riepilogo.costi.summary') }}`;
      const qs  = new URLSearchParams({ idAssociazione: ass, idConvenzione: conv });

      let json;
      try {
        json = await fetchJsonWithLoader(`${url}?${qs.toString()}`);
      } catch (e) {
        if (e && (e.name === 'AbortError' || String(e.message || '').toLowerCase().includes('aborted'))) return;
        console.error('summary failed', e);
        return;
      }

      if (myCtx !== __ctxVersion) return;
      if (!json?.ok) return;

      Object.entries(json.sezioni || {}).forEach(([tip, v]) => {
        const id = Number(tip);
        const sp = document.getElementById(`summary-prev-${id}`);
        const sc = document.getElementById(`summary-cons-${id}`);
        const ss = document.getElementById(`summary-scos-${id}`);
        if (sp) sp.textContent = 'Preventivo: ' + eur(v.preventivo);
        if (sc) sc.textContent = 'Consuntivo: ' + eur(v.consuntivo);
        if (ss) ss.textContent = 'Scostamento: ' + pct(v.scostamento);
      });

      document.getElementById('tot-prev').textContent = eur(json.totale.preventivo);
      document.getElementById('tot-cons').textContent = eur(json.totale.consuntivo);
      document.getElementById('tot-scos').textContent = pct(json.totale.scostamento);

      __totPrevGenerale = Number(json.totale.preventivo || 0);
      __totConsGenerale = Number(json.totale.consuntivo || 0);
      updateTotaleNettoSostitutiviRow();
    }

    // =========================
    // LAZY LOADING SEZIONI 2..11
    // =========================
    const IDS_SEZIONI = [2,3,4,5,6,7,8,9,10,11];
    const sezCache = new Map();

    function updateBulkButtonsVisibility() {
      const convVal = ($conv?.value || '').trim();
      const showBar = !!convVal && convVal !== 'TOT';
      document.querySelectorAll('.js-bulk-bar').forEach(el => el.classList.toggle('d-none', !showBar));
    }

    function clearAllTablesAndSummaries() {
      IDS_SEZIONI.forEach(id => {
        const tbody = document.querySelector(`#table-sezione-${id} tbody`);
        if (tbody) tbody.innerHTML = '';

        const sp = document.getElementById(`summary-prev-${id}`);
        const sc = document.getElementById(`summary-cons-${id}`);
        const ss = document.getElementById(`summary-scos-${id}`);
        if (sp) sp.textContent = 'Preventivo: -';
        if (sc) sc.textContent = 'Consuntivo: -';
        if (ss) ss.textContent = 'Scostamento: -';
      });

      document.getElementById('tot-prev').textContent = '€0,00';
      document.getElementById('tot-cons').textContent = '€0,00';
      document.getElementById('tot-scos').textContent = '0%';

      __totPrevGenerale = 0;
      __totConsGenerale = 0;
      updateTotaleNettoSostitutiviRow();

      document.getElementById('noDataMessage')?.classList.add('d-none');
      updateBulkButtonsVisibility();
    }

    function resetContextAndUI() {
      closeAllAccordions();
      abortAllFetches();
      bumpContext();
      sezCache.clear();
      clearAllTablesAndSummaries();
    }

    function softResetForConvenzioneChange() {
      // vuoi chiudere accordion ma senza flash sui summary: ok
      closeAllAccordions();
      abortAllFetches();
      bumpContext();

      // svuota solo i tbody (summary li aggiorna summaryTotals)
      IDS_SEZIONI.forEach(id => {
        const tbody = document.querySelector(`#table-sezione-${id} tbody`);
        if (tbody) tbody.innerHTML = '';
      });

      document.getElementById('noDataMessage')?.classList.add('d-none');
      sezCache.clear();
    }

    async function loadSezione(idTipologia) {
      const myCtx = __ctxVersion;

      const ass  = currentAssociazione();
      const conv = ($conv?.value || '').trim();

      const url = `{{ route('riepilogo.costi.sezione', ['idTipologia' => '__ID__']) }}`.replace('__ID__', idTipologia);
      const params = new URLSearchParams({ idAssociazione: ass, idConvenzione: conv });

      let json;
      try {
        json = await fetchJsonWithLoader(`${url}?${params.toString()}`);
      } catch (e) {
        if (e && (e.name === 'AbortError' || String(e.message || '').toLowerCase().includes('aborted'))) {
          return { prev: 0, cons: 0 };
        }
        console.error('loadSezione failed', idTipologia, e);
        return { prev: 0, cons: 0 };
      }

      if (myCtx !== __ctxVersion) return { prev: 0, cons: 0 };

      const data = json?.data || [];
      const dataMerged = mergeCarburanteAdditivi(data, idTipologia);

      const tbody = document.querySelector(`#table-sezione-${idTipologia} tbody`);
      if (!tbody) return { prev: 0, cons: 0 };

      tbody.innerHTML = '';
      let sumPrev = 0, sumCons = 0;

      dataMerged.forEach(row => {
        const prev = toNum(row?.preventivo);
        const cons = toNum(row?.consuntivo);
        sumPrev += prev;
        sumCons += cons;

        const editingEnabled = !!conv && conv !== 'TOT';
        const merged = isMergedRow(row);

        let actionsHtml = '—';

        if (editingEnabled) {
          if (row?.meta?.carburante_additivi) {
            const parts = Array.isArray(row?.meta?.of) ? row.meta.of.filter(Boolean) : [];
            if (parts.length >= 2) {
              const editUrl = `{{ route('riepilogo.costi.edit.carburante_additivi') }}`;
              const qs = new URLSearchParams({
                idAssociazione: ass,
                idConvenzione: conv,
                idCarb: parts[0],
                idAdd: parts[1]
              }).toString();

              actionsHtml = `<a class="btn btn-warning btn-icon" href="${editUrl}?${qs}" title="Modifica carburanti e additivi"><i class="fas fa-edit"></i></a>`;
            }
          } else if (isTelefoniaRow(row)) {
            const editTelUrl = `{{ route('riepilogo.costi.edit.telefonia') }}`;
            const qs = new URLSearchParams({
              idAssociazione: ass,
              idConvenzione: conv,
              idFissa: ID_TEL_FISSA,
              idMobile: ID_TEL_MOBILE
            }).toString();

            actionsHtml = `<a class="btn btn-warning btn-icon" href="${editTelUrl}?${qs}" title="Modifica utenze telefoniche"><i class="fas fa-edit"></i></a>`;
          } else if (merged) {
            const parts =
              Array.isArray(row?.merged_of) ? row.merged_of.filter(Boolean) :
              Array.isArray(row?.meta?.of) ? row.meta.of.filter(Boolean) : [];

            if (parts.length >= 2) {
              const editMergeUrl = `{{ route('riepilogo.costi.edit.formazione') }}`;
              const qs = new URLSearchParams({
                idAssociazione: ass,
                idConvenzione: conv,
                idA: parts[0],
                idB: parts[1]
              }).toString();

              actionsHtml = `<a class="btn btn-warning btn-icon" href="${editMergeUrl}?${qs}" title="Modifica formazione (A + DAE + RDAE)"><i class="fas fa-edit"></i></a>`;
            }
          } else {
            const ensureUrl = `{{ route('riepilogo.costi.ensureEdit') }}`;
            const qs = new URLSearchParams({
              idAssociazione: ass,
              idConvenzione: conv,
              idVoceConfig: row.idVoceConfig
            }).toString();

            actionsHtml = `<a class="btn btn-warning btn-icon" href="${ensureUrl}?${qs}" title="Modifica"><i class="fas fa-edit"></i></a>`;
          }
        }

        const tr = document.createElement('tr');
        tr.setAttribute('title', 'Voce: ' + (row?.descrizione ?? '-') + '\n');
        tr.innerHTML = `
          <td>${row?.descrizione ?? ''}</td>
          <td class="text-end">${eur(prev)}</td>
          <td class="text-end">${eur(cons)}</td>
          <td class="text-end">${row?.scostamento ?? '0%'}</td>
          <td class="text-center">${actionsHtml}</td>`;
        tbody.appendChild(tr);
      });

      const scos = sumPrev !== 0 ? ((sumCons - sumPrev) / sumPrev * 100) : 0;
      document.getElementById(`summary-prev-${idTipologia}`).textContent = 'Preventivo: ' + eur(sumPrev);
      document.getElementById(`summary-cons-${idTipologia}`).textContent = 'Consuntivo: ' + eur(sumCons);
      document.getElementById(`summary-scos-${idTipologia}`).textContent = 'Scostamento: ' + pct(scos);

      return { prev: sumPrev, cons: sumCons };
    }

    async function loadSezioneLazy(idTipologia) {
      if (sezCache.has(idTipologia)) return sezCache.get(idTipologia);

      const s = await loadSezione(idTipologia);
      sezCache.set(idTipologia, Object.assign({}, s, { loaded: true }));
      return s;
    }

    function bindAccordionLazyLoad() {
      document.querySelectorAll('#accordionRiep .accordion-collapse').forEach(el => {
        el.addEventListener('show.bs.collapse', function() {
          const id = String(this.id || '');
          if (!id.startsWith('collapse-')) return;
          const n = parseInt(id.replace('collapse-', ''), 10);
          if (!Number.isFinite(n)) return;
          if (n < 2 || n > 11) return;
          loadSezioneLazy(n);
        });
      });
    }

    // =========================
    // MEZZI SOSTITUTIVI
    // =========================
    async function loadSezioneMezziSostitutivi() {
      const ass  = currentAssociazione();
      const conv = ($conv?.value || '').trim();

      const section = document.getElementById('accordion-mezzi-sostitutivi');
      if (!section) return;

      if (!conv || conv === 'TOT') {
        section.classList.add('d-none');
        __costoFasciaSost = 0;
        __costoMezziSost  = 0;
        updateTotaleNettoSostitutiviRow();
        return;
      }

      const url  = `/ajax/rot-sost/stato?idAssociazione=${ass}&idConvenzione=${conv}&anno=${anno}`;
      const data = await fetchJsonWithLoader(url).catch(() => ({}));

      section.classList.add('d-none');
      const tbody = document.querySelector('#table-mezzi-sostitutivi tbody');
      if (tbody) tbody.innerHTML = '';

      if (!data?.ok || data.modalita !== 'sostitutivi') {
        __costoFasciaSost = 0;
        __costoMezziSost  = 0;
        updateTotaleNettoSostitutiviRow();
        return;
      }

      section.classList.remove('d-none');

      const costoFascia = Number(data.costo_fascia_oraria || 0);
      const costoSost   = Number(data.costo_mezzi_sostitutivi || 0);
      const totaleNetto = costoFascia - costoSost;

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${data.convenzione ?? 'Convenzione selezionata'}</td>
        <td class="text-end">${eur(costoFascia)}</td>
        <td class="text-end">${eur(costoSost)}</td>
        <td class="text-end fw-bold">${eur(totaleNetto)}</td>
        <td class="text-center">
          <a href="/mezzi-sostitutivi/${conv}/edit" class="btn btn-warning btn-icon" title="Modifica costi orari">
            <i class="fas fa-edit"></i>
          </a>
        </td>`;
      tbody.appendChild(row);

      document.getElementById('summary-prev-sost').textContent = 'Massimale mezzi sostitutivi: ' + eur(costoFascia);
      document.getElementById('summary-cons-sost').textContent = 'Costo mezzi sostitutivi: ' + eur(costoSost);
      document.getElementById('summary-scos-sost').textContent = 'Massimale meno Costo: ' + eur(totaleNetto);

      __costoFasciaSost = costoFascia;
      __costoMezziSost  = costoSost;
      updateTotaleNettoSostitutiviRow();
    }

    // =========================
    // CARICA CONVENZIONI
    // =========================
    async function loadConvenzioniForAss(assId, preselect = 'TOT') {
      if (!$conv) return;

      $conv.innerHTML = '';
      if (!assId) {
        $conv.setAttribute('disabled', 'disabled');
        resetContextAndUI();
        return;
      }
      $conv.removeAttribute('disabled');

      const optTot = document.createElement('option');
      optTot.value = 'TOT';
      optTot.textContent = 'TOTALE';
      $conv.appendChild(optTot);

      const items = await fetchJsonWithLoader(`/ajax/convenzioni-by-associazione/${assId}?anno=${anno}`).catch(() => []);

      (items || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.text;
        $conv.appendChild(opt);
      });

      $conv.value = preselect ?? 'TOT';

      // qui NON faccio reset: lo fa il change assoc prima, e init quando serve
      await loadSezioneMezziSostitutivi();
      if (window.__reloadRotSostBox) window.__reloadRotSostBox();
      await loadSummaryTotals();
      updateBulkButtonsVisibility();
      updateTotaleNettoSostitutiviRow();
    }

    // =========================
    // EVENTI SELECT
    // =========================
    $assoc?.addEventListener('change', async function () {
      const assId = this.value || '';

      // cambio associazione = reset completo (chiude, pulisce summary, ecc)
      resetContextAndUI();

      @if(Route::has('sessione.setAssociazione'))
      await fetchWithLoader("{{ route('sessione.setAssociazione') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ idAssociazione: assId })
      }).catch(() => {});
      @endif

      await loadConvenzioniForAss(assId, 'TOT');
    });

    $conv?.addEventListener('change', async function () {
      const val = this.value;

      // cambio convenzione = chiude accordion + abort + bump + svuota tbody (no flash summary)
      softResetForConvenzioneChange();

      @if(Route::has('sessione.setConvenzione'))
      await fetchWithLoader("{{ route('sessione.setConvenzione') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ idConvenzione: val })
      }).catch(() => {});
      @endif

      await loadSezioneMezziSostitutivi();
      if (window.__reloadRotSostBox) window.__reloadRotSostBox();

      await loadSummaryTotals();
      updateBulkButtonsVisibility();
      updateTotaleNettoSostitutiviRow();
    });

    // =========================
    // CLICK "Modifica Preventivi sezione"
    // =========================
    document.addEventListener('click', function(e) {
      const el = e.target.closest('.js-edit-prev');
      if (!el) return;
      e.preventDefault();

      const sezione = el.dataset.sezione;
      const ass  = document.getElementById('assocSelect')?.value || @json((string)($selectedAssoc ?? ''));
      const conv = document.getElementById('convSelect')?.value || '';

      if (!conv || conv === 'TOT') {
        alert('Seleziona una convenzione specifica per modificare i preventivi della sezione.');
        return;
      }

      const url = new URL(
        "{{ route('riepilogo.costi.editPreventiviSezione', ['sezione' => '__S__']) }}".replace('__S__', sezione),
        window.location.origin
      );
      url.searchParams.set('idAssociazione', ass);
      url.searchParams.set('idConvenzione', conv);
      window.location = url.toString();
    });

    // =========================
    // INIT
    // =========================
    (async () => {
      bindAccordionLazyLoad();

      if (isElevato) {
        const preSelAss = ($assoc?.value || '').trim() || String(selectedAssocServer || '');
        if (preSelAss) {
          // init: reset completo per partire puliti
          resetContextAndUI();
          await loadConvenzioniForAss(preSelAss, selectedConvServer || 'TOT');
        } else {
          resetContextAndUI();
        }
      } else {
        resetContextAndUI();

        if ($conv && selectedConvServer && selectedConvServer !== 'TOT') {
          $conv.value = String(selectedConvServer);
        }

        await loadSezioneMezziSostitutivi();
        if (window.__reloadRotSostBox) window.__reloadRotSostBox();
        await loadSummaryTotals();
      }

      updateBulkButtonsVisibility();
    })();

    // ===== MERGE carburanti+additivi =====
    function mergeCarburanteAdditivi(rows, idTipologia) {
      const filtered = [];
      let firstPos = null;
      let sumPrev = 0, sumCons = 0;

      let idCarb = null;
      let idAdd  = null;

      function isCarb(desc) { return desc.indexOf('CARBURANT') !== -1; }
      function isAdd(desc)  { return desc.indexOf('ADDITIV') !== -1; }

      (rows || []).forEach(r => {
        const d = norm(r?.descrizione);
        const hitCarb = isCarb(d);
        const hitAdd  = isAdd(d);

        if (hitCarb || hitAdd) {
          if (firstPos === null) firstPos = filtered.length;

          sumPrev += toNum(r?.preventivo);
          sumCons += toNum(r?.consuntivo);

          if (hitCarb && !idCarb) idCarb = r.idVoceConfig;
          if (hitAdd  && !idAdd)  idAdd  = r.idVoceConfig;

          return;
        }

        filtered.push(r);
      });

      if (firstPos === null || !idCarb || !idAdd) return rows;

      const scost = sumPrev !== 0 ? ((sumCons - sumPrev) / sumPrev * 100) : 0;

      const mergedRow = {
        idVoceConfig: 'CARB_ADD_MERGE',
        descrizione: 'carburanti e additivi',
        preventivo: sumPrev,
        consuntivo: sumCons,
        scostamento: `${scost.toFixed(2)}%`,
        meta: { merged: true, carburante_additivi: true, of: [idCarb, idAdd] }
      };

      filtered.splice(firstPos, 0, mergedRow);
      return filtered;
    }

  })();
</script>
@endpush
