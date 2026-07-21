@extends('layouts.game')
@section('title','Mundos')
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mundo','url'=>null]])
@section('content')
<div class="mb-4"><h1 class="fs-3">Mundos</h1><p class="text-secondary">Explora los mundos disponibles.</p></div>
<div class="row g-3">
@forelse($worlds as $entry)
<div class="col-lg-4"><article class="card h-100 overflow-hidden">
@if($entry['preview_url'])<img src="{{ $entry['preview_url'] }}" class="card-img-top" style="aspect-ratio:16/9;object-fit:cover" alt="Vista de {{ $entry['world']->name }}">
@else<div class="d-flex align-items-center justify-content-center bg-light text-secondary" style="aspect-ratio:16/9" aria-label="Vista no disponible"><span aria-hidden="true" class="fs-1">◇</span></div>@endif
<div class="card-body p-4 d-flex flex-column"><h2 class="h4">{{ $entry['world']->name }}</h2><p>{{ $entry['world']->description }}</p><a class="btn btn-primary mt-auto" href="{{ route('worlds.show',$entry['world']) }}">Explorar mundo</a></div>
</article></div>
@empty<p>No hay mundos activos.</p>@endforelse
</div>
@endsection
