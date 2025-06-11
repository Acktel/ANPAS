@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Modifica Convenzione #{{ $conv->idConvenzione }}</h1>
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul></div>
  @endif

  <form action="{{ route('convenzioni.update',$conv->idConvenzione) }}" method="POST">
    @csrf @method('PUT')
    <div class="mb-3">
      <label>Associazione</label>
      <select name="idAssociazione" class="form-select" required>
        @foreach($associazioni as $s)
          <option value="{{ $s->idAssociazione }}"
            {{ old('idAssociazione',$conv->idAssociazione)==$s->idAssociazione?'selected':'' }}>
            {{ $s->Associazione }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="mb-3">
      <label>Anno</label>
      <select name="idAnno" class="form-select" required>
        @foreach($anni as $a)
          <option value="{{ $a->idAnno }}"
            {{ old('idAnno',$conv->idAnno)==$a->idAnno?'selected':'' }}>
            {{ $a->anno }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="mb-3">
      <label>Descrizione</label>
      <input type="text" name="Convenzione" class="form-control"
             value="{{ old('Convenzione',$conv->Convenzione) }}" required>
    </div>
    <div class="mb-3">
      <label>Lettera</label>
      <input type="text" name="lettera_identificativa" class="form-control"
             value="{{ old('lettera_identificativa',$conv->lettera_identificativa) }}"
             maxlength="5" required>
    </div>
    <button class="btn btn-primary">Aggiorna</button>
  </form>
</div>
@endsection
