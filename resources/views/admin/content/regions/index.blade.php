@extends('layouts.game')
@section('title','Admin · Regiones')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1>Regiones</h1>@if($contextWorld)<p class="text-secondary mb-0">Mundo: {{ $contextWorld->name }}</p>@endif</div>
    <div>@if($contextWorld)<a class="btn btn-outline-secondary" href="{{ route('admin.content.worlds.edit',$contextWorld) }}">Volver al mundo</a>@endif <a class="btn btn-primary" href="{{ $contextWorld ? route('admin.content.worlds.regions.create',$contextWorld) : route('admin.content.regions.create') }}">Crear región</a></div>
</div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mundo</th><th>Región</th><th>Estado</th><th>Orden</th><th>Niveles</th><th>Mapa</th><th>Acciones</th></tr></thead><tbody>
@forelse($regions as $region)
<tr><td>{{ $region->world->name }}</td><td>{{ $region->name }}<br><small>{{ $region->code }}</small></td><td>{{ $region->status }}</td><td>{{ $region->sort_order }}</td><td>{{ $region->recommended_level_min }}–{{ $region->recommended_level_max ?: '∞' }}</td><td>{{ $region->worldMaps->isNotEmpty() ? $region->worldMaps->first()->name : 'No configurado' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.content.regions.edit',$region) }}">Editar</a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.content.world-maps.index',['region_id'=>$region->id]) }}">Administrar mapa</a> @if($region->status==='active')<form class="d-inline" method="POST" action="{{ route('admin.content.regions.deactivate',$region) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning">Desactivar</button></form>@else<form class="d-inline" method="POST" action="{{ route('admin.content.regions.activate',$region) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Activar</button></form>@endif</td></tr>
@empty<tr><td colspan="7" class="text-secondary">No hay regiones configuradas.</td></tr>@endforelse
</tbody></table></div>{{ $regions->links() }}
@endsection
