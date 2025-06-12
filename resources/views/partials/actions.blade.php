<td>
  {{-- 🔍 Dettagli (solo se previsto) --}}
  {{-- <a href="{{ route('riepilogo.costi.show', $id) }}" class="btn btn-sm btn-primary me-1">Dettagli</a> --}}

  {{-- ✏️ Modifica --}}
  <a href="{{ route('riepilogo.costi.edit', $id) }}" class="btn btn-sm btn-warning me-1">
    Modifica
  </a>

  {{-- ❌ Elimina --}}
  <form action="{{ route('riepilogo.costi.destroy', $id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Sei sicuro di voler eliminare questa voce?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-danger">
      Elimina
    </button>
  </form>
</td>
