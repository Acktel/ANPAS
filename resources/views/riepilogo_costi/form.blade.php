<form action="{{ $action }}" method="POST">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    <input type="hidden" name="idRiepilogo" value="{{ $idRiepilogo }}">
    <input type="hidden" name="idTipologiaRiepilogo" value="{{ $idTipologiaRiepilogo }}">

    <div class="card-anpas mb-4">
      <div class="card-body bg-anpas-white">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="descrizione" class="form-label">Descrizione</label>
            <input 
              type="text" 
              id="descrizione"
              name="descrizione" 
              class="form-control" 
              value="{{ old('descrizione', $dati->descrizione ?? '') }}" 
              required
            >
          </div>
          <div class="col-md-3 mb-3">
            <label for="preventivo" class="form-label">Preventivo</label>
            <input 
              type="number" 
              id="preventivo"
              name="preventivo" 
              step="0.01" 
              class="form-control" 
              value="{{ old('preventivo', $dati->preventivo ?? 0) }}"
            >
          </div>
          <div class="col-md-3 mb-3">
            <label for="consuntivo" class="form-label">Consuntivo</label>
            <input 
              type="number" 
              id="consuntivo"
              name="consuntivo" 
              step="0.01" 
              class="form-control" 
              value="{{ old('consuntivo', $dati->consuntivo ?? 0) }}"
            >
          </div>
        </div>

        <div class="text-center mt-4">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-save me-1"></i> Salva
          </button>
          <a href="{{ route('riepilogo.costi.index') }}" class="btn btn-secondary">
            Annulla
          </a>
        </div>
      </div>
    </div>
</form>
