{{-- resources/views/aziende_sanitarie/edit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Modifica Azienda Sanitaria</h1>

  @if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('aziende-sanitarie.update', $azienda->idAziendaSanitaria) }}" method="POST">
    @csrf
    @method('PATCH')

    {{-- DATI AZIENDA --}}
    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">

        {{-- Nome (richiesto) --}}
        <div class="mb-3">
          <label for="Nome" class="form-label">Nome Azienda</label>
          <input
            type="text"
            name="Nome"
            id="Nome"
            class="form-control"
            required
            value="{{ old('Nome', $azienda->Nome) }}">
        </div>

        {{-- Indirizzo (opzionale) --}}
        <div class="mb-3">
          <label for="Indirizzo" class="form-label">Indirizzo</label>
          <input
            type="text"
            name="Indirizzo"
            id="Indirizzo"
            class="form-control"
            value="{{ old('Indirizzo', $azienda->Indirizzo ?? '') }}">
        </div>

        {{-- Email (opzionale) --}}
        <div class="mb-3">
          <label for="mail" class="form-label">Email</label>
          <input
            type="email"
            name="mail"
            id="mail"
            class="form-control"
            value="{{ old('mail', $azienda->mail ?? '') }}">
        </div>

        <hr>

        {{-- LOTTI: grafica/spaziature come configurazione --}}
        <div class="card-anpas mb-4">
          <div class="card-header bg-anpas-primary">
            <b>Lotti per Azienda Sanitaria</b> — Modifica i lotti per l’azienda
          </div>
          {{-- Tabella Lotti --}}
          <div class="card-body bg-anpas-white p-0">
            <table id="lottiTable" class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
              <thead class="thead-anpas">
                <tr>
                  <th style="width:70px">#</th>
                  <th>Nome Lotto</th>
                  <th>Descrizione</th>
                  <th style="width:90px">Azioni</th>
                </tr>
              </thead>
              <tbody>
                @php
                $oldLotti = old('lotti', []);
                $rows = count($oldLotti) ? collect($oldLotti) : $lotti;
                @endphp

                @foreach($rows as $i => $lotto)
                @php
                $id = is_array($lotto) ? ($lotto['id'] ?? null) : ($lotto->id ?? null);
                $nome = is_array($lotto) ? ($lotto['nomeLotto'] ?? '') : ($lotto->nomeLotto ?? '');
                $desc = is_array($lotto) ? ($lotto['descrizione'] ?? '') : ($lotto->descrizione ?? '');
                @endphp
                <tr data-row-index="{{ $i }}">
                  <input type="hidden" name="lotti[{{ $i }}][id]" value="{{ $id }}">
                  <input type="hidden" name="lotti[{{ $i }}][_delete]" value="0" class="lotto-delete">

                  <td class="text-muted">{{ $i + 1 }}</td>
                  <td>
                    <input type="text" name="lotti[{{ $i }}][nomeLotto]" class="form-control" value="{{ $nome }}">
                  </td>
                  <td>
                    <input type="text" name="lotti[{{ $i }}][descrizione]" class="form-control" value="{{ $desc }}">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <hr>

        {{-- Altri dati azienda --}}

        {{-- Convenzioni associate (opzionale, multiple) --}}
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="convenzioni" class="form-label">Convenzioni associate</label>
            @php $selected = collect(old('convenzioni', $convenzioniSelezionate)); @endphp
            <select
              name="convenzioni[]"
              id="convenzioni"
              class="form-select"
              multiple
              size="6">
              @foreach($convenzioni as $c)
              <option value="{{ $c->idConvenzione }}"
                {{ $selected->contains($c->idConvenzione) ? 'selected' : '' }}>
                {{ $c->Convenzione }}
              </option>
              @endforeach
            </select>
            <div class="form-text">Puoi selezionare una o più convenzioni</div>
          </div>

          {{-- Note (opzionale) --}}
          <div class="col-md-6">
            <label for="note" class="form-label">Note</label>
            <textarea name="note" id="note" class="form-control" rows="6">{{ old('note', $azienda->note ?? '') }}</textarea>
          </div>
        </div>

      </div>
    </div>

    {{-- Pulsanti --}}
    <div class="text-center">
      <button type="submit" class="btn btn-anpas-green me-3">
        <i class="fas fa-check me-1"></i>Salva Modifiche
      </button>
      <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">Annulla</a>
    </div>

  </form>
</div>
@endsection

@push('scripts')
<script>
  (function() {
    const tableBody = document.querySelector('#lottiTable tbody');
    const addBtn = document.getElementById('addLottoBtn');
    const inputNome = document.getElementById('newLottoNome');
    const inputDesc = document.getElementById('newLottoDesc');

    // indice iniziale (considera eventuali old())
    let nextIndex = (function() {
      const rows = tableBody.querySelectorAll('tr[data-row-index]');
      let max = -1;
      rows.forEach(tr => {
        const i = parseInt(tr.getAttribute('data-row-index'), 10);
        if (!isNaN(i) && i > max) max = i;
      });
      return max + 1;
    })();

    function escapeHtml(s) {
      return (s ?? '').toString()
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", "&#039;");
    }

    function renumberRows() {
      const trs = tableBody.querySelectorAll('tr[data-row-index]');
      trs.forEach((tr, idx) => {
        tr.setAttribute('data-row-index', idx);
        tr.querySelector('td').textContent = idx + 1;

        const idH = tr.querySelector('input[name^="lotti["][name$="[id]"]');
        const delH = tr.querySelector('input[name^="lotti["][name$="[_delete]"]');
        const nome = tr.querySelector('input[name^="lotti["][name$="[nomeLotto]"]');
        const desc = tr.querySelector('input[name^="lotti["][name$="[descrizione]"]');

        if (idH) idH.name = `lotti[${idx}][id]`;
        if (delH) delH.name = `lotti[${idx}][_delete]`;
        if (nome) nome.name = `lotti[${idx}][nomeLotto]`;
        if (desc) desc.name = `lotti[${idx}][descrizione]`;
      });
      nextIndex = trs.length;
    }

    function addRow(nomeVal = '', descVal = '') {
      const i = nextIndex++;
      const tr = document.createElement('tr');
      tr.setAttribute('data-row-index', i);

      tr.innerHTML = `
      <input type="hidden" name="lotti[${i}][id]" value="">
      <input type="hidden" name="lotti[${i}][_delete]" value="0" class="lotto-delete">

      <td class="text-muted">${i + 1}</td>
      <td>
        <input type="text" name="lotti[${i}][nomeLotto]" class="form-control" value="${escapeHtml(nomeVal)}">
      </td>
      <td>
        <input type="text" name="lotti[${i}][descrizione]" class="form-control" value="${escapeHtml(descVal)}">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-anpas-delete js-remove-row" title="Elimina">
          <i class="fas fa-trash-alt"></i>
        </button>
      </td>
    `;

      tableBody.appendChild(tr);
    }

    addBtn.addEventListener('click', function() {
      const nome = (inputNome.value || '').trim();
      const desc = (inputDesc.value || '').trim();
      if (!nome) {
        inputNome.focus();
        return;
      }
      addRow(nome, desc);
      inputNome.value = '';
      inputDesc.value = '';
    });

    tableBody.addEventListener('click', function(e) {
      const btn = e.target.closest('.js-remove-row');
      if (!btn) return;

      const tr = btn.closest('tr');
      const idInput = tr.querySelector('input[name$="[id]"]');
      const delInput = tr.querySelector('input[name$="[_delete]"]');

      // Se riga esistente: marca per eliminazione e nascondi
      if (idInput && idInput.value) {
        delInput.value = '1';
        tr.style.display = 'none';
      } else {
        // Riga nuova: rimuovi e rinumera
        tr.remove();
        renumberRows();
      }
    });
  })();
</script>
@endpush