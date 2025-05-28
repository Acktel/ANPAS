@extends('layouts.app')

@section('title', 'Nuovo Automezzo')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        Nuovo Automezzo
      </h2>
    </div>
    <div class="col-auto ms-auto">
      <a href="{{ route('automezzi.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Indietro
      </a>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-body">
    <form action="{{ route('automezzi.store') }}" method="POST">
      @csrf

      {{-- Seleziona Associazione --}}
      <div class="mb-3">
        <label class="form-label">Associazione</label>
        <select name="idAssociazione" class="form-select @error('idAssociazione') is-invalid @enderror">
          <option value="">-- Scegli un'associazione --</option>
          @foreach($associazioni as $asso)
            <option value="{{ $asso->idAssociazione }}" {{ old('idAssociazione') == $asso->idAssociazione ? 'selected' : '' }}>
              {{ $asso->Associazione }}
            </option>
          @endforeach
        </select>
        @error('idAssociazione')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>

      {{-- Seleziona Anno --}}
      <div class="mb-3">
        <label class="form-label">Anno</label>
        <select name="idAnno" class="form-select @error('idAnno') is-invalid @enderror">
          <option value="">-- Scegli un anno --</option>
          @foreach($anni as $anno)
            <option value="{{ $anno->idAnno }}" {{ old('idAnno') == $anno->idAnno ? 'selected' : '' }}>
              {{ $anno->Anno }}
            </option>
          @endforeach
        </select>
        @error('idAnno')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>

      {{-- Nome Automezzo --}}
      <div class="mb-3">
        <label class="form-label">Nome Automezzo</label>
        <input
          type="text"
          name="Automezzo"
          class="form-control @error('Automezzo') is-invalid @enderror"
          placeholder="Es. Ambulanza A1"
          value="{{ old('Automezzo') }}"
        >
        @error('Automezzo')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save"></i> Salva
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
