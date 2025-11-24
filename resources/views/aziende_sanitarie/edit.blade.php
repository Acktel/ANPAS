@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Azienda Sanitaria</h1>

  @if ($errors->any())
      <div class="alert alert-danger">
          <ul class="mb-0">
              @foreach ($errors->all() as $err)
                  <li>{{ $err }}</li>
              @endforeach
          </ul>
      </div>
  @endif

  <form action="{{ route('aziende-sanitarie.update', $azienda->idAziendaSanitaria) }}" method="POST">
      @csrf
      @method('PATCH')

      <div class="card-anpas mb-4">
        <div class="card-body bg-anpas-white">

          {{-- NAV TABS --}}
          <ul class="nav nav-tabs mb-3" role="tablist">
              <li class="nav-item" role="presentation">
                  <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-anagrafica" role="tab">
                      Anagrafica
                  </button>
              </li>

              <li class="nav-item" role="presentation">
                  <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-lotti" role="tab">
                      Lotti
                  </button>
              </li>

              @if($isElevato)
              <li class="nav-item" role="presentation">
                  <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-conv" role="tab">
                      Convenzioni
                  </button>
              </li>
              @endif
          </ul>

          <div class="tab-content">

              {{-- TAB ANAGRAFICA --}}
              <div class="tab-pane fade show active" id="pane-anagrafica" role="tabpanel">

                  <div class="mb-3">
                      <label class="form-label">Nome Azienda</label>
                      <input type="text" name="Nome" class="form-control" required value="{{ old('Nome', $azienda->Nome) }}">
                  </div>

                  <div class="row">
                      {{-- Provincia --}}
                      <div class="col-md-4 mb-3">
                        <label class="form-label">Provincia</label>
                        <select name="provincia" id="provincia" class="form-select">
                            <option value="">-- Seleziona --</option>
                            @php
                                $allowed = ['VC','AL','AT','BI','CN','NO','TO','VB'];
                                $prov = collect($cities)->pluck('sigla_provincia')->unique()->filter(fn($p)=>in_array($p,$allowed))->sort();
                                $selectedProv = old('provincia', $azienda->provincia);
                            @endphp

                            @foreach($prov as $p)
                            <option value="{{ $p }}" {{ $selectedProv == $p ? 'selected' : '' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                      </div>

                      {{-- Città --}}
                      <div class="col-md-4 mb-3">
                        <label class="form-label">Città</label>
                        <select name="citta" id="citta" class="form-select" data-current="{{ old('citta', $azienda->citta) }}">
                            <option value="">-- Seleziona --</option>
                        </select>
                      </div>

                      {{-- CAP --}}
                      <div class="col-md-4 mb-3">
                        <label class="form-label">CAP</label>
                        <select name="cap" id="cap" class="form-select" data-current="{{ old('cap', $azienda->cap) }}">
                            <option value="">-- Seleziona --</option>
                        </select>
                      </div>
                  </div>

                  <div class="mb-3">
                      <label class="form-label">Indirizzo</label>
                      <input type="text" name="Indirizzo" class="form-control" value="{{ old('Indirizzo', $azienda->Indirizzo) }}">
                  </div>

                  <div class="mb-3">
                      <label class="form-label">Email</label>
                      <input type="email" name="mail" class="form-control" value="{{ old('mail', $azienda->mail) }}">
                  </div>

                  <div class="mb-3">
                      <label class="form-label">Note</label>
                      <textarea name="note" class="form-control" rows="3">{{ old('note', $azienda->note) }}</textarea>
                  </div>

                  <div class="text-center mt-4">
                      <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary me-2">Annulla</a>

                      <button id="btnToLotti" type="button" class="btn btn-anpas-green">
                          Avanti
                      </button>
                  </div>

              </div>

              {{-- TAB LOTTI --}}
              <div class="tab-pane fade" id="pane-lotti" role="tabpanel">

                  @php
                      $lp = old('lotti_presenti', $lotti->count() ? '1' : '0');
                  @endphp

                  <div class="d-flex justify-content-between align-items-center mb-3">
                      <label class="form-label fw-bold mb-0">Lotti presenti:</label>
                      <div>
                          <label class="me-3">
                              <input type="radio" name="lotti_presenti" value="1" {{ $lp=='1'?'checked':'' }}> Sì
                          </label>

                          <label>
                              <input type="radio" name="lotti_presenti" value="0" {{ $lp=='0'?'checked':'' }}> No
                          </label>
                      </div>
                  </div>

                  <hr>

                  <table class="table table-bordered">
                      <thead>
                          <tr>
                              <th>#</th>
                              <th>Nome Lotto</th>
                              <th>Descrizione</th>
                              <th>Azioni</th>
                          </tr>
                      </thead>
                      <tbody id="lottiContainer">
                          @foreach($lotti as $i => $lotto)
                          <tr data-index="{{ $i }}">
                              <td class="lotto-index">{{ $i+1 }}</td>
                              <td><input type="text" name="lotti[{{ $i }}][nomeLotto]" class="form-control" value="{{ $lotto->nomeLotto }}"></td>
                              <td><input type="text" name="lotti[{{ $i }}][descrizione]" class="form-control" value="{{ $lotto->descrizione }}"></td>
                              <td>
                                  <button type="button" class="btn btn-danger btn-sm remove-lotto"><i class="fas fa-trash"></i></button>
                                  <input type="hidden" name="lotti[{{ $i }}][id]" value="{{ $lotto->id }}">
                                  <input type="hidden" name="lotti[{{ $i }}][_delete]" value="0">
                              </td>
                          </tr>
                          @endforeach
                      </tbody>
                  </table>

                  <button type="button" id="addLotto" class="btn btn-anpas-green mb-4">+ Aggiungi Lotto</button>

                <div class="text-center">
                    <button id="btnBackAnagrafica" type="button" class="btn btn-secondary me-2">
                        Indietro
                    </button>

                    @if($isElevato)
                    <button id="btnToConvenzioni" type="button" class="btn btn-anpas-green">
                        Avanti
                    </button>
                    @else
                    <button type="submit" class="btn btn-anpas-green">Salva</button>
                    @endif
                </div>
              </div>

              {{-- TAB CONVENZIONI --}}
              @if($isElevato)
              <div class="tab-pane fade" id="pane-conv" role="tabpanel">

                  <div class="alert alert-info">
                      Seleziona le associazioni che devono avere una convenzione per ciascun lotto.
                  </div>

                  <table class="table table-bordered" id="convTable">
                      <thead>
                          <tr>
                              <th>#</th>
                              <th>Convenzione</th>
                              <th>Associazioni (multi-select)</th>
                          </tr>
                      </thead>
                      <tbody></tbody>
                  </table>

                  <div class="text-center mt-4">
                      <button id="btnBackLotti" type="button" class="btn btn-secondary me-2">
                          Indietro
                      </button>
                      <button type="submit" class="btn btn-anpas-green">Salva Modifiche</button>
                  </div>
              </div>
              @endif

          </div> {{-- tab-content --}}
        </div>
      </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  // ===========================================
  // CAMBIO TAB SENZA data-bs-toggle
  // ===========================================
  function goToTab(id) {
      const trigger = document.querySelector(`button[data-bs-target="${id}"]`);
      if (!trigger) return;
      new bootstrap.Tab(trigger).show();
  }

  // BOTTONI
  const btnToLotti        = document.getElementById('btnToLotti');
  const btnBackAnagrafica = document.getElementById('btnBackAnagrafica');
  const btnToConvenzioni  = document.getElementById('btnToConvenzioni');
  const btnBackLotti      = document.getElementById('btnBackLotti');

  btnToLotti?.addEventListener('click',        () => goToTab('#pane-lotti'));
  btnBackAnagrafica?.addEventListener('click', () => goToTab('#pane-anagrafica'));
  btnToConvenzioni?.addEventListener('click',  () => goToTab('#pane-conv'));
  btnBackLotti?.addEventListener('click',      () => goToTab('#pane-lotti'));


  // ============================================================
  // PROVINCIA → CITTÀ → CAP (DINAMICI)
  // ============================================================
  const ALL_CITIES = @json($cities);
  const ALL_CAPS   = @json($caps);

  const provSelect = document.getElementById('provincia');
  const citySelect = document.getElementById('citta');
  const capSelect  = document.getElementById('cap');

  // valori iniziali (old() o DB)
  const initialProv = "{{ old('provincia', $azienda->provincia ?? '') }}";
  const initialCity = "{{ old('citta', $azienda->citta ?? '') }}";
  const initialCap  = "{{ old('cap', $azienda->cap ?? '') }}";

  function resetSelect(select, text = '-- Seleziona --') {
      select.innerHTML = `<option value="">${text}</option>`;
  }

  function populateCities() {
      resetSelect(citySelect);
      resetSelect(capSelect);

      const p = provSelect.value;
      if (!p) return;

      const list = ALL_CITIES.filter(c => c.sigla_provincia === p);

      list.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.denominazione_ita;
          opt.textContent = c.denominazione_ita.charAt(0).toUpperCase() + c.denominazione_ita.slice(1);
          if (c.denominazione_ita === initialCity) opt.selected = true;
          citySelect.appendChild(opt);
      });

      if (initialCity) citySelect.value = initialCity;

      populateCaps();
  }

  function populateCaps() {
      resetSelect(capSelect);

      const p = provSelect.value;
      const c = citySelect.value;
      if (!p || !c) return;

      const list = ALL_CAPS.filter(cap => cap.sigla_provincia === p && cap.denominazione_ita === c);

      list.forEach(cap => {
          const opt = document.createElement('option');
          opt.value = cap.cap;
          opt.textContent = cap.cap;
          if (cap.cap == initialCap) opt.selected = true;
          capSelect.appendChild(opt);
      });

      if (initialCap) capSelect.value = initialCap;
  }

  provSelect.addEventListener('change', () => {
      citySelect.dataset.current = '';
      capSelect.dataset.current = '';
      populateCities();
  });

  citySelect.addEventListener('change', () => {
      capSelect.dataset.current = '';
      populateCaps();
  });

  // init
  if (initialProv) {
      provSelect.value = initialProv;
      populateCities();
  }


  // ============================================================
  // LOTTI
  // ============================================================
  const lottiContainer = document.getElementById('lottiContainer');
  const addLottoBtn = document.getElementById('addLotto');
  let nextLottoIndex = {{ $lotti->count() }};

  if (addLottoBtn) {
      addLottoBtn.addEventListener('click', () => {
          const idx = nextLottoIndex++;

          lottiContainer.insertAdjacentHTML('beforeend', `
            <tr data-index="${idx}">
                <td class="lotto-index"></td>
                <td><input name="lotti[${idx}][nomeLotto]" class="form-control"></td>
                <td><input name="lotti[${idx}][descrizione]" class="form-control"></td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-lotto"><i class="fas fa-trash"></i></button>
                    <input type="hidden" name="lotti[${idx}][id]" value="">
                    <input type="hidden" name="lotti[${idx}][_delete]" value="0">
                </td>
            </tr>`);

          renumberLotti();
          rebuildConvTable();
      });
  }

  function renumberLotti() {
      lottiContainer.querySelectorAll('tr:not([data-deleted="1"])')
          .forEach((tr, i) => tr.querySelector('.lotto-index').textContent = i + 1);
  }

  document.addEventListener('click', e => {
      const btn = e.target.closest('.remove-lotto');
      if (!btn) return;

      const tr = btn.closest('tr');
      const id = tr.querySelector('input[name$="[id]"]').value;
      const del = tr.querySelector('input[name$="[_delete]"]');

      if (id) {
          del.value = 1;
          tr.dataset.deleted = "1";
          tr.style.display = "none";
      } else {
          tr.remove();
      }

      renumberLotti();
      rebuildConvTable();
  });


  // ============================================================
  // CONVENZIONI MULTI-SELECT
  // ============================================================
  function rebuildConvTable() {
    @if(!$isElevato) return; @endif

    const tbody = document.querySelector('#convTable tbody');
    tbody.innerHTML = '';

    const nomeAzienda      = @json($azienda->Nome);
    const associazioni     = @json($associazioni);
    const convAssocByLotto = @json($convAssocByLotto);

    const rows = lottiContainer.querySelectorAll('tr:not([data-deleted="1"])');

    rows.forEach((tr, visualIdx) => {
        const key = tr.dataset.index;
        const lottoName = tr.querySelector('input[name^="lotti"][name$="[nomeLotto]"]').value;
        const lottoId = tr.querySelector('input[name^="lotti"][name$="[id]"]').value;

        const preselected = lottoId && convAssocByLotto[lottoId]
            ? convAssocByLotto[lottoId].map(Number)
            : [];

        let options = '';
        associazioni.forEach(a => {
            const sel = preselected.includes(Number(a.idAssociazione)) ? 'selected' : '';
            options += `<option value="${a.idAssociazione}" ${sel}>${a.Associazione}</option>`;
        });

        const convName = lottoName ? `${nomeAzienda} - ${lottoName}` : nomeAzienda;

        tbody.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${visualIdx + 1}</td>
            <td>${convName}</td>
            <td>
                <select name="conv_assoc[${key}][]" class="form-select" multiple size="7">
                    ${options}
                </select>
            </td>
          </tr>
        `);
    });
  }

  renumberLotti();
  rebuildConvTable();

});
</script>
@endpush

