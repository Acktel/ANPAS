@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <h1 class="container-title mb-4">Dettaglio Azienda Sanitaria</h1>

    <div class="card-anpas mb-4">
        <div class="card-body bg-anpas-white">

            {{-- NAV TABS --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-anagrafica">
                        Anagrafica
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-lotti">
                        Lotti
                    </button>
                </li>

                @if($isElevato)
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-conv">
                        Convenzioni
                    </button>
                </li>
                @endif
            </ul>

            <div class="tab-content">

                {{-- TAB ANAGRAFICA --}}
                <div class="tab-pane fade show active" id="pane-anagrafica">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome Azienda</label>
                        <div class="form-control-plaintext">{{ $azienda->Nome }}</div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Provincia</label>
                            <div class="form-control-plaintext">{{ $azienda->provincia ?: '—' }}</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Città</label>
                            <div class="form-control-plaintext">{{ $azienda->citta ?: '—' }}</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">CAP</label>
                            <div class="form-control-plaintext">{{ $azienda->cap ?: '—' }}</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Indirizzo</label>
                        <div class="form-control-plaintext">
                            {{ trim(($azienda->indirizzo_via ?? '').' '.($azienda->indirizzo_civico ?? '')) 
                                ?: ($azienda->Indirizzo ?? '—') }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <div class="form-control-plaintext">{{ $azienda->mail ?: '—' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Note</label>
                        <div class="form-control-plaintext">{{ $azienda->note ?: '—' }}</div>
                    </div>

                </div>

                {{-- TAB LOTTI --}}
                <div class="tab-pane fade" id="pane-lotti">

                    <div class="fw-bold mb-2">Lotti presenti:</div>
                    <div class="form-control-plaintext mb-3">
                        {{ $lotti->count() ? 'Sì' : 'No' }}
                    </div>

                    @if($lotti->count())
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome Lotto</th>
                                <th>Descrizione</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lotti as $i => $lotto)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $lotto->nomeLotto }}</td>
                                <td>{{ $lotto->descrizione }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                        <div class="form-control-plaintext">— Nessun lotto presente —</div>
                    @endif

                </div>

                {{-- TAB CONVENZIONI --}}
                @if($isElevato)
                <div class="tab-pane fade" id="pane-conv">

                    <div class="alert alert-info">
                        Convenzioni collegate a questa azienda sanitaria.
                    </div>

                    @if(!empty($convenzioni) && count($convenzioni))
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Convenzione</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($convenzioni as $i => $conv)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $conv->Convenzione }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                        <div class="form-control-plaintext">— Nessuna convenzione collegata —</div>
                    @endif

                </div>
                @endif

            </div>

            {{-- PULSANTI --}}
            <div class="text-center mt-4">

                @can('manage-all-associations')
                <a href="{{ route('aziende-sanitarie.edit', $azienda->idAziendaSanitaria) }}"
                    class="btn btn-anpas-edit me-2">
                    <i class="fas fa-edit me-1"></i> Modifica
                </a>
                @endcan

                <a href="{{ route('aziende-sanitarie.index') }}" class="btn btn-secondary">
                    Torna alla lista
                </a>
            </div>

        </div>
    </div>

</div>
@endsection
