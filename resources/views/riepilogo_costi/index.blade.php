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
            <div class="col-2" id="summary-prev-sost">Costo fascia oraria: -</div>
            <div class="col-2" id="summary-cons-sost">Costo netto sostitutivi: -</div>
            <div class="col-2" id="summary-scos-sost">Totale al netto: -</div>
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
                <th class="text-end" style="width:180px">Costo per fascia oraria (€)</th>
                <th class="text-end" style="width:180px">Costo mezzi sostitutivi (€)</th>
                <th class="text-end" style="width:180px">Totale al netto (€)</th>
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
  (function() {
    const $loader = $('#pageLoader');
    const show = () => $loader.stop(true, true).fadeIn(120).attr({
      'aria-hidden': 'false',
      'aria-busy': 'true'
    });
    const hide = () => $loader.stop(true, true).fadeOut(120).attr({
      'aria-hidden': 'true',
      'aria-busy': 'false'
    });

    // Loader globale per tutte le chiamate $.ajax
    $(document).ajaxStart(show);
    $(document).ajaxStop(hide);

    // Espongo per uso manuale (fetch)
    window.AnpasLoader = {
      show,
      hide
    };

    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
    const isElevato = @json($isElevato);
    const anno = @json($anno);
    const selectedAssocServer = @json((int)($selectedAssoc ?? 0));

    const $assoc = document.getElementById('assocSelect');
    const $conv = document.getElementById('convSelect');

    // se NON elevato, blocco la select associazione
    if (!isElevato && $assoc) $assoc.setAttribute('disabled', 'disabled');

    // Totali per "netto" sostitutivi
    let __totPrevGenerale = 0;
    let __totConsGenerale = 0;
    let __costoFasciaSost = 0;
    let __costoMezziSost = 0;

    // utils
    const eur = v => new Intl.NumberFormat('it-IT', {
      style: 'currency',
      currency: 'EUR'
    }).format(Number(v || 0));
    const pct = v => `${(Number(v)||0).toFixed(2)}%`;
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
      return typeof idv === 'string' && /_MERGE$/.test(idv); // es. TEL_MERGE, FORMAZIONE_MERGE
    }

    function currentAssociazione() {
      let v = ($assoc?.value || '').trim();
      if (!v) v = String(selectedAssocServer || '');
      return v;
    }

    // Box "Totale generale al netto dei mezzi sostitutivi"
    function updateTotaleNettoSostitutiviRow() {
      const wrap = document.getElementById('totale-netto-sost-wrapper');
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

      // differenza effettiva da sottrarre
      const diffSost = Math.max(0, __costoMezziSost - __costoFasciaSost);

      // totali netti
      const prevNet = Math.max(0, __totPrevGenerale);
      const consNet = Math.max(0, __totConsGenerale - diffSost);
      const scosNet = prevNet !== 0 ? ((consNet - prevNet) / prevNet * 100) : 0;

      outPrev.textContent = eur(prevNet);
      outCons.textContent = eur(consNet);
      outScos.textContent = `${scosNet.toFixed(2)}%`;
      wrap.classList.remove('d-none');
    }

    function loadConvenzioniForAss(assId, preselect = 'TOT') {
      if (!$conv) return;

      $conv.innerHTML = '';
      if (!assId) {
        $conv.setAttribute('disabled', 'disabled');
        return;
      }
      $conv.removeAttribute('disabled');

      const optTot = document.createElement('option');
      optTot.value = 'TOT';
      optTot.textContent = 'TOTALE';
      $conv.appendChild(optTot);

      fetch(`/ajax/convenzioni-by-associazione/${assId}?anno=${anno}`)
        .then(r => (r.ok ? r.json() : []))
        .then(items => {
          (items || []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.text;
            $conv.appendChild(opt);
          });
          $conv.value = preselect ?? 'TOT';

          (async () => {
            AnpasLoader.show();
            await reloadAllSections();
            await loadSezioneMezziSostitutivi();
            if (window.__reloadRotSostBox) window.__reloadRotSostBox();
          })();
        })
        .catch(() => {});
    }

    function loadSezione(idTipologia) {
      const ass = currentAssociazione();
      const conv = ($conv?.value || '').trim();

      const url = `{{ route('riepilogo.costi.sezione', ['idTipologia' => '__ID__']) }}`.replace('__ID__', idTipologia);
      const params = new URLSearchParams({
        idAssociazione: ass,
        idConvenzione: conv
      });

      return fetch(`${url}?${params}`)
        .then(r => r.json())
        .then(({
          data
        }) => {
          const tbody = document.querySelector(`#table-sezione-${idTipologia} tbody`);
          if (!tbody) return {
            prev: 0,
            cons: 0
          };

          tbody.innerHTML = '';
          let sumPrev = 0,
            sumCons = 0;

          (data || []).forEach(row => {
            const prev = toNum(row?.preventivo);
            const cons = toNum(row?.consuntivo);
            sumPrev += prev;
            sumCons += cons;

            const editingEnabled = !!conv && conv !== 'TOT';
            const merged = isMergedRow(row);

            let actionsHtml = '—';
            if (editingEnabled) {
              if (isTelefoniaRow(row)) {
                const editTelUrl = `{{ route('riepilogo.costi.edit.telefonia') }}`;
                const qs = new URLSearchParams({
                  idAssociazione: ass,
                  idConvenzione: conv,
                  idFissa: ID_TEL_FISSA,
                  idMobile: ID_TEL_MOBILE
                }).toString();

                actionsHtml = `
                  <a class="btn btn-warning btn-icon"
                    href="${editTelUrl}?${qs}"
                    title="Modifica utenze telefoniche">
                    <i class="fas fa-edit"></i>
                  </a>`;
              } else if (merged) {
                // MERGE formazione (6010 + 6011)
                const parts = Array.isArray(row?.merged_of) ? row.merged_of.filter(Boolean) : [];
                if (parts.length >= 2) {
                  const editMergeUrl = `{{ route('riepilogo.costi.edit.formazione') }}`;
                  const qs = new URLSearchParams({
                    idAssociazione: ass,
                    idConvenzione: conv,
                    idA: parts[0],
                    idB: parts[1],
                  }).toString();
                  actionsHtml = `<a class="btn btn-warning btn-icon" href="${editMergeUrl}?${qs}" title="Modifica formazione (A + DAE + RDAE)"><i class="fas fa-edit"></i></a>`;
                } else {
                  actionsHtml = '—';
                }
              } else {
                // Edit classico singola voce
                const ensureUrl = `{{ route('riepilogo.costi.ensureEdit') }}`;
                const qs = new URLSearchParams({
                  idAssociazione: ass,
                  idConvenzione: conv,
                  idVoceConfig: row.idVoceConfig
                }).toString();
                actionsHtml = `<a class="btn btn-warning btn-icon" href="${ensureUrl}?${qs}" title="Modifica"><i class="fas fa-edit"></i></a>`;
              }
            }
            const hoverText ='Voce: ' + (row?.descrizione ?? '-') + '\n';
            const tr = document.createElement('tr');
            tr.setAttribute('title', hoverText);
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

          return {
            prev: sumPrev,
            cons: sumCons
          };
        })
        .catch(() => ({
          prev: 0,
          cons: 0
        }));
    }


    function updateBulkButtonsVisibility() {
      const convVal = ($conv?.value || '').trim();
      const show = !!convVal && convVal !== 'TOT';
      document.querySelectorAll('.js-bulk-bar').forEach(el => el.classList.toggle('d-none', !show));
    }

    async function reloadAllSections() {
      const ids = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11]; // standard (NO mezzi sostitutivi)
      let totPrev = 0,
        totCons = 0;

      for (const id of ids) {
        const s = await loadSezione(id);
        totPrev += s.prev;
        totCons += s.cons;
      }

      const scosTot = totPrev !== 0 ? ((totCons - totPrev) / totPrev * 100) : 0;
      document.getElementById('tot-prev').textContent = eur(totPrev);
      document.getElementById('tot-cons').textContent = eur(totCons);
      document.getElementById('tot-scos').textContent = `${scosTot.toFixed(2)}%`;

      __totPrevGenerale = totPrev;
      __totConsGenerale = totCons;
      updateTotaleNettoSostitutiviRow();

      const noData = (totPrev === 0 && totCons === 0);
      document.getElementById('noDataMessage')?.classList.toggle('d-none', !noData);
      updateBulkButtonsVisibility();
      AnpasLoader.hide();
      return {
        totPrev,
        totCons
      };
    }

    async function loadSezioneMezziSostitutivi() {
      const ass = currentAssociazione();
      const conv = ($conv?.value || '').trim();

      const section = document.getElementById('accordion-mezzi-sostitutivi');
      if (!section) return;

      // se non c’è convenzione o è TOTALE -> nascondi e reset
      if (!conv || conv === 'TOT') {
        section.classList.add('d-none');
        __costoFasciaSost = 0;
        __costoMezziSost = 0;
        updateTotaleNettoSostitutiviRow();
        return;
      }

      const url = `/ajax/rot-sost/stato?idAssociazione=${ass}&idConvenzione=${conv}&anno=${anno}`;
      const res = await fetch(url);
      const data = await res.json().catch(() => ({}));

      section.classList.add('d-none');
      const tbody = document.querySelector('#table-mezzi-sostitutivi tbody');
      if (tbody) tbody.innerHTML = '';

      if (!data?.ok || data.modalita !== 'sostitutivi') {
        __costoFasciaSost = 0;
        __costoMezziSost = 0;
        updateTotaleNettoSostitutiviRow();
        return;
      }

      section.classList.remove('d-none');

      const costoFascia = Number(data.costo_fascia_oraria || 0);
      const costoSost = Number(data.costo_mezzi_sostitutivi || 0);
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

      document.getElementById('summary-prev-sost').textContent = 'Costo fascia oraria: ' + eur(costoFascia);
      document.getElementById('summary-cons-sost').textContent = 'Costo mezzi sostitutivi: ' + eur(costoSost);
      document.getElementById('summary-scos-sost').textContent = 'Totale al netto: ' + eur(totaleNetto);

      __costoFasciaSost = costoFascia;
      __costoMezziSost = costoSost;
      updateTotaleNettoSostitutiviRow();
      AnpasLoader.hide();
    }

    $assoc?.addEventListener('change', function() {
      const assId = this.value || '';
      @if(Route::has('sessione.setAssociazione'))
      fetch("{{ route('sessione.setAssociazione') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
          idAssociazione: assId
        })
      }).finally(() => loadConvenzioniForAss(assId, 'TOT'));
      AnpasLoader.hide();
      @else
      loadConvenzioniForAss(assId, 'TOT');
      AnpasLoader.hide();
      @endif
    });

    $conv?.addEventListener('change', function() {
      AnpasLoader.show();
      const val = this.value;
      @if(Route::has('sessione.setConvenzione'))
      fetch("{{ route('sessione.setConvenzione') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
          idConvenzione: val
        })
      }).finally(async () => {
        await reloadAllSections();
        await loadSezioneMezziSostitutivi();
        if (window.__reloadRotSostBox) window.__reloadRotSostBox();
        AnpasLoader.hide();
      });
      @else
        (async () => {
          await reloadAllSections();
          await loadSezioneMezziSostitutivi();
          AnpasLoader.hide();
        })();
      @endif
    });

    document.addEventListener('click', function(e) {
      const el = e.target.closest('.js-edit-prev');
      if (!el) return;
      e.preventDefault();

      const sezione = el.dataset.sezione;
      const ass = document.getElementById('assocSelect')?.value || @json((string)($selectedAssoc ?? ''));
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

    (async () => {
      if (isElevato) {
        const preSel = ($assoc?.value || '').trim() || String(selectedAssocServer || '');
        if (preSel) loadConvenzioniForAss(preSel, @json($selectedConv ?? 'TOT'));
      } else {
        await reloadAllSections();
        await loadSezioneMezziSostitutivi();
        if (window.__reloadRotSostBox) window.__reloadRotSostBox();
      }

      if ($conv && @json($selectedConv) && @json($selectedConv) !== 'TOT') {
        $conv.value = String(@json($selectedConv));
      }

      updateTotaleNettoSostitutiviRow();
      AnpasLoader.hide();
    })();

    AnpasLoader.hide();
  })();
</script>
@endpush