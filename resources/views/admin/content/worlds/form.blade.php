@extends('layouts.game')
@section('title','Admin · Mundo')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>{{ $world->exists ? 'Editar mundo' : 'Crear mundo' }}</h1>
    <a class="btn btn-outline-secondary" href="{{ route('admin.content.worlds.index') }}">Volver</a>
</div>
<form method="POST" enctype="multipart/form-data" action="{{ $world->exists ? route('admin.content.worlds.update',$world) : route('admin.content.worlds.store') }}">
    @csrf
    @if($world->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="world-code">Código</label><input id="world-code" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code',$world->code) }}" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="world-name">Nombre</label><input id="world-name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name',$world->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label" for="world-description">Descripción</label><textarea id="world-description" class="form-control @error('description') is-invalid @enderror" name="description">{{ old('description',$world->description) }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="world-status">Estado</label><select id="world-status" class="form-select @error('status') is-invalid @enderror" name="status" required>@foreach($worldRow['statuses'] as $status)<option value="{{ $status }}" @if(old('status',$world->status ?: 'active')===$status) selected @endif>{{ $status }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="world-sort-order">Orden</label><input id="world-sort-order" class="form-control @error('sort_order') is-invalid @enderror" type="number" min="0" name="sort_order" value="{{ old('sort_order',$world->sort_order ?: 0) }}" required>@error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    @include('admin.content.partials.catalog-image-upload',['model'=>$world,'catalogImage'=>$worldRow['image']])
    <button class="btn btn-primary mt-3">Guardar</button>
</form>
@if($world->exists && $worldRow['image']->exists())
<form method="POST" action="{{ route('admin.content.worlds.image.destroy',$world) }}" class="mt-2" onsubmit="return confirm('¿Retirar la portada actual del mundo?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Retirar portada</button></form>
@endif
@if($world->exists)
<section class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Regiones de este mundo</h2><a class="btn btn-sm btn-primary" href="{{ route('admin.content.worlds.regions.create',$world) }}">Crear nueva región</a></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Región</th><th>Estado</th><th>Orden</th><th>Niveles</th><th>Mapa predeterminado</th><th>Acciones</th></tr></thead><tbody>
        @forelse($worldRow['region_rows'] as $row)
            @php($region=$row['model'])
            <tr><td>{{ $region->name }}<br><small>{{ $region->code }}</small></td><td>{{ $region->status }}</td><td>{{ $region->sort_order }}</td><td>{{ $region->recommended_level_min }}–{{ $region->recommended_level_max ?: '∞' }}</td><td>{{ $row['has_default_map'] ? $row['default_map']->name.' · '.$row['default_map']->status : 'No configurado' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.content.regions.edit',$region) }}">Editar región</a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.content.world-maps.index',['region_id'=>$region->id]) }}">Administrar mapa</a></td></tr>
        @empty
            <tr><td colspan="6" class="text-secondary">Este mundo todavía no tiene regiones.</td></tr>
        @endforelse
        </tbody></table></div>
    </div>
</section>
@endif
@endsection
