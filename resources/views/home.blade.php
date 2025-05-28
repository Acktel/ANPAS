{{-- resources/views/home.blade.php --}}
@extends('layouts.app')

@section('title', $title)

@section('content')
  <h1 class="mb-4">{{ $title }}</h1>

  <table class="table table-striped">
    <thead>
      <tr>
        @foreach($columns as $col)
          <th>{{ ucfirst($col) }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($records as $row)
        <tr>
          @foreach($columns as $col)
            <td>{{ data_get($row, $col) }}</td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td colspan="{{ count($columns) }}">Nessun record trovato.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
@endsection
