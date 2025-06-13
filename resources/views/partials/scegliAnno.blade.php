{{-- resources/views/partials/scegliAnno.blade.php --}}

@auth
  <form method="POST" action="{{ route('anno.set') }}"
        class="d-flex align-items-center sceglianno-small p-2 bg-anpas-white rounded shadow-sm mb-0">
    @csrf
    <i class="fas fa-calendar-alt text-anpas-green me-2"></i>
    <input
      type="number"
      name="anno_riferimento"
      min="2020" max="{{ date('Y') }}" step="1"
      value="{{ session('anno_riferimento', date('Y')) }}"
      class="form-control form-control-sm text-center me-2"
      style="width: 4rem;"
    >
    <button type="submit" class="btn btn-sm btn-anpas-green">
      <i class="fas fa-check"></i>
    </button>
  </form>
@else
  <div class="sceglianno-small p-2 bg-anpas-white rounded shadow-sm mb-0 text-center">
    <i class="fas fa-lock text-anpas-red mb-1"></i>
    <div class="fw-bold text-anpas-red">Accedi per scegliere l’anno</div>
  </div>
@endauth
