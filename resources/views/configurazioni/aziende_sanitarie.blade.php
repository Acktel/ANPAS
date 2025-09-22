@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Configurazione Lotti Azienda Sanitaria</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card-anpas mb-4">
    <div class="card-body bg-anpas-white">

      {{-- Filtro Azienda --}}
      <form method="GET" action="{{ route('configurazioni.aziende_sanitarie') }}" class="row g-3 align-items-center mb-4">
        <div class="col-auto">
          <label for="idAziendaSanitaria" class="col-form-label">Azienda Sanitaria</label>
        </div>
        <div class="col-auto">
          <select name="idAziendaSanitaria" id="idAziendaSanitaria" class="form-select" onchange="this.form.submit()">
            <option value="" {{ empty($idAziendaSanitaria) ? 'selected' : '' }}>— seleziona —</option>
            @foreach($aziende as $az)
              <option value="{{ $az->idAziendaSanitaria }}" {{ $az->idAziendaSanitaria == $idAziendaSanitaria ? 'selected' : '' }}>
                {{ $az->Nome }}
              </option>
            @endforeach
          </select>
        </div>
      </form>

      @if(empty($idAziendaSanitaria))
        <div class="alert alert-info mb-0">
          Seleziona un’Azienda Sanitaria per visualizzare e gestire i lotti.
        </div>
      @else
        <div class="card-header bg-anpas-primary">
          <b>Lotti per Azienda Sanitaria</b> — inserire qui di seguito i lotti per l’azienda selezionata
        </div>

        {{-- Form aggiunta lotto (solo se azienda selezionata) --}}
        <div class="card-body bg-anpas-white p-0">
          <form method="POST" action="{{ route('configurazioni.aziende_sanitarie.store') }}" class="d-flex p-3 border-bottom">
            @csrf
            <input type="hidden" name="idAziendaSanitaria" value="{{ $idAziendaSanitaria }}">
            <input type="text" name="nomeLotto" class="form-control me-2" placeholder="Nuovo lotto" required>
            <button type="submit" class="btn btn-anpas-green">
              <i class="fas fa-plus me-1"></i> Aggiungi
            </button>
          </form>
        </div>

        {{-- Tabella Lotti filtrati --}}
        <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
          <thead class="thead-anpas">
            <tr>
              <th>ID</th>
              <th>Nome Lotto</th>
              <th style="width: 90px">Azioni</th>
            </tr>
          </thead>
          <tbody>
            @forelse($lotti as $lotto)
              <tr>
                <td>{{ $lotto->id }}</td>
                <td>{{ $lotto->nomeLotto }}</td>
                <td>
                  <form method="POST" action="{{ route('configurazioni.aziende_sanitarie.destroy', $lotto->id) }}" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-anpas-delete">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center py-3">Nessun lotto presente per l’azienda selezionata.</td></tr>
            @endforelse
          </tbody>
        </table>
      @endif

    </div>
  </div>
</div>
@endsection











@push('scripts')
  <script>
    (function () {
      // cerca prima un elemento con id, altrimenti prende il primo .alert.alert-success
      const flash = document.getElementById('flash-message') || document.querySelector('.alert.alert-success');
      if (!flash) return;

      // aspetta 3500ms (3.5s) poi fa fade + collapse e rimuove l'elemento
      setTimeout(() => {
        // animazione: opacità + altezza
        flash.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, padding 0.4s ease, margin 0.4s ease';
        flash.style.opacity = '0';
        // per lo "slide up" imposta max-height e padding a 0
        flash.style.maxHeight = flash.scrollHeight + 'px'; // inizializza
        // forza repaint per sicurezza
        // eslint-disable-next-line no-unused-expressions
        flash.offsetHeight;
        flash.style.maxHeight = '0';
        flash.style.paddingTop = '0';
        flash.style.paddingBottom = '0';
        flash.style.marginTop = '0';
        flash.style.marginBottom = '0';

        // rimuovi dal DOM dopo che l'animazione è finita
        setTimeout(() => {
          if (flash.parentNode) flash.parentNode.removeChild(flash);
        }, 600); // lascia un po' di tempo alla transizione
      }, 3500);
    })();
  </script>
@endpush