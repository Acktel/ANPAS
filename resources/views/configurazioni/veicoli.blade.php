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
        <div class="card-header bg-anpas-primary text-white">
          Tipologie Veicolo
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
        <div class="card-header bg-anpas-primary text-white">
          Tipologie Carburante
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
