@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Nuovo Riepilogo Dati Caratteristici</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">
      <form id="form-riepilogo" action="{{ route('riepiloghi.store') }}" method="POST">
        @csrf

        @php
          use App\Models\Associazione;
          $user = Auth::user();
          $isImpersonating = session()->has('impersonate');
          $selectedAssociazione = old('idAssociazione', session('associazione_selezionata', $user->IdAssociazione));
          $assocCorr = Associazione::getById($selectedAssociazione);
          $annoCorr = old('idAnno', session('anno_riferimento', now()->year));
        @endphp

        <div class="row mb-3">
          {{-- Associazione --}}
          <div class="col-md-6">
            @if ($isImpersonating || $user->role_id == 4)
              <label class="form-label">Associazione</label>
              <input type="text" class="form-control" value="{{ $assocCorr->Associazione }}" readonly>
              <input type="hidden" name="idAssociazione" value="{{ $assocCorr->IdAssociazione }}">
            @else
              <label for="idAssociazione" class="form-label">Associazione</label>
              <select name="idAssociazione" id="idAssociazione" class="form-select" required>
                <option value="">-- Seleziona Associazione --</option>
                @foreach($associazioni as $asso)
                  <option value="{{ $asso->idAssociazione }}"
                    {{ $selectedAssociazione == $asso->idAssociazione ? 'selected' : '' }}>
                    {{ $asso->Associazione }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>

          {{-- Anno --}}
          <div class="col-md-6">
            <label for="idAnno" class="form-label">Anno Consuntivo</label>
            <input
              type="number"
              name="idAnno"
              id="idAnno"
              class="form-control"
              min="2020"
              max="{{ date('Y') + 5 }}"
              value="{{ $annoCorr }}"
              required
            >
          </div>
        </div>

        <hr>

        <h4 class="mb-3">Riepilogo Dati Caratteristici</h4>
        <table class="table table-bordered mb-3" id="riepilogoTable">
          <thead class="thead-anpas">
            <tr>
              <th>Descrizione</th>
              <th>Preventivo</th>
              <th>Consuntivo</th>
              <th class="text-center">Azioni</th>
            </tr>
          </thead>
          <tbody>
            @php
              $oldDescr = old('riep_descrizione', ['']);
              $oldPrev  = old('riep_preventivo', ['']);
              $oldCons  = old('riep_consuntivo', ['']);
            @endphp

            @foreach($oldDescr as $i => $desc)
              <tr>
                <td>
                  <input type="text" name="riep_descrizione[]" class="form-control" value="{{ $desc }}" maxlength="500">
                </td>
                <td>
                  <input type="text" name="riep_preventivo[]" class="form-control text-end" value="{{ $oldPrev[$i] ?? '' }}">
                </td>
                <td>
                  <input type="text" name="riep_consuntivo[]" class="form-control text-end" value="{{ $oldCons[$i] ?? '' }}">
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-anpas-delete btn-remove-row">
                    <i class="fas fa-minus"></i>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>

        <div class="mb-4">
          <button type="button" id="btn-add-row" class="btn btn-sm btn-secondary">
            <i class="fas fa-plus me-1"></i> Aggiungi Riga
          </button>
        </div>

        <div class="text-center mb-2">
          <button type="submit" class="btn btn-anpas-green me-2">
            <i class="fas fa-save me-1"></i> Salva Riepilogo
          </button>
          <a href="{{ route('riepiloghi.index', [
              'idAssociazione' => $selectedAssociazione,
              'idAnno' => $annoCorr
          ]) }}" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Annulla
          </a>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('riepilogoTable');

    // Aggiungi riga
    document.getElementById('btn-add-row').addEventListener('click', () => {
      const newRow = document.createElement('tr');
      newRow.innerHTML = `
        <td><input type="text" name="riep_descrizione[]" class="form-control" maxlength="500"></td>
        <td><input type="text" name="riep_preventivo[]" class="form-control text-end"></td>
        <td><input type="text" name="riep_consuntivo[]" class="form-control text-end"></td>
        <td class="text-center">
          <button type="button" class="btn btn-sm btn-anpas-delete btn-remove-row">
            <i class="fas fa-minus"></i>
          </button>
        </td>
      `;
      table.querySelector('tbody').appendChild(newRow);
    });

    // Rimuovi riga
    table.addEventListener('click', function (e) {
      if (e.target.closest('.btn-remove-row')) {
        const tbody = table.querySelector('tbody');
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
