{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  {{-- Header della pagina in stile Tabler --}}
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          {{ __('Dashboard') }}
        </h2>
      </div>
    </div>
  </div>

  {{-- Contenuto principale --}}
  <div class="page-body">
    <div class="row row-deck row-cards">
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            {{ __("You're logged in!") }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
