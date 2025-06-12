<form action="{{ $action }}" method="POST">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    <input type="hidden" name="idRiepilogo" value="{{ $idRiepilogo }}">
    <input type="hidden" name="idTipologiaRiepilogo" value="{{ $idTipologiaRiepilogo }}">

    <div class="mb-3">
        <label>Descrizione</label>
        <input type="text" name="descrizione" class="form-control" value="{{ old('descrizione', $dati->descrizione ?? '') }}" required>
    </div>
    <div class="mb-3">
        <label>Preventivo</label>
        <input type="number" step="0.01" name="preventivo" class="form-control" value="{{ old('preventivo', $dati->preventivo ?? 0) }}">
    </div>
    <div class="mb-3">
        <label>Consuntivo</label>
        <input type="number" step="0.01" name="consuntivo" class="form-control" value="{{ old('consuntivo', $dati->consuntivo ?? 0) }}">
    </div>

    <button type="submit" class="btn btn-primary">Salva</button>
</form>
