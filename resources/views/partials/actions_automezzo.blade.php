<a href="{{ route('automezzi.show', $id) }}" class="btn btn-sm btn-primary me-1">Dettagli</a>
<a href="{{ route('automezzi.edit', $id) }}" class="btn btn-sm btn-warning me-1">Modifica</a>
<form action="{{ route('automezzi.destroy', $id) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Eliminare definitivamente?')">
  @csrf
  @method('DELETE')
  <button type="submit" class="btn btn-sm btn-danger">Elimina</button>
</form>
