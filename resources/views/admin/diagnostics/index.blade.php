@extends('components.layout')
@section('title','Diagnostics')

@section('content')
<div class="container py-4">
  <h1 class="mb-3">Diagnostics</h1>

  <div class="row">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">App</div>
        <div class="card-body">
          <ul class="list-group">
            <li class="list-group-item">Laravel: {{ $app['laravel'] }}</li>
            <li class="list-group-item">PHP: {{ $app['php'] }}</li>
            <li class="list-group-item">ENV: {{ $app['env'] }}</li>
            <li class="list-group-item">Debug: {{ $app['debug'] ? 'true' : 'false' }}</li>
            <li class="list-group-item">APP_URL: {{ $app['app_url'] }}</li>
          </ul>
        </div>
      </div>
    </div>

    @foreach($checks as $name => $c)
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header text-capitalize">{{ $name }}</div>
        <div class="card-body">
          @if(($c['ok'] ?? false) === true)
            <span class="badge bg-success">OK</span>
            <pre class="small mt-2">{{ print_r($c, true) }}</pre>
          @else
            <span class="badge bg-danger">FAIL</span>
            <div class="small text-danger mt-2">{{ $c['error'] ?? 'Unknown error' }}</div>
          @endif
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endsection
