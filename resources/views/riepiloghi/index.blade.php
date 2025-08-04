@extends('layouts.app')

@php $user = Auth::user(); @endphp

@section('content')
<div class="container-fluid">
  {{-- Titolo --}}
  <h1 class="container-title mb-4">Elenco Dati Caratteristici</h1>

  {{-- Filtro Associazione + Nuovo --}}
  <div class="d-flex mb-3">
    @if(auth()->user()->hasAnyRole(['SuperAdmin','Admin','Supervisor']))
      <div class="me-3">
        <label for="assocSelect" class="form-label">Associazione</label>
        <select id="assocSelect" class="form-select">
          @foreach($associazioni as $assoc)
            <option value="{{ $assoc->idAssociazione }}" {{ $assoc->idAssociazione == session('associazione_selezionata') ? 'selected' : '' }}>
              {{ $assoc->Associazione }}
            </option>
          @endforeach
        </select>
      </div>
    @endif
    <div class="ms-auto">
      <a href="{{ route('riepiloghi.create') }}" class="btn btn-anpas-green">
        <i class="fas fa-plus me-1"></i> Nuovo Riepilogo
      </a>
    </div>
  </div>

  {{-- Tabella --}}
  <table
    id="riepiloghiTable"
    class="common-css-dataTable table table-hover table-striped-anpas table-bordered dt-responsive nowrap w-100 mb-0">
    <thead class="thead-anpas">
      <tr>
        <th>Anno</th>
        <th>Descrizione</th>
        <th>ID Riepilogo</th>
        <th class="text-end">Preventivo</th>
        <th class="text-end">Consuntivo</th>
        <th class="text-center">Azioni</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.head.querySelector('meta[name="csrf-token"]').content;

    // 🔁 Cambia associazione → salva in sessione
    document.getElementById('assocSelect')?.addEventListener('change', function () {
      const idAssociazione = this.value;

      fetch("{{ route('sessione.setAssociazione') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ idAssociazione })
      }).then(() => location.reload());
    });

    // 🧾 DataTable
    $('#riepiloghiTable').DataTable({
      processing: true,
      serverSide: false,
      ajax: {
        url: "{{ route('riepiloghi.data') }}",
        data: function(d) {
          d.idAssociazione = '{{ session('associazione_selezionata') }}';
        },
        dataSrc: 'data'
      },
      columns: [
        { data: 'anno' },
        { data: 'descrizione' },
        { data: 'idRiepilogo' },
        { data: 'preventivo', className: 'text-end', render: $.fn.dataTable.render.number('.', ',', 2, '') },
        { data: 'consuntivo', className: 'text-end', render: $.fn.dataTable.render.number('.', ',', 2, '') },
        {
          data: 'actions_id',
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: function(id) {
            return `
              <a href="/riepiloghi/${id}" class="btn btn-sm btn-anpas-green me-1 btn-icon" title="Dettagli">
                <i class="fas fa-info-circle"></i>
              </a>
              <a href="/riepiloghi/${id}/edit" class="btn btn-sm btn-anpas-edit me-1 btn-icon" title="Modifica">
                <i class="fas fa-edit"></i>
              </a>
              <form action="/riepiloghi/${id}" method="POST" style="display:inline-block" onsubmit="return confirm('Confermi cancellazione?')">
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-sm btn-anpas-delete btn-icon" title="Elimina">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </form>
            `;
          }
        }
      ],
      language: { url: '/js/i18n/Italian.json' },
      rowCallback: function(row, data, index) {
        $(row).removeClass('even odd').addClass(index % 2 === 0 ? 'even' : 'odd');
      },
      stripeClasses: ['table-white', 'table-striped-anpas'],
      paging: true,
      searching: true,
      ordering: true,
    });
  });
</script>
@endpush
