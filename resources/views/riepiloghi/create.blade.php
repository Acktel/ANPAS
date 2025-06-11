@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Nuovo Riepilogo Dati Caratteristici</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('riepiloghi.store') }}" method="POST">
    @csrf

    {{-- Seleziona Associazione --}}
    <div class="mb-3">
      <label for="idAssociazione" class="form-label">Nome Associazione</label>
      <select name="idAssociazione" id="idAssociazione" class="form-select" required>
        <option value="">-- Seleziona Associazione --</option>
        @foreach($associazioni as $asso)
          <option value="{{ $asso->idAssociazione }}"
            {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}>
            {{ $asso->Associazione }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- Seleziona Anno (spinner numerico) --}}
    <div class="mb-3">
    <label for="idAnno" class="form-label">Anno Consuntivo</label>
    <input 
        type="number" 
        name="idAnno" 
        id="idAnno" 
        class="form-control" 
        min="2020" 
        max="{{ date('Y') + 5 }}" 
        step="1" 
        value="{{ old('idAnno') }}" 
        placeholder="Inserisci l'anno, es. 2024"
        >
    </div>

    <hr>

    {{-- RIEPILOGO DATI CARATTERISTICI --}}
    <h4>RIEPILOGO DATI CARATTERISTICI</h4>
    <table class="table table-bordered" id="riepilogoTable">
      <thead>
        <tr>
          <th>DESCRIZIONE</th>
          <th>PREVENTIVO</th>
          <th>CONSUNTIVO</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><input type="text" name="riep_descrizione[]" class="form-control"
                     value="{{ old('riep_descrizione.0') }}" maxlength="500"></td>
          <td><input type="text" name="riep_preventivo[]" class="form-control"
                     value="{{ old('riep_preventivo.0') }}"></td>
          <td><input type="text" name="riep_consuntivo[]" class="form-control"
                     value="{{ old('riep_consuntivo.0') }}" ></td>
          <td class="text-center">
            <button type="button" class="btn btn-danger btn-remove-row">−</button>
          </td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="btn btn-secondary mb-3" id="btn-add-row">
      Aggiungi Riga
    </button>
    <hr>
    <button type="submit" class="btn btn-primary">Salva Tutto</button>
  </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // RIEPILOGO DATI: aggiungi riga
  document.getElementById('btn-add-row').addEventListener('click', function() {
    const tbody = document.querySelector('#riepilogoTable tbody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
      <td><input type="text" name="riep_descrizione[]" class="form-control" maxlength="500"></td>
      <td><input type="text" name="riep_preventivo[]" class="form-control"></td>
      <td><input type="text" name="riep_consuntivo[]" class="form-control"></td>
      <td class="text-center"><button type="button" class="btn btn-danger btn-remove-row">−</button></td>
    `;
    tbody.appendChild(newRow);
  });

  // RIEPILOGO DATI: rimuovi riga
  document.getElementById('riepilogoTable').addEventListener('click', function(e) {
    if (e.target.matches('.btn-remove-row')) {
      const tbody = this.querySelector('tbody');
      if (tbody.rows.length > 1) {
        e.target.closest('tr').remove();
      } else {
        alert('Deve rimanere almeno una riga di riepilogo dati.');
      }
    }
  });

});
</script>
@endpush
@endsection
