<td class="text-center">
  {{-- ✏️ Modifica --}}
  <a href="{{ route('riepilogo.costi.edit', $id) }}"
     class="btn btn-sm btn-anpas-edit me-1"
     title="Modifica">
    <i class="fas fa-edit"></i>
  </a>

  {{-- 🗑️ Elimina --}}
  <form action="{{ route('riepilogo.costi.destroy', $id) }}" method="POST"
        style="display:inline-block"
        onsubmit="return confirm('Sei sicuro di voler eliminare questa voce?');">
    @csrf
    @method('DELETE')
    <button type="submit"
            class="btn btn-sm btn-anpas-delete"
            title="Elimina">
      <i class="fas fa-trash-alt"></i>
    </button>
  </form>
</td>
