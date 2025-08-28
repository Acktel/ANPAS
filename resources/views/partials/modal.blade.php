<!-- resources/views/riepilogo_costi/partials/modal.blade.php -->
<div class="modal fade" id="modalVoce" tabindex="-1" aria-labelledby="modalVoceLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="formVoce" method="POST" action="{{ route('riepilogo.costi.store', 0) }}">
      @csrf
      @method('POST')
      <input type="hidden" name="id">
      <input type="hidden" name="idTipologiaRiepilogo">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalVoceLabel">Aggiungi Voce</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="descrizione" class="form-label">Descrizione</label>
            <input type="text" class="form-control" name="descrizione" required>
          </div>
          <div class="mb-3">
            <label for="preventivo" class="form-label">Preventivo</label>
            <input type="number" class="form-control" name="preventivo" step=1.00" required>
          </div>
          <div class="mb-3">
            <label for="consuntivo" class="form-label">Consuntivo</label>
            <input type="number" class="form-control" name="consuntivo" step=1.00" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salva</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        </div>
      </div>
    </form>
  </div>
</div>
