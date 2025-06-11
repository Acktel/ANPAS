
@auth
@if(session()->has('impersonate') || auth()->user()->can('manage-own-association'))
@php
    $assocCorr = \App\Models\Associazione::getById(Auth::user()->IdAssociazione)->Associazione;
@endphp
    <div class="container-fluid text-center mb-4">
        {{-- Titolo centrato e azzurrino --}}
        <h1 class="text-primary mb-3">{{ $assocCorr }}</h1>

        {{-- Form centrato sotto il titolo --}}
        <form method="POST" action="{{ route('anno.set') }}" class="d-inline-flex align-items-center justify-content-center gap-2">
            @csrf
            <label for="idAnno" class="form-label mb-0">Consuntivo Anno:</label>
            <br>
            {{-- Input per l'anno con valore predefinito e range --}}
            <input
                type="number"
                name="anno_riferimento"
                id="idAnno"
                class="form-control form-control-sm text-center"
                min="2020"
                max="{{ date('Y') }}"
                step="1"
                value="{{ session('anno_riferimento', date('Y')) }}"
                style="width: 100px;">
            <button type="submit" class="btn btn-sm btn-outline-primary">↺</button>
        </form>
    </div>
@endif
@endauth

{{-- Se non autenticato, mostra solo il titolo --}}
@guest
    <div class="container-fluid text-center mb-4">
        <h1 class="text-primary mb-3">ANPAS</h1>
        <p class="text-muted">Accedi per visualizzare i dati della tua associazione</p>
    </div>
@endguest
