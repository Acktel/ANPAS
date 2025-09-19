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

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="Associazione" class="form-label">Nome Associazione</label>
            <input type="text" class="form-control" id="Associazione" name="Associazione" style="text-transform: uppercase;"
                   value="{{ old('Associazione', $associazione->Associazione) }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email', $associazione->email) }}" required>
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
              <input type="text" class="form-control" id="indirizzo" name="indirizzo" style="text-transform: uppercase;"
                    value="{{ old('indirizzo', $associazione->indirizzo) }}" required>
            </div>

          <div class="col-md-6">
            <label for="note" class="form-label">Note</label>
              <textarea name="note" id="note" class="form-control" rows="3">{{ old('note', $associazione->note) }}</textarea>
          </div>
        </div>

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
          <button type="submit" class="btn btn-anpas-green me-2"><i class="fas fa-check me-1"></i>Aggiorna Associazione</button>
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

    /**
     * filterList({ ignoreText: boolean })
     * - se ignoreText = true: ignora il contenuto dell'input e mostra tutte le città della provincia selezionata
     * - se ignoreText = false: filtra anche per testo inserito
     */
    function filterList(options = {}) {
        const ignoreText = !!options.ignoreText;
        const provincia = provinciaSelect.value;

        // se non è selezionata una provincia, nascondi la lista
        if (!provincia) {
            list.style.display = 'none';
            return;
        }

        const text = ignoreText ? '' : input.value.trim().toLowerCase();

        let hasVisible = false;
        Array.from(list.children).forEach(li => {
            const matchProvincia = li.dataset.provincia === provincia;
            const matchText = text === '' ? true : li.textContent.toLowerCase().includes(text);
            if (matchProvincia && matchText) {
                li.style.display = 'block';
                hasVisible = true;
            } else {
                li.style.display = 'none';
            }
        });

        list.style.display = hasVisible ? 'block' : 'none';

        // se la li selezionata (data-selected="true") non è visibile, resetta input e selezione
        const selectedLi = list.querySelector('[data-selected="true"]');
        if (selectedLi && selectedLi.style.display === 'none') {
            input.value = '';
            selectedLi.removeAttribute('data-selected');
        }
    }

    // mostra tutte le città della provincia al focus (ignora il testo presente)
    input.addEventListener('focus', () => filterList({ ignoreText: true }));

    // mentre l'utente scrive filtra in base al testo
    input.addEventListener('input', () => {
        // digitando si rimuove la "selezione predefinita"
        Array.from(list.children).forEach(li => li.removeAttribute('data-selected'));
        filterList({ ignoreText: false });
    });

    // enter: capitalizza prima lettera e chiudi lista
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (input.value.trim() !== '') {
                input.value = input.value.trim();
                input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
            }
            list.style.display = 'none';
        }
    });

    // al cambio provincia mostra subito la lista delle città di quella provincia
    provinciaSelect.addEventListener('change', function() {
        // se la città precedente non appartiene alla nuova provincia, resettala
        const selectedLi = list.querySelector('[data-selected="true"]');
        if (selectedLi && selectedLi.dataset.provincia !== provinciaSelect.value) {
            selectedLi.removeAttribute('data-selected');
            input.value = '';
        }
        filterList({ ignoreText: true });
    });

    // click su una voce: imposta input, marca come selezionata e chiudi dropdown
    Array.from(list.children).forEach(li => {
        li.addEventListener('click', () => {
            input.value = li.textContent.trim();
            input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);

            // marca come selezionata e rimuovi la marcatura dalle altre
            Array.from(list.children).forEach(i => i.removeAttribute('data-selected'));
            li.setAttribute('data-selected', 'true');

            list.style.display = 'none';
        });
    });

    // chiudi lista cliccando fuori
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.position-relative')) {
            list.style.display = 'none';
        }
    });

    // inizializza (non aprire la lista al caricamento)
    filterList(); // imposta i display corretti
    list.style.display = 'none';
});
</script>
@endpush