{{-- resources/views/configurazioni/riepilogo.blade.php --}}
@extends('layouts.app')

@section('title', 'Configurazioni — Voci Riepilogo')
@section('content')
<div class="container-fluid">
    <h1 class="container-title mb-4">Configurazioni — Voci Riepilogo</h1>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger mb-3">
        @foreach($errors->all() as $e)
        <div>{{ $e }}</div>
        @endforeach
    </div>
    @endif

    {{-- Sezioni per tipologia --}}
    @foreach($tipologie as $t)
        @continue($t->id == 12) {{-- nascondi Totale generale --}}
        @php $voci = $vociByTipologia[$t->id] ?? []; @endphp
        <div class="card-anpas mb-4">
            <div class="card-header bg-anpas-primary d-flex justify-content-between align-items-center">
                <b>{{ $t->id }} — {{ $t->descrizione }}</b>
                <small class="text-white-50">Trascina le righe per riordinare</small>
            </div>
            <div class="card-body bg-anpas-white p-0">
                <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
                    <thead class="thead-anpas">
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Descrizione</th>
                            <th style="width:140px;">Ordinamento</th>
                            <th style="width:90px;">Attivo</th>
                            <th style="width:120px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-{{ $t->id }}" data-tipologia="{{ $t->id }}">
                        @forelse($voci as $voce)
                        <tr data-id="{{ $voce->id }}">
                            <td class="drag-handle text-center">⋮⋮</td>
                           
                            <td>
                                <form action="{{ route('configurazioni.riepilogo.update', $voce->id) }}" method="POST" class="d-flex gap-2 align-items-center">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="idTipologiaRiepilogo" value="{{ $t->id }}">
                                    <input type="text" name="descrizione" class="form-control form-control-sm" value="{{ $voce->descrizione }}" required readonly>
                            </td>
                            <td>
                                <input type="number" name="ordinamento" class="form-control form-control-sm" value="{{ $voce->ordinamento }}" min="0">
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="attivo" value="0">
                                <input type="checkbox" name="attivo" value="1" {{ $voce->attivo ? 'checked' : '' }}>
                            </td>
                            <td class="text-nowrap text-center">
                                <button class="btn btn-sm btn-anpas-green me-1" title="Salva"><i class="fas fa-save"></i></button>
                                </form>
                               <!-- <form action="{{ route('configurazioni.riepilogo.destroy', $voce->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-anpas-delete" title="Elimina"><i class="fas fa-trash-alt"></i></button>
                                </form>-->
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-3">Nessuna voce configurata.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    document.querySelectorAll('tbody[id^="tbody-"]').forEach(initSortable);

    function initSortable(tbody) {
        let draggingEl = null;

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
                saveOrder(tbody);
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
    }

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

    function saveOrder(tbody) {
        const ids = [...tbody.querySelectorAll('tr[data-id]')].map(tr => tr.dataset.id);

        fetch("{{ route('configurazioni.riepilogo.reorder') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json','X-CSRF-TOKEN': token },
            body: JSON.stringify({ order: ids })
        })
        .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
        .then(res => { if (!res.ok) throw res; })
        .catch(err => {
            console.error('Riordino fallito', err);
            alert('Errore nel salvataggio dell’ordinamento.');
        });
    }
});
</script>

  <script>
    (function () {
      // cerca prima un elemento con id, altrimenti prende il primo .alert.alert-success
      const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
      if (!flash) return;

      // aspetta 3500ms (3.5s) poi fa fade + collapse e rimuove l'elemento
      setTimeout(() => {
        // animazione: opacità + altezza
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
        flash.style.opacity = '0';
        // per lo "slide up" imposta max-height e padding a 0
        flash.style.maxHeight = flash.scrollHeight + 'px'; // inizializza
        // forza repaint per sicurezza
        // eslint-disable-next-line no-unused-expressions
        flash.offsetHeight;
        flash.style.maxHeight = '0';
        flash.style.paddingTop = '0';
        flash.style.paddingBottom = '0';
        flash.style.marginTop = '0';
        flash.style.marginBottom = '0';

        // rimuovi dal DOM dopo che l'animazione è finita
        setTimeout(() => {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 600); // lascia un po' di tempo alla transizione
      }, 3500);
    })();
  </script>
@endpush
