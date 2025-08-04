@extends('layouts.app')
@php
  $assocCorr = \App\Models\Associazione::getById(session('associazione_selezionata', Auth::user()->IdAssociazione));
@endphp
@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Modifica Riepilogo #{{ $riepilogo->idRiepilogo }}
  </h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-anpas pb-5">
    <div class="card-body bg-anpas-white">
      <form id="mainForm" action="{{ route('riepiloghi.update', $riepilogo->idRiepilogo) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row mb-3">
          {{-- Associazione --}}
          <div class="col-md-6">
            <label class="form-label">Associazione</label>
            <input type="text" class="form-control" value="{{ $assocCorr->Associazione }}" readonly>
            <input type="hidden" name="idAssociazione" value="{{ $assocCorr->IdAssociazione }}">
          </div>

          {{-- Anno --}}
          <div class="col-md-6">
            <label for="idAnno" class="form-label">Anno Consuntivo</label>
            <select name="idAnno" id="idAnno" class="form-select" required>
              @foreach($anni as $annoRec)
                <option value="{{ $annoRec->idAnno }}"
                  {{ old('idAnno', $riepilogo->idAnno) == $annoRec->idAnno ? 'selected' : '' }}>
                  {{ $annoRec->anno }}
                </option>
              @endforeach
            </select>
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
              $oldDescr = old('riep_descrizione', $dati->pluck('descrizione')->toArray());
              $oldPrev = old('riep_preventivo', $dati->pluck('preventivo')->toArray());
              $oldCons = old('riep_consuntivo', $dati->pluck('consuntivo')->toArray());
            @endphp

            @forelse($oldDescr as $i => $descr)
              <tr>
                <td><input type="text" name="riep_descrizione[]" class="form-control" value="{{ $descr }}" maxlength="500"></td>
                <td><input type="text" name="riep_preventivo[]" class="form-control" value="{{ $oldPrev[$i] ?? '' }}"></td>
                <td><input type="text" name="riep_consuntivo[]" class="form-control" value="{{ $oldCons[$i] ?? '' }}"></td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-anpas-delete btn-remove-row">
                    <i class="fas fa-minus"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td><input type="text" name="riep_descrizione[]" class="form-control" maxlength="500"></td>
                <td><input type="text" name="riep_preventivo[]" class="form-control"></td>
                <td><input type="text" name="riep_consuntivo[]" class="form-control"></td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-anpas-delete btn-remove-row">
                    <i class="fas fa-minus"></i>
                  </button>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>

        <div class="mb-4">
          <button type="button" id="btn-add-row" class="btn btn-sm btn-secondary">
            <i class="fas fa-plus me-1"></i> Aggiungi Riga
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Footer fisso con bottoni --}}
<div class="fixed-footer bg-white border-top py-2 px-3 text-center shadow">
  <button type="submit" form="mainForm" class="btn btn-anpas-green me-2">
    <i class="fas fa-save me-1"></i> Aggiorna Riepilogo
  </button>
  <a href="{{ route('riepiloghi.index') }}" class="btn btn-secondary">
    Annulla
  </a>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Aggiungi riga
    document.getElementById('btn-add-row').addEventListener('click', function () {
      const tbody = document.querySelector('#riepilogoTable tbody');
      const newRow = document.createElement('tr');
      newRow.innerHTML = `
        <td><input type="text" name="riep_descrizione[]" class="form-control" maxlength="500"></td>
        <td><input type="text" name="riep_preventivo[]" class="form-control"></td>
        <td><input type="text" name="riep_consuntivo[]" class="form-control"></td>
        <td class="text-center">
          <button type="button" class="btn btn-sm btn-anpas-delete btn-remove-row">
            <i class="fas fa-minus"></i>
          </button>
        </td>
      `;
      tbody.appendChild(newRow);
    });

    // Rimuovi riga
    document.getElementById('riepilogoTable').addEventListener('click', function (e) {
      if (e.target.closest('.btn-remove-row')) {
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
