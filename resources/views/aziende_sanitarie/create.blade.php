{{-- resources/views/aziende_sanitarie/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Nuova Azienda Sanitaria</h1>

  @if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('aziende-sanitarie.store') }}" method="POST">
    @csrf

    {{-- DATI AZIENDA --}}
    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">
        {{-- Nome (richiesto) --}}
        <div class="mb-3">
          <label for="Nome" class="form-label">Nome Azienda</label>
          <input type="text" name="Nome" id="Nome" class="form-control" required value="{{ old('Nome') }}">
        </div>

        {{-- Indirizzo (opzionale) --}}
        <div class="mb-3">
          <label for="Indirizzo" class="form-label">Indirizzo</label>
          <input type="text" name="Indirizzo" id="Indirizzo" class="form-control" value="{{ old('Indirizzo') }}">
        </div>

        {{-- Email (opzionale) --}}
        <div class="mb-3">
          <label for="mail" class="form-label">Email</label>
          <input type="email" name="mail" id="mail" class="form-control" value="{{ old('mail') }}">
        </div>

        <hr>


        <div class="card-body bg-anpas-white">
          {{-- “Filtro Azienda” NON serve in create, quindi lo saltiamo --}}

          {{-- Header coerente --}}
          <div class="card-header bg-anpas-primary">
            <label class="form-label">Inserire qui di seguito i lotti per l’azienda</label>
          </div>

          {{-- Form aggiunta lotto (inline, no wrap) --}}
          <div class="card-body bg-anpas-white p-0">
            <div class="d-flex p-3 border-bottom align-items-start flex-nowrap gap-2 overflow-auto">
              <input type="text" id="newLottoNome"
                class="form-control me-2 flex-shrink-0"
                style="width:280px"
                placeholder="Nome lotto">

              <input type="text" id="newLottoDesc"
                class="form-control me-2 flex-shrink-0"
                style="width:420px"
                placeholder="Descrizione (opzionale)">

              <button type="button" id="addLottoBtn"
                class="btn btn-anpas-green flex-shrink-0">
                <i class="fas fa-plus me-1"></i> Aggiungi
              </button>
            </div>
          </div>

          {{-- Tabella Lotti (stessa skin della config) --}}
          <table id="lottiTable" class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th style="width: 70px">#</th>
                <th>Nome Lotto</th>
                <th>Descrizione</th>
                <th style="width: 90px">Azioni</th>
              </tr>
            </thead>
            <tbody>
              {{-- Se ci sono old() dalla validazione, ripristino --}}
              @php $oldLotti = array_values(old('lotti', [])); @endphp
              @foreach($oldLotti as $i => $lotto)
              <tr data-row-index="{{ $i }}">
                <td class="text-muted">{{ $i + 1 }}</td>
                <td>
                  <input type="text" name="lotti[{{ $i }}][nomeLotto]" class="form-control" value="{{ $lotto['nomeLotto'] ?? '' }}">
                </td>
                <td>
                  <input type="text" name="lotti[{{ $i }}][descrizione]" class="form-control" value="{{ $lotto['descrizione'] ?? '' }}">
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
        <hr>
        <div class="row">
          {{-- Convenzioni (opzionale, multiple) --}}
          <div class="col-md-6 mb-3">
            <label for="convenzioni" class="form-label">Convenzioni associate</label>
            <select name="convenzioni[]" id="convenzioni" class="form-select" multiple>
              @foreach($convenzioni as $c)
              <option value="{{ $c->idConvenzione }}" {{ collect(old('convenzioni'))->contains($c->idConvenzione) ? 'selected' : '' }}>
                {{ $c->Convenzione }}
              </option>
              @endforeach
            </select>
            <div class="form-text">Puoi selezionare una o più convenzioni</div>
          </div>

          {{-- Note (opzionale) --}}
          <div class="col-md-6">
            <label for="note" class="form-label">Note</label>
            <textarea name="note" id="note" class="form-control" rows="4">{{ old('note') }}</textarea>
          </div>
        </div>
      </div>
    </div>


    <div class="text-center">
      <button type="submit" class="btn btn-anpas-green me-3">
        <i class="fas fa-check me-1"></i>Salva Azienda
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

    // calcola l'indice successivo partendo da eventuali old()
    let nextIndex = (function() {
      const rows = tableBody.querySelectorAll('tr[data-row-index]');
      let max = -1;
      rows.forEach(tr => {
        const i = parseInt(tr.getAttribute('data-row-index'), 10);
        if (!isNaN(i) && i > max) max = i;
      });
      return max + 1;
    })();

    function renumberRows() {
      const trs = tableBody.querySelectorAll('tr[data-row-index]');
      trs.forEach((tr, idx) => {
        tr.setAttribute('data-row-index', idx);
        tr.querySelector('td').textContent = idx + 1;
        const nome = tr.querySelector('input[name^="lotti["][name$="[nomeLotto]"]');
        const desc = tr.querySelector('input[name^="lotti["][name$="[descrizione]"]');
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

    function escapeHtml(s) {
      return (s ?? '').toString()
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", "&#039;");
    }

    addBtn.addEventListener('click', function() {
      const nome = (inputNome.value || '').trim();
      const desc = (inputDesc.value || '').trim();

      if (!nome) {
        inputNome.focus();
        return;
      }

      addRow(nome, desc);
      // reset input inline
      inputNome.value = '';
      inputDesc.value = '';
    });

    tableBody.addEventListener('click', function(e) {
      const btn = e.target.closest('.js-remove-row');
      if (!btn) return;
      const tr = btn.closest('tr');
      tr.remove();
      renumberRows();
    });
  })();
</script>
@endpush