@extends('layouts.app')

@php
$nome = $user->firstname;
$ruolo = $roleName;
$lastLogin = $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : '';

@endphp

@section('content')
<h1>DIVERTITI LUCONE!</h1>

<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h4 class="card-title">Dati Anagrafici</h4>
      </div>
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
