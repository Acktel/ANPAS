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
  const anno      = @json($anno ?? session('anno_riferimento', now()->year));

  const $table = $('#riepiloghiTable');
  let dataTable = null;

  const $assoc = document.getElementById('assocSelect');
  const $conv  = document.getElementById('convSelect');

  const ajaxDataUrl       = @json(route('riepiloghi.data'));
  const ajaxConvTpl       = @json(route('riepiloghi.convenzioniByAssociazione', ['idAssociazione' => '__ID__']));
  const setConvenzioneUrl = @json(route('sessione.setConvenzione'));
  const ensureRigaUrl     = @json(route('riepiloghi.ensureAndRedirectToEdit'));

  // Route-safe (niente /hardcoded)
  const ROUTE_EDIT_RIGA    = @json(route('riepiloghi.riga.edit', ['id' => '__ID__']));
  const ROUTE_DESTROY_RIGA = @json(route('riepiloghi.riga.destroy', ['id' => '__ID__']));
  const ROUTE_EDIT_TOT     = @json(route('riepiloghi.voce.tot.edit', ['riepilogo' => '__R__', 'voce' => '__V__']));

  function formatNum(val) {
    if (val === null || val === undefined) return '';
    const n = Number(val);
    if (isNaN(n)) return '';
    return n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function showOrHideTable() {
    const show = isElevato ? !!($assoc?.value || '').trim()
                           : !!($conv?.value || '').trim();
    $table.toggle(show);
    if (show && dataTable) {
      dataTable.columns.adjust().draw(false);
    }
  }

  function initTableIfNeeded() {
    if ($.fn.DataTable.isDataTable('#riepiloghiTable')) {
      if (!dataTable) dataTable = $('#riepiloghiTable').DataTable();
      return;
    }

    dataTable = $table.DataTable({
      processing: true,
      serverSide: false,
      stateDuration: -1,
      stateSave: true, 
      ajax: {
        url: ajaxDataUrl,
        data: function(d) {
          d.idAssociazione = ($assoc?.value || '').trim();
          d.idConvenzione  = ($conv?.value || '').trim();
        },
        dataSrc: 'data'
      },
      columns: [
        { data: 'anno' },
        { data: 'descrizione', render: d => d ? String(d).toUpperCase() : '' },
        { data: 'preventivo', className: 'text-end', render: d => formatNum(d) },
        { data: 'consuntivo', className: 'text-end', render: d => formatNum(d) },
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: function(row) {
            const buttons = [];
            const convSel = (document.getElementById('convSelect')?.value || '').trim();
            const nonEdit = !!row.non_editabile;

            if (!nonEdit) {
              if (convSel === 'TOT' && row.tot_editabile) {
                const href = ROUTE_EDIT_TOT.replace('__R__', row.idRiepilogo).replace('__V__', row.voce_id);
                buttons.push(`
                  <a href="${href}" class="btn btn-anpas-edit me-1 btn-icon" title="Modifica TOTALE">
                    <i class="fas fa-edit"></i>
                  </a>
                `);
              } else if (convSel !== 'TOT') {
                if (row.valore_id) {
                  const href = ROUTE_EDIT_RIGA.replace('__ID__', row.valore_id);
                  buttons.push(`
                    <a href="${href}" class="btn btn-anpas-edit me-1 btn-icon" title="Modifica">
                      <i class="fas fa-edit"></i>
                    </a>
                  `);
                } else {
                  // Riga mancante: crea e vai in edit
                  buttons.push(`
                    <form action="${ensureRigaUrl}" method="POST" style="display:inline-block">
                      <input type="hidden" name="_token" value="${csrfToken}">
                      <input type="hidden" name="idRiepilogo" value="${row.idRiepilogo}">
                      <input type="hidden" name="idVoceConfig" value="${row.voce_id}">
                      <input type="hidden" name="idConvenzione" value="${convSel}">
                      <button type="submit" class="btn btn-anpas-edit btn-icon" title="Crea e modifica">
                        <i class="fas fa-edit"></i>
                      </button>
                    </form>
                  `);
                }
              }
            }

            if (convSel !== 'TOT' && row.valore_id) {
              const action = ROUTE_DESTROY_RIGA.replace('__ID__', row.valore_id);
              buttons.push(`
                <form action="${action}" method="POST" style="display:inline-block"
                      onsubmit="return confirm('Confermi cancellazione della riga?')">
                  <input type="hidden" name="_token" value="${csrfToken}">
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit" class="btn btn-anpas-delete btn-icon" title="Elimina riga">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              `);
            }

            return buttons.join('');
          }
        }
      ],
      language: {
        url: '/js/i18n/Italian.json',
        paginate: {
          first: '<i class="fas fa-angle-double-left"></i>',
          last: '<i class="fas fa-angle-double-right"></i>',
          next: '<i class="fas fa-angle-right"></i>',
          previous: '<i class="fas fa-angle-left"></i>'
        },
      },
      paging: true,
      searching: true,
      ordering: true,
      stripeClasses: ['table-striped-anpas', ''],
      rowCallback: function(row, data, index) {
        $(row).toggleClass('even odd', false).addClass(index % 2 === 0 ? 'even' : 'odd');
      }
    });
  }

  function reloadTable() {
    if (!$.fn.DataTable.isDataTable('#riepiloghiTable')) {
      initTableIfNeeded();
    }
    if (!dataTable) dataTable = $('#riepiloghiTable').DataTable();
    dataTable.ajax.reload(null, false);
  }

  function loadConvenzioniForAss(assId) {
    if (!$conv) return;
    $conv.innerHTML = '';
    if (!assId) {
      $conv.setAttribute('disabled', 'disabled');
      showOrHideTable();
      return;
    }
    $conv.removeAttribute('disabled');

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

  // Event listeners
  $assoc?.addEventListener('change', function () {
    loadConvenzioniForAss((this.value || '').trim());
  });

  $conv?.addEventListener('change', function () {
    fetch(setConvenzioneUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ idConvenzione: this.value })
    }).finally(() => {
      showOrHideTable();
      initTableIfNeeded();
      reloadTable();
    });
  });

  // Bootstrap iniziale
  if (isElevato) {
    const preSel = ($assoc?.value || '').trim();
    preSel ? loadConvenzioniForAss(preSel) : showOrHideTable();
  } else {
    loadConvenzioniForAss(($assoc?.value || '').trim());
  }
});
</script>
@endpush
