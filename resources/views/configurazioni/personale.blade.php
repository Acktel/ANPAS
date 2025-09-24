@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Configurazioni → Personale</h1>

  {{-- ✅ Successo --}}
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- ⚠️ Errori --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row">
    {{-- 🔹 QUALIFICHE (solo ordina/attiva) --}}
    <div class="col-md-6">
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary d-flex justify-content-between align-items-center">
          <b>Qualifiche</b>
          <small class="text-white-50">Trascina le righe per riordinare</small>
        </div>

        <div class="card-body bg-anpas-white p-0">
          <table class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th style="width:40px;"></th>
                <th>Qualifica</th>
                <th style="width:140px;">Ordinamento</th>
                <th style="width:90px;">Attivo</th>
                <th style="width:120px;" class="text-center">Azioni</th>
              </tr>
            </thead>
            <tbody id="tbody-qualifiche">
              @forelse($qualifiche as $q)
                <tr data-id="{{ $q->id }}">
                  <td class="drag-handle text-center">⋮⋮</td>
                  <td>{{ $q->nome }}</td>
                  <td>
                    <form action="{{ route('configurazioni.qualifiche.update', $q->id) }}" method="POST" class="d-flex gap-2 align-items-center">
                      @csrf
                      @method('PUT')
                      <input type="number" name="ordinamento" class="form-control form-control-sm" value="{{ $q->ordinamento ?? 0 }}" min="0">
                  </td>
                  <td class="text-center">
                      <input type="hidden" name="attivo" value="0">
                      <input type="checkbox" name="attivo" value="1" {{ !empty($q->attivo) ? 'checked' : '' }}>
                  </td>
                  <td class="text-center">
                      <button class="btn btn-sm btn-anpas-green" title="Salva">
                        <i class="fas fa-save"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center py-3">Nessuna qualifica configurata.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- 📜 CONTRATTI (come prima: aggiungi/elimina) --}}
    <div class="col-md-6">
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary ">
          <b>Contratti Applicati</b>
        </div>
        <div class="card-body bg-anpas-white p-0">
          <form action="{{ route('configurazioni.contratti.store') }}" method="POST" class="d-flex p-3 border-bottom">
            @csrf
            <input type="text" name="nome" class="form-control me-2" placeholder="Nome contratto (es. CCNL ANPAS)" required>
            <button type="submit" class="btn btn-anpas-green">
              <i class="fas fa-plus me-1"></i> Aggiungi
            </button>
          </form>

          <table class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>Contratto</th>
                <th class="text-center">Azioni</th>
              </tr>
            </thead>
            <tbody>
              @forelse($contratti as $c)
                <tr>
                  <td>{{ $c->nome }}</td>
                  <td class="text-center">
                    <form action="{{ route('configurazioni.contratti.destroy', $c->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-anpas-delete">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-center py-3">Nessun contratto.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const tbody = document.getElementById('tbody-qualifiche');

  if (!tbody) return;

  let draggingEl = null;

  // Abilita drag solo sul "manico"
  tbody.querySelectorAll('tr').forEach(tr => {
    const handle = tr.querySelector('.drag-handle');
    if (!handle) return;

    handle.addEventListener('mousedown', () => tr.setAttribute('draggable', 'true'));
    handle.addEventListener('mouseup',   () => tr.removeAttribute('draggable'));

    tr.addEventListener('dragstart', (e) => {
      draggingEl = tr;
      tr.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', tr.dataset.id);
    });

    tr.addEventListener('dragend', () => {
      tr.classList.remove('dragging');
      tr.removeAttribute('draggable');
      draggingEl = null;
      saveOrder(); // salva al termine del drag
    });
  });

  tbody.addEventListener('dragover', (e) => {
    e.preventDefault();
    const afterEl = getDragAfterElement(tbody, e.clientY);
    if (!draggingEl) return;
    if (afterEl == null) {
      tbody.appendChild(draggingEl);
    } else {
      tbody.insertBefore(draggingEl, afterEl);
    }
  });

  function getDragAfterElement(container, y) {
    const els = [...container.querySelectorAll('tr:not(.dragging)')];
    let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
    for (const child of els) {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        closest = { offset, element: child };
      }
    }
    return closest.element;
  }

  function saveOrder() {
    const ids = [...tbody.querySelectorAll('tr[data-id]')].map(tr => tr.dataset.id);

    fetch("{{ route('configurazioni.qualifiche.reorder') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({ order: ids })
    })
    .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
    .then(res => { if (!res.ok && res.ok !== true) throw res; })
    .catch(err => {
      console.error('Riordino qualifiche fallito', err);
      alert('Errore nel salvataggio dell’ordinamento delle qualifiche.');
    });
  }
});
</script>
@endpush
