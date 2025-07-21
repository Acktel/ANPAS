<a href="{{ route('riepilogo.costi.edit', $id) }}"
   class="btn btn-sm btn-anpas-edit me-1 btn-icon"
   title="Modifica">
  <i class="fas fa-edit"></i>
</a>

<form action="{{ route('riepilogo.costi.destroy', $id) }}"
      method="POST"
      style="display:inline"
      onsubmit="return confirm('Sei sicuro di voler eliminare questa voce?');">
  @csrf
  @method('DELETE')
  <button type="submit"
          class="btn btn-sm btn-anpas-delete btn-icon"
          title="Elimina">
    <i class="fas fa-trash-alt"></i>
  </button>
</form>
