@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Configurazioni Veicoli
  </h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="row">
    <div class="col-md-6">
      {{-- Tipologie Veicolo --}}
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary">
          <b>Tipologie Veicolo: </b>
          &nbsp;inserire qui di seguito le tipologie di veicolo (esempio: AMBULANZA TRAUMA)
        </div>
        <div class="card-body bg-anpas-white p-0">
          <form action="{{ route('configurazioni.tipologia-veicolo.store') }}" method="POST" class="d-flex p-3 border-bottom">
            @csrf
            <input type="text" name="nome" class="form-control me-2" placeholder="Nuova tipologia veicolo" required>
            <button type="submit" class="btn btn-anpas-green"><i class="fas fa-plus me-1"></i> Aggiungi</button>
          </form>

          <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              @forelse($vehicleTypes as $type)
                <tr>
                  <td>{{ $type->id }}</td>
                  <td>{{ $type->nome }}</td>
                  <td>
                    <form action="{{ route('configurazioni.tipologia-veicolo.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center py-3">Nessuna tipologia.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      {{-- Tipologie Carburante --}}
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary">
          <b>Tipologie Carburante:  </b>
          &nbsp;inserire qui di seguito le tipologie di carburante (esempio: DIESEL)
        </div>
        <div class="card-body bg-anpas-white p-0">
          <form action="{{ route('configurazioni.carburante.store') }}" method="POST" class="d-flex p-3 border-bottom">
            @csrf
            <input type="text" name="nome" class="form-control me-2" placeholder="Nuovo carburante" required>
            <button type="submit" class="btn btn-anpas-green"><i class="fas fa-plus me-1"></i> Aggiungi</button>
          </form>

          <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              @forelse($fuelTypes as $fuel)
                <tr>
                  <td>{{ $fuel->id }}</td>
                  <td>{{ $fuel->nome }}</td>
                  <td>
                    <form action="{{ route('configurazioni.carburante.destroy', $fuel->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center py-3">Nessun carburante.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
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