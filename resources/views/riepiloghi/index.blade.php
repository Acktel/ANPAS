{{-- resources/views/riepiloghi/index.blade.php --}}
@extends('layouts.app')

@php
  $user = Auth::user();
@endphp

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Dati caratteristici (Tipologia 1)</h1>

  {{-- Filtri --}}
  <div class="row g-3 align-items-end mb-3">
    @if($user->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
      <div class="col-md-4">
        <label for="assocSelect" class="form-label">Associazione</label>
        <select id="assocSelect" class="form-select">
          <option value="">— seleziona —</option>
          @foreach($associazioni as $assoc)
            <option
              value="{{ $assoc->idAssociazione }}"
              {{ $assoc->idAssociazione == ($selectedAssoc ?? session('associazione_selezionata')) ? 'selected' : '' }}>
              {{ $assoc->Associazione }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label for="convSelect" class="form-label">Convenzione</label>
        <select id="convSelect" class="form-select" disabled>
          <option value="">— seleziona —</option>
        </select>
      </div>
    @else
      <input type="hidden" id="assocSelect" value="{{ $selectedAssoc }}">
      <div class="col-md-6">
        <label for="convSelect" class="form-label">Convenzione</label>
        <select id="convSelect" class="form-select">
          <option value="TOT">TOTALE</option>
        </select>
      </div>
    @endif
  </div>

  {{-- Tabella --}}
  <table
    id="riepiloghiTable"
    class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap w-100 mb-0"
    style="display:none;">
    <thead class="thead-anpas">
      <tr>
        <th>Anno</th>
        <th>Descrizione</th>
        <th class="text-end">Preventivo</th>
        <th class="text-end">Consuntivo</th>
        <th class="text-center">Azioni</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
  const isElevato = @json($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']));
  const anno = @json($anno ?? session('anno_riferimento', now()->year));

  const $table = $('#riepiloghiTable');
  let dataTable = null;

  const $assoc = document.getElementById('assocSelect');
  const $conv  = document.getElementById('convSelect');

  // Routes / URL templates
  const ajaxDataUrl = @json(route('riepiloghi.data'));
  const ajaxConvTpl = @json(route('riepiloghi.convenzioniByAssociazione', ['idAssociazione' => '__ID__']));
  const setConvenzioneUrl = @json(route('sessione.setConvenzione'));
  const ensureEditUrl = @json(route('riepiloghi.riga.ensureEdit')); // GET con query: idRiepilogo, voce_id, idConvenzione
  const destroyByKeysUrl = @json(route('riepiloghi.riga.destroyByKeys'));
  const totEditTpl = @json(route('riepiloghi.voce.tot.edit', ['riepilogo' => '__R__', 'voce' => '__V__']));

  function isTotaleSelected () {
    return (($conv?.value || '').trim() === 'TOT');
  }

  function showOrHideTable() {
    if (isElevato) {
      const ass = ($assoc?.value || '').trim();
      $table.toggle(!!ass);
    } else {
      const conv = ($conv?.value || '').trim();
      $table.toggle(!!conv);
    }
  }

  function formatNum(val) {
    if (val === null || val === undefined) return '';
    // se è stringa non numerica (es. "-"), la ritorno così com'è
    if (typeof val === 'string' && isNaN(parseFloat(val))) return val;
    const n = Number(val);
    if (isNaN(n)) return '';
    return n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function initTableIfNeeded() {
    if (dataTable) return;

    dataTable = $table.DataTable({
      processing: true,
      serverSide: false,
      ajax: {
        url: ajaxDataUrl,
        data: function (d) {
          d.tipologia      = 1;
          d.idAssociazione = ($assoc?.value || '').trim();
          d.idConvenzione  = ($conv?.value || '').trim(); // 'TOT' oppure id numerico
        },
        dataSrc: 'data'
      },
      columns: [
        { data: 'anno' },
        {
          data: 'descrizione',
          render: d => d ? String(d).toUpperCase() : ''
        },
        {
          data: 'preventivo',
          className: 'text-end',
          render: function (d) { return formatNum(d); }
        },
        {
          data: 'consuntivo',
          className: 'text-end',
          render: function (d) { return formatNum(d); }
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: function (row) {
            const riepilogoId   = row.idRiepilogo;
            const voceId        = row.voce_id;       // id configurazione voce (corretto)
            const totale        = isTotaleSelected();
            const totabile      = !!row.tot_editabile;
            const nonEditabile  = !!row.non_editabile;

            let html = '';

            if (totale) {
              // Vista TOTALE: consenti edit solo se la voce è "tot-editabile" e non è calcolata
              if (totabile && !nonEditabile) {
                const url = totEditTpl.replace('__R__', riepilogoId).replace('__V__', voceId);
                html += `
                  <a href="${url}" class="btn btn-warning btn-icon" title="Modifica (TOTALE)">
                    <i class="fas fa-edit"></i>
                  </a>
                `;
              } else {
                html += `<span class="text-muted">—</span>`;
              }
            } else {
              // Vista CONVENZIONE: consenti edit solo se NON è calcolata
              if (!nonEditabile) {
                const idConv = ($conv?.value || '').trim();
                const qs = new URLSearchParams({
                  idRiepilogo: riepilogoId,
                  voce_id: voceId,
                  idConvenzione: idConv
                }).toString();

                html += `
                  <a href="${ensureEditUrl}?${qs}" class="btn btn-warning me-1 btn-icon" title="Modifica">
                    <i class="fas fa-edit"></i>
                  </a>
                  <form action="${destroyByKeysUrl}" method="POST"
                        class="d-inline-block"
                        onsubmit="return confirm('Confermi eliminazione?')">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <input type="hidden" name="idRiepilogo" value="${riepilogoId}">
                    <input type="hidden" name="voce_id" value="${voceId}">
                    <input type="hidden" name="idConvenzione" value="${idConv}">
                    <button type="submit" class="btn btn-danger btn-icon" title="Elimina">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                `;
              } else {
                html += `<span class="text-muted">—</span>`;
              }
            }

            return html;
          }
        }
      ],
      language: { url: '/js/i18n/Italian.json' },
      paging: true,
      searching: true,
      ordering: true
    });
  }

  function reloadTable() {
    if (dataTable) dataTable.ajax.reload(null, false);
  }

  // Carica convenzioni per Associazione (forza TOT al cambio)
  function loadConvenzioniForAss(assId) {
    if (!$conv) return;
    $conv.innerHTML = '';
    if (!assId) {
      $conv.setAttribute('disabled', 'disabled');
      showOrHideTable();
      return;
    }
    $conv.removeAttribute('disabled');

    // Aggiungi "TOTALE"
    const optTot = document.createElement('option');
    optTot.value = 'TOT';
    optTot.textContent = 'TOTALE';
    $conv.appendChild(optTot);

    const url = ajaxConvTpl.replace('__ID__', assId) + '?anno=' + encodeURIComponent(anno);

    fetch(url)
      .then(r => r.ok ? r.json() : [])
      .then(items => {
        (items || []).forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.text;
          $conv.appendChild(opt);
        });
        $conv.value = 'TOT';
        showOrHideTable();
        initTableIfNeeded();
        reloadTable();
      })
      .catch(() => showOrHideTable());
  }

  // Listeners
  $assoc?.addEventListener('change', function () {
    const assId = (this.value || '').trim();
    // Non serve settare la sessione lato server per l'associazione:
    // passiamo sempre idAssociazione nell'ajax di DataTables
    loadConvenzioniForAss(assId);
  });

  $conv?.addEventListener('change', function () {
    // Questa route esiste: aggiorno sessione per coerenza con altre pagine
    fetch(setConvenzioneUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ idConvenzione: this.value })
    }).finally(() => {
      showOrHideTable();
      initTableIfNeeded();
      reloadTable();
    });
  });

  // bootstrap iniziale
  if (isElevato) {
    const preSel = ($assoc?.value || '').trim();
    preSel ? loadConvenzioniForAss(preSel) : showOrHideTable();
  } else {
    loadConvenzioniForAss(($assoc?.value || '').trim());
  }
});
</script>
@endpush
