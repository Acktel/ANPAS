@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">Dettaglio Azienda Sanitaria</h1>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white">

      {{-- Nome --}}
      <div class="mb-3">
        <label class="form-label fw-bold">Nome Azienda:</label>
        <div>{{ $azienda->Nome }}</div>
      </div>

      {{-- Indirizzo --}}
      <div class="mb-3">
        <label class="form-label fw-bold">Indirizzo:</label>
        <div>{{ $azienda->Indirizzo }}</div>
      </div>

      {{-- Email --}}
      <div class="mb-3">
        <label class="form-label fw-bold">Email:</label>
        <div>{{ $azienda->mail }}</div>
      </div>

      {{-- Convenzioni collegate --}}
      <div class="mb-3">
        <label class="form-label fw-bold">Convenzioni associate:</label>
        @if(!empty($convenzioni) && count($convenzioni) > 0)
          <ul class="mb-0">
            @foreach($convenzioni as $c)
              <li>{{ $c->Convenzione }} (Anno: {{ $c->idAnno }})</li>
            @endforeach
          </ul>
        @else
          <div>— Nessuna convenzione collegata —</div>
        @endif
      </div>

      {{-- Pulsanti --}}
      <div class="text-center mt-4">
        <a href="{{ route('aziende-sanitarie.edit', $azienda->id) }}" class="btn btn-anpas-edit me-2">
          <i class="fas fa-edit me-1"></i> Modifica
        </a>
        <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
          Torna alla lista
        </a>
      </div>

    </div>
  </div>
</div>
@endsection
