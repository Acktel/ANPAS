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
      2  => 'Automezzi',
      3  => 'Attrezzatura Sanitaria',
      4  => 'Telecomunicazioni',
      5  => 'Costi gestione struttura',
      6  => 'Costo del personale',
      7  => 'Materiale sanitario di consumo',
      8  => 'Costi amministrativi',
      9  => 'Quote di ammortamento',
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
              <div class="col-5 fw-bold">{{ $id }} — {{ $titolo }}</div>
              <div class="col-2" id="summary-prev-{{ $id }}">-</div>
              <div class="col-2" id="summary-cons-{{ $id }}">-</div>
              <div class="col-2" id="summary-scos-{{ $id }}">-</div>
            </div>
          </button>
        </h2>
        <div id="collapse-{{ $id }}" class="accordion-collapse collapse" data-bs-parent="#accordionRiep">
          <div class="accordion-body">
            <table id="table-sezione-{{ $id }}"
                   class="table table-hover table-striped-anpas table-bordered w-100 mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th>Voce</th>
                  <th class="text-end" style="width:160px">Preventivo</th>
                  <th class="text-end" style="width:160px">Consuntivo</th>
                  <th class="text-end" style="width:140px">% Scostamento</th>
                  <th class="text-center" style="width:90px">Azioni</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    @endforeach

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
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
  const isElevato = @json($isElevato);
  const anno = @json($anno);
  const selectedAssocServer = @json((int)($selectedAssoc ?? 0));
  const $assoc = document.getElementById('assocSelect');
  const $conv  = document.getElementById('convSelect');

  // utils
  const eur = v => new Intl.NumberFormat('it-IT', { style:'currency', currency:'EUR' }).format(Number(v||0));
  const pct = v => `${(Number(v)||0).toFixed(2)}%`;

  // Associazione corrente con fallback dalla sessione lato server
  function currentAssociazione(){
    let v = ($assoc?.value || '').trim();
    if (!v) v = String(selectedAssocServer || '');
    return v;
  }

  // carica convenzioni per associazione (con TOTALE in cima)
  function loadConvenzioniForAss(assId, preselect = 'TOT'){
    if (!$conv) return;
    $conv.innerHTML = '';
    if (!assId) { $conv.setAttribute('disabled', 'disabled'); return; }
    $conv.removeAttribute('disabled');

    const optTot = document.createElement('option');
    optTot.value = 'TOT'; optTot.textContent = 'TOTALE';
    $conv.appendChild(optTot);

    fetch(`/ajax/convenzioni-by-associazione/${assId}?anno=${anno}`)
      .then(r => r.ok ? r.json() : [])
      .then(items => {
        (items || []).forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.id; opt.textContent = c.text;
          $conv.appendChild(opt);
        });
        $conv.value = preselect ?? 'TOT';
        reloadAllSections();
      })
      .catch(()=>{ /* ignore */ });
  }

  // render di UNA sezione
  function loadSezione(idTipologia){
    const ass = currentAssociazione();
    const conv = ($conv?.value || '').trim();

    const url = `{{ route('riepilogo.costi.sezione', ['idTipologia' => '__ID__']) }}`
      .replace('__ID__', idTipologia);

    const params = new URLSearchParams({
      idAssociazione: ass,
      idConvenzione: conv
    });

    return fetch(`${url}?${params}`)
      .then(r => r.json())
      .then(({data}) => {
        const tbody = document.querySelector(`#table-sezione-${idTipologia} tbody`);
        if (!tbody) return { prev: 0, cons: 0 };

        tbody.innerHTML = '';
        let sumPrev = 0, sumCons = 0;

        (data || []).forEach(row => {
          sumPrev += Number(row.preventivo || 0);
          sumCons += Number(row.consuntivo || 0);

          const editingEnabled = !!conv && conv !== 'TOT';

          // Link EDIT: ensure -> redirect a edit(rigaId)
          let actionsHtml = '—';
          if (editingEnabled) {
            const ensureUrl = `{{ route('riepilogo.costi.ensureEdit') }}`;
            const qs = new URLSearchParams({
              idAssociazione: ass,
              idConvenzione: conv,
              idVoceConfig: row.idVoceConfig
            }).toString();
            actionsHtml = `
              <a class="btn btn-warning btn-icon"
                 href="${ensureUrl}?${qs}"
                 title="Modifica">
                <i class="fas fa-edit"></i>
              </a>
            `;
          }

          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${row.descrizione ?? ''}</td>
            <td class="text-end">${eur(row.preventivo)}</td>
            <td class="text-end">${eur(row.consuntivo)}</td>
            <td class="text-end">${row.scostamento ?? '0%'}</td>
            <td class="text-center">${actionsHtml}</td>
          `;
          tbody.appendChild(tr);
        });

        // summary sezione
        const scos = sumPrev !== 0 ? ((sumCons - sumPrev) / sumPrev * 100) : 0;
        document.getElementById(`summary-prev-${idTipologia}`).textContent = eur(sumPrev);
        document.getElementById(`summary-cons-${idTipologia}`).textContent = eur(sumCons);
        document.getElementById(`summary-scos-${idTipologia}`).textContent = pct(scos);

        return { prev: sumPrev, cons: sumCons };
      })
      .catch(() => ({ prev: 0, cons: 0 }));
  }

  // ricarica TUTTE le sezioni + totale generale
  function reloadAllSections(){
    const ids = [2,3,4,5,6,7,8,9,10,11];
    let totPrev = 0, totCons = 0;

    (async () => {
      for (const id of ids){
        const s = await loadSezione(id);
        totPrev += s.prev; totCons += s.cons;
      }
      const scosTot = totPrev !== 0 ? ((totCons - totPrev) / totPrev * 100) : 0;
      document.getElementById('tot-prev').textContent = eur(totPrev);
      document.getElementById('tot-cons').textContent = eur(totCons);
      document.getElementById('tot-scos').textContent = pct(scosTot);

      const noData = (totPrev === 0 && totCons === 0);
      document.getElementById('noDataMessage')?.classList.toggle('d-none', !noData);
    })();
  }

  // listeners
  $assoc?.addEventListener('change', function(){
    const assId = this.value || '';
    @if (Route::has('sessione.setAssociazione'))
    fetch("{{ route('sessione.setAssociazione') }}", {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ idAssociazione: assId })
    }).finally(() => loadConvenzioniForAss(assId, 'TOT'));
    @else
    loadConvenzioniForAss(assId, 'TOT');
    @endif
  });

  $conv?.addEventListener('change', function(){
    const val = this.value;
    @if (Route::has('sessione.setConvenzione'))
    fetch("{{ route('sessione.setConvenzione') }}", {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ idConvenzione: val })
    }).finally(reloadAllSections);
    @else
    reloadAllSections();
    @endif
  });

  // bootstrap
  if (isElevato) {
    const preSel = ($assoc?.value || '').trim() || String(selectedAssocServer || '');
    if (preSel) loadConvenzioniForAss(preSel, @json($selectedConv ?? 'TOT'));
  } else {
    reloadAllSections();
  }
})();
</script>
@endpush
