@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page-header d-print-none">
  <div class="row g-2 align-items-center">
    <div class="col">
      <h2 class="page-title mb-2">Dashboard</h2>
      <p>Panoramica costi {{ $anno }}
        @if($assocName)
          — dati reali per associazione <strong>{{ $assocName }}</strong>
        @endif
      </p>
    </div>
  </div>
</div>

<div class="page-body">
  {{-- Selettore associazione --}}
  @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
    <div class="mb-3">
      <form method="GET" action="{{ route('dashboard') }}" id="assocSelectForm" class="w-100" style="max-width:400px">
        <div class="position-relative">
          <div class="input-group">
            <input id="assocSelect" name="assocLabel" class="form-control" autocomplete="off"
              placeholder="Seleziona associazione"
              value="{{ optional($associazioni->firstWhere('idAssociazione', $selectedAssoc))->Associazione ?? '' }}"
              aria-label="Seleziona associazione">
            <button type="button" id="assocSelectToggleBtn" class="btn btn-outline-secondary" aria-haspopup="listbox" aria-expanded="false" title="Mostra elenco">
              <i class="fas fa-chevron-down"></i>
            </button>
            <input type="hidden" id="assocSelectHidden" name="idAssociazione" value="{{ $selectedAssoc ?? '' }}">
          </div>
          <ul id="assocSelectDropdown" class="list-group position-absolute w-100"
              style="z-index:2000; display:none; max-height:240px; overflow:auto; top:100%; left:0; background-color:#fff;">
            @foreach($associazioni as $assoc)
              <li class="list-group-item assoc-item" data-id="{{ $assoc->idAssociazione }}">{{ $assoc->Associazione }}</li>
            @endforeach
          </ul>
        </div>
      </form>
    </div>
  @endif

  {{-- KPI --}}
  <div class="row row-deck row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="text-muted mb-1">Totale Preventivo</div>
        <div class="h2">{{ number_format($totPrev, 2, ',', '.') }} €</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="text-muted mb-1">Totale Consuntivo</div>
        <div class="h2">{{ number_format($totCons, 2, ',', '.') }} €</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="text-muted mb-1">Scostamento Totale</div>
        <div class="h2">
          {{ number_format($deltaTot, 2, ',', '.') }} €
          @if(!is_null($scostPercTot))
            <small class="{{ $deltaTot>=0 ? 'text-danger' : 'text-success' }}">
              ({{ $scostPercTot }}%)
            </small>
          @endif
        </div>
      </div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card"><div class="card-body">
        <div class="text-muted mb-1">Convenzioni · Mezzi · Km</div>
        <div class="h2">{{ $numeroConvenzioni }} <small class="text-muted">conv</small></div>
        <div>{{ $numeroAutomezzi }} mezzi · {{ number_format($kmTotali, 0, ',', '.') }} km</div>
      </div></div>
    </div>
  </div>

  {{-- Grafici --}}
  <div class="row row-deck row-cards">
    {{-- 1) Barre: Preventivo vs Consuntivo + linea Scostamento % --}}
    <div class="col-lg-12 mb-4">
      <div class="card h-100">
        <div class="card-header"><strong>Riepilogo costi per tipologia</strong> — Preventivo vs Consuntivo & Scostamento %</div>
        <div class="card-body"><canvas id="chartBarLine" height="120"></canvas></div>
      </div>
    </div>

    {{-- 2) Donut: Peso % consuntivo per tipologia --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header"><strong>Incidenza sul Consuntivo</strong> — % per tipologia</div>
        <div class="card-body"><canvas id="chartDonut" height="220"></canvas></div>
      </div>
    </div>

    {{-- 3) Classifica: Top 5 scostamenti (€, valori assoluti) --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header"><strong>Top scostamenti</strong> — 5 tipologie con delta € più alto (assoluto)</div>
        <div class="card-body"><canvas id="chartTopDelta" height="220"></canvas></div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  {{-- Dataset dal server --}}
  <script>
    const DASH_LAB   = @json($labels);
    const DASH_PREV  = @json($preventivi);
    const DASH_CONS  = @json($consuntivi);
    const DASH_SCOST = @json($scostamenti);
    const TOP_LAB    = @json($topLabels);
    const TOP_DELTA  = @json($topDelta);

    // Donut: usa i valori assoluti per le fette (evita che spariscano se negativi)
    const DASH_CONS_ABS = (DASH_CONS || []).map(v => Math.abs(Number(v) || 0));

    // Palette (20 colori) — cicla se le voci sono >20
    const DONUT_COLORS = [
      '#2E7D32','#C62828','#1565C0','#EF6C00','#6A1B9A',
      '#00838F','#9E9D24','#AD1457','#283593','#558B2F',
      '#D81B60','#00897B','#F9A825','#5D4037','#7B1FA2',
      '#1976D2','#E64A19','#303F9F','#0097A7','#9C27B0'
    ];

    const fmtEuro = n => (Number(n)||0).toLocaleString('it-IT',{minimumFractionDigits:2, maximumFractionDigits:2});
  </script>

  {{-- Grafico 1: Barre + Linea --}}
  <script>
    (function(){
      const ctx = document.getElementById('chartBarLine').getContext('2d');
      new Chart(ctx, {
        data: {
          labels: DASH_LAB,
          datasets: [
            { type: 'bar',  label: 'Preventivo', data: DASH_PREV },
            { type: 'bar',  label: 'Consuntivo', data: DASH_CONS },
            { type: 'line', label: 'Scostamento %', data: DASH_SCOST, yAxisID: 'y1', tension: 0.2, borderWidth: 2, pointRadius: 3 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: '€' },
              ticks: { callback: v => v.toLocaleString('it-IT') }
            },
            y1: {
              position: 'right',
              beginAtZero: true,
              title: { display: true, text: '%' },
              ticks: { callback: v => v + '%' },
              grid: { drawOnChartArea: false }
            }
          },
          plugins: {
            legend: { position: 'top' },
            tooltip: {
              mode: 'index',
              intersect: false,
              callbacks: {
                label: (ctx) => {
                  const isPerc = (ctx.dataset.type === 'line') || (ctx.dataset.yAxisID === 'y1');
                  return isPerc
                    ? `${ctx.dataset.label}: ${Number(ctx.parsed.y ?? 0).toFixed(2)}%`
                    : `${ctx.dataset.label}: ${fmtEuro(ctx.parsed.y)} €`;
                }
              }
            }
          }
        }
      });
    })();
  </script>

  {{-- Grafico 2: Donut consuntivo (valori assoluti per % + tooltip con valore originale) --}}
  <script>
  (function(){
    const wrap = document.getElementById('chartDonut').getContext('2d');
    const totalAbs = (DASH_CONS_ABS || []).reduce((a,b)=>a+b,0);

    if (!totalAbs) {
      // Mostra un messaggio al posto del grafico
      wrap.canvas.parentElement.innerHTML = '<div class="text-center text-muted py-5">Nessun consuntivo disponibile</div>';
      return;
    }

    new Chart(wrap, {
      type: 'doughnut',
      data: {
        labels: DASH_LAB,
        datasets: [{
          data: DASH_CONS_ABS,
          backgroundColor: DASH_LAB.map((_, i) => DONUT_COLORS[i % DONUT_COLORS.length]),
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
          legend: { position: 'right' },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const idx = ctx.dataIndex;
                const absVal = Number(ctx.parsed) || 0;
                const origVal = Number(DASH_CONS[idx] ?? 0);
                const pct = totalAbs > 0 ? (absVal / totalAbs * 100) : 0;
                return `${ctx.label}: ${fmtEuro(origVal)} €  (${pct.toFixed(2)}%)`;
              }
            }
          }
        }
      }
    });
  })();
</script>


  {{-- Grafico 3: Orizzontale top scostamenti (già ordinati lato server, valori assoluti) --}}
  <script>
    (function(){
      const ctx = document.getElementById('chartTopDelta').getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: TOP_LAB,
          datasets: [{ label: 'Δ € (Consuntivo - Preventivo)', data: TOP_DELTA }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: {
              beginAtZero: true,
              ticks: { callback: v => v.toLocaleString('it-IT') }
            }
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => `Δ: ${fmtEuro(ctx.parsed.x)} €`
              }
            }
          }
        }
      });
    })();
  </script>

  {{-- Custom select associazione (riuso) --}}
  <script>
    function setupCustomSelect(formId, inputId, dropdownId, toggleBtnId, hiddenId) {
      const form = document.getElementById(formId);
      const input = document.getElementById(inputId);
      const dropdown = document.getElementById(dropdownId);
      const toggleBtn = document.getElementById(toggleBtnId);
      const hidden = document.getElementById(hiddenId);
      if (!form || !input || !dropdown || !hidden) return;

      const items = Array.from(dropdown.querySelectorAll('.assoc-item'))
        .map(li => ({ id: String(li.dataset.id), name: (li.textContent || '').trim(), node: li }));

      function showDropdown(){ dropdown.style.display='block'; toggleBtn?.setAttribute('aria-expanded','true'); }
      function hideDropdown(){ dropdown.style.display='none';  toggleBtn?.setAttribute('aria-expanded','false');}
      function filterDropdown(term){
        term = (term||'').toLowerCase();
        dropdown.querySelectorAll('.assoc-item').forEach(li => {
          const txt = (li.textContent || '').toLowerCase();
          li.style.display = txt.includes(term) ? '' : 'none';
        });
      }
      function setSelection(id, name, submit=true){
        hidden.value = id ?? ''; input.value = name ?? '';
        try{ localStorage.setItem('associazione_selezionata', id ?? ''); localStorage.setItem('selectedAssocLabel', name ?? ''); }catch(e){}
        try{
          const url = new URL(window.location);
          if (id) url.searchParams.set('idAssociazione', id); else url.searchParams.delete('idAssociazione');
          history.replaceState(null,'',url);
        }catch(e){}
        if (submit) form.submit();
      }
      dropdown.querySelectorAll('.assoc-item').forEach(li => {
        li.style.cursor='pointer';
        li.addEventListener('click', function(){ setSelection(this.dataset.id, this.textContent.trim()); });
      });
      input.addEventListener('input', () => filterDropdown(input.value));
      toggleBtn?.addEventListener('click', ev => { ev.stopPropagation(); dropdown.style.display==='block'?hideDropdown():showDropdown(); });
      document.addEventListener('click', e => { if (!form.contains(e.target)) hideDropdown(); });

      (function restore(){
        try{
          const hid = (hidden.value || '').toString();
          if (hid){
            const found = items.find(it => it.id === hid);
            if (found){ input.value = found.name; return; }
          }
          const storedId = localStorage.getItem('associazione_selezionata');
          const storedLabel = localStorage.getItem('selectedAssocLabel');
          if (!hidden.value && storedId){ hidden.value = storedId; input.value = storedLabel || input.value; }
          else if (hidden.value && !input.value && storedLabel){ input.value = storedLabel; }
        }catch(e){}
      })();
    }
    setupCustomSelect("assocSelectForm","assocSelect","assocSelectDropdown","assocSelectToggleBtn","assocSelectHidden");
  </script>
@endpush
