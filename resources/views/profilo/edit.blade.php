@extends('layouts.app')

@php

$nome = $user->firstname;

@endphp

@section('content')
<h1>DIVERTITI LUCONE!</h1>


<div class="row">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body text-center">
        <span class="avatar avatar-xl" style="background-image: url('{{ $user->avatar_url }}')"></span>
        <h3 class="mt-3">{{ $user->name }}</h3>
        <p class="text-muted">{{ $user->ruolo }}</p>
        <div>
          <a href="#" class="btn btn-outline-primary btn-sm">Modifica</a>
          <a href="#" class="btn btn-outline-danger btn-sm">Disattiva</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><h4 class="card-title">Dati Anagrafici</h4></div>
      <div class="card-body">
        <dl class="row">
          <dt class="col-sm-4">Email</dt>
          <dd class="col-sm-8">{{ $user->email }}</dd>

          <dt class="col-sm-4">Telefono</dt>
          <dd class="col-sm-8">{{ $user->telefono }}</dd>

          <dt class="col-sm-4">Ultimo Accesso</dt>
          <dd class="col-sm-8">{{ $user->last_login_at }}</dd>
        </dl>
      </div>
    </div>
  </div>
</div>


@endsection
