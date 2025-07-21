@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <h1 class="container-title mb-4">
    Convenzioni − Anno {{ session('anno_riferimento', now()->year) }}
  </h1>

  @if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="d-flex mb-3">
    <div class="ms-auto">
      <a href="{{ route('convenzioni.create') }}"
        class="btn btn-anpas-green">
        <i class="fas fa-plus me-1"></i> Nuova Convenzione
      </a>
    </div>
  </div>

  <div id="noDataMessage" class="alert alert-info d-none">
    Nessuna convenzione presente per l’anno {{ session('anno_riferimento', now()->year) }}.<br>
    Vuoi importare le convenzioni dall’anno precedente?
    <div class="mt-2">
      <button id="btn-duplica-si" class="btn btn-sm btn-anpas-green me-2">Sì</button>
      <button id="btn-duplica-no" class="btn btn-sm btn-secondary">No</button>
    </div>
  </div>

  <div class="card-anpas">
    <div class="card-body bg-anpas-white p-0">
      <table id="convenzioniTable"
        class="common-css-dataTable table table-hover table-striped table-bordered dt-responsive nowrap mb-0 table-striped-anpas">
        <thead class="thead-anpas">
          <tr>
            <th>ID</th>
            <th>Associazione</th>
            <th>Anno</th>
            <th>Descrizione</th>
            <th>Lettera</th>
            <th data-orderable="false" class="col-actions text-center">Azioni</th>
          </tr>
        </thead>
        <tbody id="sortable-convenzioni" class="sortable">
          @forelse($convenzioni as $c)
          <tr data-id="{{ $c->idConvenzione }}">
            <td>{{ $c->idConvenzione }}</td>
            <td>{{ $c->Associazione }}</td>
            <td>{{ $c->idAnno }}</td>
            <td>{{ $c->Convenzione }}</td>
            <td>{{ $c->lettera_identificativa }}</td>
            <td>
              <a href="{{ route('convenzioni.edit', $c->idConvenzione) }}"
                class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
                <i class="fas fa-edit"></i>
              </a>
              <form action="{{ route('convenzioni.destroy', $c->idConvenzione) }}"
                method="POST" class="d-inline"
                onsubmit="return confirm('Eliminare questa convenzione?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-3">Nessuna convenzione.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<!-- Sortable CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    if ($('#convenzioniTable tbody tr').length > 0 &&
      $('#convenzioniTable tbody tr td').length >= 6) {
      // DataTable init
      $('#convenzioniTable').DataTable({
        paging: false,
        info: false,
        className: 'col-actions text-center',
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.11.3/i18n/it_it.json'
        },
        rowCallback: function(row, data, index) {
          if (index % 2 === 0) {
            $(row).removeClass('even').removeClass('odd').addClass('even');
          } else {
            $(row).removeClass('even').removeClass('odd').addClass('odd');
          }
        },
        stripeClasses: ['table-white', 'table-striped-anpas'],
      });
    }
    // Sortable drag & drop
    const tbody = document.querySelector('#convenzioniTable tbody.sortable');
    if (tbody) {
      Sortable.create(tbody, {
        animation: 150,
        handle: 'td',
        ghostClass: 'table-warning',
        onEnd: function() {
          const ids = Array.from(tbody.querySelectorAll('tr')).map(tr => tr.dataset.id);
          fetch('/convenzioni/riordina', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
              order: ids
            })
          }).catch(() => alert('Errore nel riordino.'));
        }
      });
    }

    // Mostra messaggio duplicazione
    fetch("{{ route('convenzioni.checkDuplicazione') }}")
      .then(res => res.json())
      .then(data => {
        if (data.mostraMessaggio) {
          document.getElementById('noDataMessage').classList.remove('d-none');
        }
      });

    // Duplica
    document.getElementById('btn-duplica-si')?.addEventListener('click', async function() {
      const btn = this;
      btn.disabled = true;
      btn.innerText = 'Duplicazione…';

      const res = await fetch("{{ route('convenzioni.duplica') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        }
      });

      const json = await res.json();
      if (res.ok) location.reload();
      else {
        alert(json.message || 'Errore duplicazione');
        btn.disabled = false;
        btn.innerText = 'Sì';
      }
    });

    // Nascondi messaggio duplicazione
    document.getElementById('btn-duplica-no')?.addEventListener('click', function() {
      document.getElementById('noDataMessage').classList.add('d-none');
    });
  });
</script>
@endpush