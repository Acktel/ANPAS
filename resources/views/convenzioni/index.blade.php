@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1>Convenzioni</h1>
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('convenzioni.create') }}" class="btn btn-primary mb-3">+ Nuova</a>

  <div id="noDataMessage" class="alert alert-info d-none">
  Nessuna convenzione presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
  Vuoi importare le convenzioni dall’anno precedente?
  <div class="mt-2">
    <button id="btn-duplica-si" class="btn btn-sm btn-success">Sì</button>
    <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
  </div>
</div>

  <table class="table table-hover table-bordered dt-responsive nowrap">
    <thead>
      <tr>
        <th>ID</th><th>Associazione</th><th>Anno</th>
        <th>Descrizione</th><th>Lettera identificativa</th><th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @forelse($convenzioni as $c)
        <tr>
          <td>{{ $c->idConvenzione }}</td>
          <td>{{ $c->Associazione }}</td>
          <td>{{ $c->idAnno }}</td>
          <td>{{ $c->Convenzione }}</td>
          <td>{{ $c->lettera_identificativa }}</td>
          <td>
            <a href="{{ route('convenzioni.edit',$c->idConvenzione) }}"
               class="btn btn-sm btn-warning">Modifica</a>
            <form action="{{ route('convenzioni.destroy',$c->idConvenzione) }}"
                  method="POST" class="d-inline"
                  onsubmit="return confirm('Eliminare?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-danger">Elimina</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center">Nessuna convenzione.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  
  fetch("{{ route('convenzioni.checkDuplicazione') }}")
    .then(res => res.json())
    .then(data => {
      console.log(data);
      if (data.mostraMessaggio) {
        document.getElementById('noDataMessage').classList.remove('d-none');
      }
    });

  document.getElementById('btn-duplica-si')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerText = 'Duplicazione...';

    const res = await fetch("{{ route('convenzioni.duplica') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json'
      }
    });

    const json = await res.json();
    if (res.ok) {
      location.reload();
    } else {
      alert(json.message || 'Errore duplicazione');
      btn.disabled = false;
      btn.innerText = 'Sì';
    }
  });

  document.getElementById('btn-duplica-no')?.addEventListener('click', function () {
    document.getElementById('noDataMessage').classList.add('d-none');
  });
});
</script>
@endpush
