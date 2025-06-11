@extends('layouts.app')

@php $user = Auth::user(); @endphp

@section('content')
<div class="container-fluid">
  <h1>Elenco Dati Caratteristici</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('riepiloghi.create') }}" class="btn btn-primary mb-3">
    + Nuovo Riepilogo
  </a>

  <table id="riepiloghiTable" class="table table-striped">
    <thead>
      <tr>
        <th>Associazione</th>
        <th>Anno</th>
        <th>Descrizione</th>
        <th>ID Riepilogo</th>
        <th>Preventivo</th>
        <th>Consuntivo</th>
        <th>Azioni</th>
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

  $('#riepiloghiTable').DataTable({
    processing: true,
    serverSide: false, // se vuoi client-side; altrimenti true e implementa serverSide
    ajax: {
      url: "{{ route('riepiloghi.data') }}",
      dataSrc: 'data'
    },
    columns: [
      { data: 'Associazione' },
      { data: 'anno' },
      { data: 'descrizione' },
      { data: 'idRiepilogo' },
      { data: 'preventivo',
        render: $.fn.dataTable.render.number( '.', ',', 2, '' )
      },
      { data: 'consuntivo',
        render: $.fn.dataTable.render.number( '.', ',', 2, '' )
      },
      {
        data: 'actions_id',
        orderable: false,
        searchable: false,
        render: function(id) {
          return `
            <a href="/riepiloghi/${id}"             class="btn btn-sm btn-info">Dettagli</a>
            <a href="/riepiloghi/${id}/edit"        class="btn btn-sm btn-warning">Modifica</a>
            <form action="/riepiloghi/${id}" method="POST" style="display:inline-block;">
              <input type="hidden" name="_token"      value="${csrfToken}">
              <input type="hidden" name="_method"     value="DELETE">
              <button type="submit" class="btn btn-sm btn-danger"
                      onclick="return confirm('Confermi cancellazione?')">
                Elimina
              </button>
            </form>
          `;
        }
      }
    ],
    language: {
      url: "https://cdn.datatables.net/plug-ins/1.11.3/i18n/Italian.json"
    },
    paging:   true,
    searching:true,
    ordering: true
  });
});
</script>
@endpush
