@extends('layouts.game')
@section('title','Admin · World Map')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<h1>{{ $map->exists ? 'Editar mapa' : 'Crear mapa' }}</h1>
<form method="POST" enctype="multipart/form-data" action="{{ $map->exists ? route('admin.content.world-maps.update',$map) : route('admin.content.world-maps.store') }}" class="row g-3" data-world-map-form>
    @csrf
    @if($map->exists)
        @method('PUT')
        <input type="hidden" name="version" value="{{ $map->version }}">
    @endif
    <div class="col-md-6">
        <label class="form-label" for="world-map-world">Mundo</label>
        <select id="world-map-world" name="world_id" class="form-select @error('world_id') is-invalid @enderror" data-world-select>
            <option value="">— Seleccionar mundo —</option>
            @foreach($worlds as $world)<option value="{{ $world->id }}" @if((string)old('world_id',$map->world_id)===(string)$world->id) selected @endif>{{ $world->name }}</option>@endforeach
        </select>
        @error('world_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="world-map-region">Región</label>
        <select id="world-map-region" name="region_id" class="form-select @error('region_id') is-invalid @enderror" data-region-select>
            <option value="">— Seleccionar región —</option>
            @foreach($regions as $region)<option value="{{ $region->id }}" @if((string)old('region_id',$map->region_id)===(string)$region->id) selected @endif>{{ $region->world->name }} › {{ $region->name }}</option>@endforeach
        </select>
        @error('region_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Selecciona un Mundo o una Región, pero no ambos.</div>
    </div>
    @foreach(['code'=>'Código','name'=>'Nombre','sort_order'=>'Orden'] as $field=>$label)
        <div class="col-md-4"><label class="form-label" for="world-map-{{ $field }}">{{ $label }}</label><input id="world-map-{{ $field }}" class="form-control @error($field) is-invalid @enderror" name="{{ $field }}" value="{{ old($field,$map->{$field}) }}">@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    @endforeach
    <div class="col-12"><label class="form-label" for="world-map-description">Descripción</label><textarea id="world-map-description" class="form-control @error('description') is-invalid @enderror" name="description">{{ old('description',$map->description) }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="world-map-status">Estado</label><select id="world-map-status" class="form-select @error('status') is-invalid @enderror" name="status">@foreach($statuses as $status)<option @if(old('status',$map->status?:'inactive')===$status) selected @endif>{{ $status }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="world-map-image">Imagen PNG/JPEG/WebP</label><input id="world-map-image" type="file" class="form-control @error('image') is-invalid @enderror" name="image" accept="image/png,image/jpeg,image/webp" @if(!$map->exists) required @endif>@error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror @if($map->exists&&$map->image_path)<div class="form-text">Actual: {{ $map->original_width }} × {{ $map->original_height }}</div>@endif</div>
    <div class="col-md-4"><div class="form-check mt-4"><input type="hidden" name="is_default" value="0"><input class="form-check-input" type="checkbox" name="is_default" value="1" @if(old('is_default',$map->is_default)) checked @endif><label class="form-check-label">Mapa predeterminado activo</label></div></div>
    @if($map->exists && session('aspect_ratio_confirmation_required'))
        <div class="col-12" data-aspect-ratio-confirmation><div class="alert alert-warning mb-2">La nueva imagen tiene una proporción diferente. Los polígonos conservarán sus coordenadas, pero debes revisarlos visualmente después del reemplazo.</div><div class="form-check"><input type="hidden" name="confirm_aspect_ratio_change" value="0"><input id="confirm-aspect-ratio" class="form-check-input" type="checkbox" name="confirm_aspect_ratio_change" value="1" required><label for="confirm-aspect-ratio" class="form-check-label">Confirmo revisar polígonos si cambia la proporción</label></div></div>
    @endif
    <div class="col-12">@error('world_map')<div class="alert alert-danger">{{ $message }}</div>@enderror<button class="btn btn-primary">Guardar mapa</button></div>
</form>
@endsection
