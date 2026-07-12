@extends('layouts.game')
@section('title','Inicio')
@php($breadcrumbs=[['label'=>'Inicio','url'=>null]])
@section('content')
<div class="mb-4"><h1 class="fs-3 mb-1">Bienvenido, {{ $character->name }}</h1><p>Tu aventura comienza en el nivel {{ $character->level }}. Poder actual: {{ $stats->power() }}.</p></div>
<div class="row g-3"><div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">Mi personaje</h2><p>Consulta tu ficha y estadísticas.</p><a class="btn btn-primary" href="{{ route('characters.show',$character) }}">Abrir personaje</a></div></div></div><div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">Inventario</h2><p>Revisa los objetos disponibles.</p><a class="btn btn-primary" href="{{ route('characters.inventory.index',$character) }}">Abrir inventario</a></div></div></div><div class="col-md-4"><div class="card h-100"><div class="card-body"><h2 class="h5">Mundo</h2><p>Explora los catálogos activos.</p><a class="btn btn-primary" href="{{ route('worlds.index') }}">Explorar mundo</a></div></div></div></div>
@endsection
