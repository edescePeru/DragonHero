@extends('layouts.game')
@section('content')
<div class="container-fluid">
@include('admin.content.partials.navigation')
<h1>Probabilidades globales de rareza</h1>
<p class="text-muted">Se aplican únicamente a drops únicos. El roll global no se normaliza: una rareza no permitida baja a la permitida inferior más cercana; si no existe, usa el piso mínimo permitido.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="POST" action="{{ route('admin.content.item-rarity-drop-rates.update') }}">@csrf @method('PUT')
<input type="hidden" name="version" value="{{ $version }}">
<div class="row">@foreach($rows as $row)<div class="col-md-6 mb-3"><label class="form-label text-capitalize">{{ $row['code'] }}</label><div class="input-group"><input type="text" inputmode="decimal" class="form-control" name="{{ $row['code'] }}_probability_percent" value="{{ old($row['code'].'_probability_percent',$row['percent']) }}"><span class="input-group-text">%</span></div><small class="text-muted">{{ $row['ppm'] }} PPM</small></div>@endforeach</div>
<p><strong>Total requerido:</strong> 100.0000 % = 1000000 PPM. Una rareza con 0 PPM aún puede ser fija cuando sea la única permitida.</p>
<button class="btn btn-primary" type="submit">Guardar configuración</button>
</form></div>
@endsection
