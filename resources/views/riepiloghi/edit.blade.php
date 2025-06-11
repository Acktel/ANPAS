@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Modifica Riepilogo #{{ $riepilogo->idRiepilogo }}</h1>

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form action="{{ route('riepiloghi.update', $riepilogo->idRiepilogo) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="row">
      <div class="col-md-4">
        <label for="idAssociazione" class="form-label">Nome Associazione</label>
        <select name="idAssociazione" id="idAssociazione" class="form-select" required>
          @foreach($associazioni as $asso)
          <option value="{{ $asso->idAssociazione }}"
            {{ old('idAssociazione', $riepilogo->idAssociazione) == $asso->idAssociazione ? 'selected' : '' }}>
            {{ $asso->Associazione }}
          </option>
          @endforeach
        </select>
      </div>

      {{-- Seleziona Anno --}}
      <div class="col-md-4">
        <label for="idAnno" class="form-label">Anno Consuntivo</label>
        <select name="idAnno" id="idAnno" class="form-select" required>
          @foreach($anni as $annoRecord)
          <option value="{{ $annoRecord->idAnno }}"
            {{ old('idAnno', $riepilogo->idAnno) == $annoRecord->idAnno ? 'selected' : '' }}>
            {{ $annoRecord->anno }}
          </option>
          @endforeach
        </select>
      </div>
    </div>
    {{-- Seleziona Associazione --}}

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
        {{-- Mostriamo le righe esistenti --}}
        @php
        $oldDescr = old('riep_descrizione', $dati->pluck('descrizione')->toArray());
        $oldPrev = old('riep_preventivo', $dati->pluck('preventivo')->toArray());
        $oldCons = old('riep_consuntivo', $dati->pluck('consuntivo')->toArray());
        @endphp

        @forelse($oldDescr as $i => $descr)
        <tr>
          <td>
            <input type="text"
              name="riep_descrizione[]"
              class="form-control"
              value="{{ $descr }}"
              maxlength="500">
          </td>
          <td>
            <input type="text"
              name="riep_preventivo[]"
              class="form-control"
              value="{{ $oldPrev[$i] ?? '' }}">
          </td>
          <td>
            <input type="text"
              name="riep_consuntivo[]"
              class="form-control"
              value="{{ $oldCons[$i] ?? '' }}">
          </td>
          <td class="text-center">
            <button type="button" class="btn btn-danger btn-remove-row">−</button>
          </td>
        </tr>
        @empty
        {{-- Se non ci sono dati salvati, mostriamo una riga vuota --}}
        <tr>
          <td><input type="text" name="riep_descrizione[]" class="form-control" maxlength="500"></td>
          <td><input type="text" name="riep_preventivo[]" class="form-control"></td>
          <td><input type="text" name="riep_consuntivo[]" class="form-control"></td>
          <td class="text-center">
            <button type="button" class="btn btn-danger btn-remove-row">−</button>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    <button type="button" class="btn btn-secondary col-md-4" id="btn-add-row">
      Aggiungi Riga
    </button> 

    <hr>
    <button type="submit" class="btn btn-primary">Aggiorna</button>
  </form>
</div>

@push('scripts')
<script>
  // Stesso script di aggiunta/rimozione righe già visto in create.blade.php
  document.addEventListener('DOMContentLoaded', function() {
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