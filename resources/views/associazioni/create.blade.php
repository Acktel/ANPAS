@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mt-2">Crea Nuova Associazione</h1>

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
      <form action="{{ route('associazioni.store') }}" method="POST">
        @csrf

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="Associazione" class="form-label">Nome Associazione</label>
            <input type="text" class="form-control" id="Associazione" name="Associazione"
                   value="{{ old('Associazione') }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email') }}" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
              <label for="provincia" class="form-label">Provincia</label>
              <select class="form-control" id="provincia" name="provincia" required>
                  <option value="" disabled selected>-- Seleziona provincia --</option>
                  @php
                      $allowedProvinces = ['VC', 'AL', 'AT', 'BI', 'CN', 'NO', 'TO', 'VB'];
                      $provinceUniche = collect($cities)
                          ->pluck('sigla_provincia')
                          ->unique()
                          ->filter(fn($sigla) => in_array($sigla, $allowedProvinces))
                          ->sort()
                          ->values();
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
                  <input type="text" id="citta_combo" name="citta" class="form-control"
                          placeholder="Inizia a scrivere..." autocomplete="off" required
                          value="{{ old('citta', isset($associazione->citta) ? ucfirst(trim($associazione->citta)) : '') }}">
                  <ul id="citta_list" class="list-group position-absolute w-100" 
                      style="z-index:1000; display:none; max-height:200px; overflow-y:auto;">
                      @foreach($cities as $city)
                        <li class="list-group-item list-group-item-action"
                            data-provincia="{{ $city->sigla_provincia }}"
                            {{ old('citta', $associazione->citta ?? '') == trim($city->denominazione_ita) ? 'data-selected="true"' : '' }}>
                            {{ ucfirst(trim($city->denominazione_ita)) }}
                        </li>
                      @endforeach
                  </ul>
              </div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="indirizzo" class="form-label">Indirizzo</label>
            <input type="text" class="form-control" id="indirizzo" name="indirizzo"
                  value="{{ old('indirizzo') }}" required>
          </div>

              <div class="col-md-6 mb-3">
            <label for="adminuser_name" class="form-label">Nome Admin</label>
            <input type="text" class="form-control" id="adminuser_name" name="adminuser_name"
                   value="{{ old('adminuser_name') }}" required>
          </div>
        </div>

        
        <div class="row">

          <div class="col-md-6 mb-3">
            <label for="adminuser_email" class="form-label">Email Admin</label>
            <input type="email" class="form-control" id="adminuser_email" name="adminuser_email"
                   value="{{ old('adminuser_email') }}" required>
          </div>

            <div class="col-md-6">
              <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="3">{{ old('note') }}</textarea>
            </div>
        </div>
        

        </div>

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2"><i class="fas fa-check me-2"></i>Crea Associazione</button>
          <a href="{{ route('associazioni.index') }}" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Annulla</a>
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
    const list = document.getElementById('citta_list');
    const provinciaSelect = document.getElementById('provincia');

    function filterList() {
        const text = input.value.toLowerCase();
        const provincia = provinciaSelect.value;

        let hasVisible = false;
        Array.from(list.children).forEach(li => {
            const matchProvincia = li.dataset.provincia === provincia;
            const matchText = li.textContent.toLowerCase().includes(text);
            if (matchProvincia && matchText) {
                li.style.display = 'block';
                hasVisible = true;
            } else {
                li.style.display = 'none';
            }
        });

        list.style.display = hasVisible ? 'block' : 'none';

        // reset selezione se la città corrente non è più visibile
        if (list.querySelector('[data-selected="true"]') && list.querySelector('[data-selected="true"]').style.display === 'none') {
            input.value = '';
        }
    }

    // mostra lista al focus
    input.addEventListener('focus', filterList);
    input.addEventListener('input', filterList);
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // evita eventuale submit del form
            if (input.value.trim() !== '') {
                // prima lettera maiuscola
                input.value = input.value.trim();
                input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
            }
            list.style.display = 'none'; // chiudi dropdown se aperta
        }
    });
    provinciaSelect.addEventListener('change', filterList);

    // selezione con click
    Array.from(list.children).forEach(li => {
    li.addEventListener('click', () => {
        input.value = li.textContent.trim();
        // forza maiuscola della prima lettera
        input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
        list.style.display = 'none';
    });
    });

    // chiudi lista cliccando fuori
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.position-relative')) {
            list.style.display = 'none';
        }
    });

    // inizializza filtro se edit
    filterList();
});
</script>
@endpush