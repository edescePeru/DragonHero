@extends('layouts.game')
@section('title','Admin · Región')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<div class="d-flex justify-content-between align-items-center mb-3"><h1>{{ $region->exists ? 'Editar región' : 'Crear región' }}</h1><a class="btn btn-outline-secondary" href="{{ $contextWorld ? route('admin.content.worlds.edit',$contextWorld) : route('admin.content.regions.index') }}">Volver</a></div>
<form method="POST" action="{{ $region->exists ? route('admin.content.regions.update',$region) : ($contextWorld ? route('admin.content.worlds.regions.store',$contextWorld) : route('admin.content.regions.store')) }}">
@csrf
@if($region->exists) @method('PUT') @endif
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="region-world">Mundo</label>
        @if($options['world_locked'])
            <input type="hidden" name="world_id" value="{{ old('world_id',$region->world_id) }}">
            <input id="region-world" class="form-control" value="{{ $options['selected_world_name'] }}" disabled>
            <div class="form-text">La pertenencia queda bloqueada para proteger mapas y zonas existentes.</div>
        @else
            <select id="region-world" class="form-select @error('world_id') is-invalid @enderror" name="world_id" required><option value="">Seleccionar mundo</option>@foreach($options['worlds'] as $world)<option value="{{ $world->id }}" @if((string)old('world_id',$region->world_id)===(string)$world->id) selected @endif>{{ $world->name }}</option>@endforeach</select>
        @endif
        @error('world_id')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3"><label class="form-label" for="region-code">Código</label><input id="region-code" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code',$region->code) }}" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="region-name">Nombre</label><input id="region-name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name',$region->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12"><label class="form-label" for="region-description">Descripción</label><textarea id="region-description" class="form-control @error('description') is-invalid @enderror" name="description">{{ old('description',$region->description) }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="region-min">Nivel mínimo</label><input id="region-min" type="number" min="1" class="form-control @error('recommended_level_min') is-invalid @enderror" name="recommended_level_min" value="{{ old('recommended_level_min',$region->recommended_level_min ?: 1) }}" required>@error('recommended_level_min')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="region-max">Nivel máximo</label><input id="region-max" type="number" min="1" class="form-control @error('recommended_level_max') is-invalid @enderror" name="recommended_level_max" value="{{ old('recommended_level_max',$region->recommended_level_max) }}">@error('recommended_level_max')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="region-status">Estado</label><select id="region-status" class="form-select @error('status') is-invalid @enderror" name="status">@foreach($options['statuses'] as $status)<option value="{{ $status }}" @if(old('status',$region->status ?: 'active')===$status) selected @endif>{{ $status }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="region-order">Orden</label><input id="region-order" type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" name="sort_order" value="{{ old('sort_order',$region->sort_order ?: 0) }}" required>@error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div>
<button class="btn btn-primary mt-3">Guardar</button>
@if($region->exists)<a class="btn btn-outline-secondary mt-3" href="{{ route('admin.content.world-maps.index',['region_id'=>$region->id]) }}">Administrar mapa</a>@endif
</form>
@endsection
