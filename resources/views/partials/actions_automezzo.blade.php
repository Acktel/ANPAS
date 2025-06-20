<td class="text-center">
  {{-- 👁️ Dettagli --}}
  <a href="{{ route('automezzi.show', $id) }}"
     class="btn btn-sm btn-anpas-green me-1"
     title="Dettagli">
    <i class="fas fa-info-circle"></i>
  </a>

  {{-- ✏️ Modifica --}}
  <a href="{{ route('automezzi.edit', $id) }}"
     class="btn btn-sm btn-anpas-edit me-1"
     title="Modifica">
    <i class="fas fa-edit"></i>
  </a>

  {{-- 🗑️ Elimina --}}
  <form action="{{ route('automezzi.destroy', $id) }}" method="POST"
        style="display:inline-block"
        onsubmit="return confirm('Eliminare definitivamente?')">
    @csrf
    @method('DELETE')
    <button type="submit"
            class="btn btn-sm btn-anpas-delete"
            title="Elimina">
      <i class="fas fa-trash-alt"></i>
    </button>
  </form>
</td>
