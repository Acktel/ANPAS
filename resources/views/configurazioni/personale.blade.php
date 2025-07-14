@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Configurazioni → Personale</h1>

  {{-- Messaggi di successo --}}
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Errori di validazione --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row">
    {{-- QUALIFICHE --}}
    <div class="col-md-6">
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary text-white">Qualifiche</div>
        <div class="card-body bg-anpas-white p-0">
          <form action="{{ route('configurazioni.qualifiche.store') }}" method="POST" class="d-flex p-3 border-bottom">
            @csrf
            <input type="text" name="nome" class="form-control me-2" placeholder="Nome qualifica (es. AUTISTA)" required>
            <button type="submit" class="btn btn-anpas-green"><i class="fas fa-plus me-1"></i> Aggiungi</button>
          </form>

          <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>Qualifica</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              @forelse($qualifiche as $q)
                <tr>
                  <td>{{ $q->nome }}</td>
                  <td>
                    <form action="{{ route('configurazioni.qualifiche.destroy', $q->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center py-3">Nessuna qualifica.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- CONTRATTI APPLICATI --}}
    <div class="col-md-6">
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary text-white">Contratti Applicati</div>
        <div class="card-body bg-anpas-white p-0">
          <form action="{{ route('configurazioni.contratti.store') }}" method="POST" class="d-flex p-3 border-bottom">
            @csrf
            <input type="text" name="nome" class="form-control me-2" placeholder="Nome contratto (es. CCNL ANPAS)" required>
            <button type="submit" class="btn btn-anpas-green"><i class="fas fa-plus me-1"></i> Aggiungi</button>
          </form>

          <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>Contratto</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              @forelse($contratti as $c)
                <tr>
                  <td>{{ $c->nome }}</td>
                  <td>
                    <form action="{{ route('configurazioni.contratti.destroy', $c->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-center py-3">Nessun contratto.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- LIVELLI MANSIONE --}}
    <div class="col-md-6">
      <div class="card-anpas mb-4">
        <div class="card-header bg-anpas-primary text-white">Livelli Mansione</div>
        <div class="card-body bg-anpas-white p-0">
          <form action="{{ route('configurazioni.livelli.store') }}" method="POST" class="d-flex p-3 border-bottom">
            @csrf
            <input type="text" name="nome" class="form-control me-2" placeholder="Livello mansione (es. C4)" required>
            <button type="submit" class="btn btn-anpas-green"><i class="fas fa-plus me-1"></i> Aggiungi</button>
          </form>

          <table class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0">
            <thead class="thead-anpas">
              <tr>
                <th>Livello</th>
                <th>Azioni</th>
              </tr>
            </thead>
            <tbody>
              @forelse($livelli as $l)
                <tr>
                  <td>{{ $l->nome }}</td>
                  <td>
                    <form action="{{ route('configurazioni.livelli.destroy', $l->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confermi eliminazione?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-anpas-delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-center py-3">Nessun livello mansione.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
