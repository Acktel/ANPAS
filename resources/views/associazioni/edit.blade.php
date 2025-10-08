@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4 mt-2">Modifica Associazione</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">
      <form action="{{ route('associazioni.update', $associazione->IdAssociazione) }}" method="POST">
        @csrf
        @method('PATCH')

        {{-- Nome + Email --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="Associazione" class="form-label">Nome Associazione</label>
            <input type="text"
                   class="form-control"
                   id="Associazione"
                   name="Associazione"
                   style="text-transform: uppercase;"
                   value="{{ old('Associazione', $associazione->Associazione) }}"
                   required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email"
                   class="form-control"
                   id="email"
                   name="email"
                   value="{{ old('email', $associazione->email) }}"
                   required>
          </div>
        </div>

        {{-- Provincia + Città --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="provincia" class="form-label">Provincia</label>
            <select class="form-control" id="provincia" name="provincia">
              <option value="" disabled {{ old('provincia', $associazione->provincia ?? '') ? '' : 'selected' }}>-- Seleziona provincia --</option>
              @php
                $allowedProvinces = ['VC','AL','AT','BI','CN','NO','TO','VB'];
                $provinceUniche = collect($cities)->pluck('sigla_provincia')->unique()
                  ->filter(fn($sigla) => in_array($sigla, $allowedProvinces))
                  ->sort()->values();
              @endphp
              @foreach($provinceUniche as $sigla)
                <option value="{{ $sigla }}" {{ old('provincia', $associazione->provincia ?? '') == $sigla ? 'selected' : '' }}>
                  {{ $sigla }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label for="citta_combo" class="form-label">Città</label>
            <div class="position-relative">
              <input type="text"
                     id="citta_combo"
                     name="citta"
                     class="form-control"
                     placeholder="Inizia a scrivere..."
                     autocomplete="off"
                     value="{{ old('citta', isset($associazione->citta) ? ucfirst(trim($associazione->citta)) : '') }}">
              <ul id="citta_list"
                  class="list-group position-absolute w-100"
                  style="z-index:1000; display:none; max-height:200px; overflow-y:auto;">
                @foreach($cities as $city)
                  <li class="list-group-item list-group-item-action"
                      data-provincia="{{ $city->sigla_provincia }}"
                      {{ old('citta', $associazione->citta ?? '') == trim($city->denominazione_ita) ? 'data-selected=true' : '' }}>
                    {{ ucfirst(trim($city->denominazione_ita)) }}
                  </li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>

        {{-- CAP + Indirizzo --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="cap_select" class="form-label">CAP</label>
            <select id="cap_select" name="cap" class="form-select" disabled>
              <option value="">-- Seleziona CAP --</option>
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label for="indirizzo" class="form-label">Indirizzo</label>
            <input type="text"
                   class="form-control"
                   id="indirizzo"
                   name="indirizzo"
                   style="text-transform: uppercase;"
                   value="{{ old('indirizzo', $associazione->indirizzo) }}"
                   >
          </div>
        </div>

        {{-- Note --}}
        <div class="row">
          <div class="col-md-12">
            <label for="note" class="form-label">Note</label>
            <textarea name="note" id="note" class="form-control" rows="3">{{ old('note', $associazione->note) }}</textarea>
          </div>
        </div>

        {{-- Dati amministratore (read-only) --}}
        @isset($adminUser)
          <hr>
          <h5 class="text-anpas-green mb-3">Dati Amministratore</h5>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="{{ $adminUser->username }}" disabled>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Email</label>
              <input type="text" class="form-control" value="{{ $adminUser->email }}" disabled>
            </div>
          </div>
        @endisset

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-check me-1"></i>Aggiorna Associazione
          </button>
          <a href="{{ route('associazioni.index') }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i>Annulla
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('citta_combo');
  const list  = document.getElementById('citta_list');
  const provinciaSelect = document.getElementById('provincia');

  // ===== CAP handling robusto =====
  const capSelect = document.getElementById('cap_select');
  const OLD_CAP   = @json(old('cap', $associazione->cap ?? ''));
  const CAPS_RAW  = @json($caps ?? []); // [{cap, sigla_provincia, denominazione_ita}]

  // Normalizza stringhe (minuscole, trim, accenti rimossi, spazi compressi)
  function norm(s) {
    return String(s || '')
      .trim()
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // rimuove accenti
      .replace(/\s+/g, ' ');
  }

  // Costruisci mappa: "prov|citta" -> [cap,...]
  const CAP_MAP = {};
  (CAPS_RAW || []).forEach(r => {
    const prov = norm(r.sigla_provincia);
    const city = norm(r.denominazione_ita);
    const key  = prov + '|' + city;
    (CAP_MAP[key] ??= []).push(String(r.cap));
  });

  function resetCap(){
    capSelect.innerHTML = '<option value="">-- Seleziona CAP --</option>';
    capSelect.disabled = true;
  }

  function populateCaps(){
    const prov = norm(provinciaSelect.value);
    const city = norm(input.value);
    if (!prov || !city) { resetCap(); return; }

    const key  = prov + '|' + city;
    const caps = CAP_MAP[key] || [];

    capSelect.innerHTML = '<option value="">-- Seleziona CAP --</option>' +
      caps.map(c => `<option value="${c}">${c}</option>`).join('');

    // Sblocco se ci sono CAP disponibili
    capSelect.disabled = caps.length === 0 ? true : false;

    // Ripristino old() se coerente
    if (OLD_CAP && caps.includes(String(OLD_CAP))) {
      capSelect.value = String(OLD_CAP);
    }
  }
  // ===== /CAP handling =====

  function filterList(options = {}) {
    const ignoreText = !!options.ignoreText;
    const provincia = provinciaSelect.value;
    if (!provincia) { list.style.display = 'none'; resetCap(); return; }

    const text = ignoreText ? '' : (input.value || '').trim().toLowerCase();

    let hasVisible = false;
    Array.from(list.children).forEach(li => {
      const matchProvincia = li.dataset.provincia === provincia;
      const matchText = text === '' ? true : li.textContent.toLowerCase().includes(text);
      li.style.display = (matchProvincia && matchText) ? 'block' : 'none';
      if (li.style.display === 'block') hasVisible = true;
    });

    list.style.display = hasVisible ? 'block' : 'none';

    const selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.style.display === 'none') {
      input.value = '';
      selectedLi.removeAttribute('data-selected');
      resetCap();
    }
  }

  // Eventi città/provincia
  input.addEventListener('focus', () => filterList({ ignoreText: true }));
  input.addEventListener('input', () => {
    Array.from(list.children).forEach(li => li.removeAttribute('data-selected'));
    filterList({ ignoreText: false });
    resetCap();
  });
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (input.value.trim() !== '') {
        input.value = input.value.trim();
        input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
      }
      list.style.display = 'none';
      populateCaps(); // conferma città -> carica CAP
    }
  });

  provinciaSelect.addEventListener('change', function() {
    const selectedLi = list.querySelector('[data-selected="true"]');
    if (selectedLi && selectedLi.dataset.provincia !== provinciaSelect.value) {
      selectedLi.removeAttribute('data-selected');
      input.value = '';
    }
    filterList({ ignoreText: true });
    resetCap();
  });

  Array.from(list.children).forEach(li => {
    li.addEventListener('click', () => {
      input.value = li.textContent.trim();
      input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
      Array.from(list.children).forEach(i => i.removeAttribute('data-selected'));
      li.setAttribute('data-selected','true');
      list.style.display = 'none';
      populateCaps(); // click su città -> carica CAP
    });
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.position-relative')) list.style.display = 'none';
  });

  // Init
  filterList();
  list.style.display = 'none';
  populateCaps(); // ripristina CAP se coerente
});
</script>
@endpush
