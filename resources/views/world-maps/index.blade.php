@extends('layouts.game')
@section('title','Mapas del mundo')
@section('content')
<h1>Mapas del mundo</h1><p>Selecciona un mundo o una región con mapa disponible.</p><div class="row g-3">@foreach($selectors as $world)<div class="col-md-6"><div class="card h-100"><div class="card-body"><h2 class="h4">{{ $world['name'] }}</h2>@if($world['map_url'])<a class="btn btn-primary mb-2" href="{{ $world['map_url'] }}">Abrir mapa del mundo</a>@endif<ul>@foreach($world['regions'] as $region)<li><a href="{{ $region['map_url'] }}">{{ $region['name'] }}</a></li>@endforeach</ul></div></div></div>@endforeach</div>
@endsection
