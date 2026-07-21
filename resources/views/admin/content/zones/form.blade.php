@extends('layouts.game')
@section('title','Admin · Zone')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<h1>{{ $zone->exists?'Editar Zone':'Crear Zone' }}</h1>
<form method="POST" action="{{ $zone->exists?route('admin.content.zones.update',$zone):route('admin.content.zones.store') }}">
@csrf
@if($zone->exists) @method('PUT') @endif
<div class="row g-3">
 <div class="col-md-6"><label>World › Region</label><select class="form-select" name="region_id">@foreach($options['regions'] as $region)<option value="{{ $region->id }}" @if(old('region_id',$zone->region_id)==$region->id) selected @endif>{{ $region->world->name }} › {{ $region->name }}</option>@endforeach</select></div>
 @foreach(['code','name','recommended_level_min','recommended_level_max','sort_order'] as $field)<div class="col-md-3"><label>{{ $field }}</label><input class="form-control" name="{{ $field }}" value="{{ old($field,$zone->{$field}) }}"></div>@endforeach
 <div class="col-12"><label>Descripción</label><textarea class="form-control" name="description">{{ old('description',$zone->description) }}</textarea></div>
 @foreach(['zone_type'=>$options['zone_types'],'status'=>$options['statuses']] as $field=>$values)<div class="col-md-3"><label>{{ $field }}</label><select class="form-select" name="{{ $field }}">@foreach($values as $value)<option @if(old($field,$zone->{$field})===$value) selected @endif>{{ $value }}</option>@endforeach</select></div>@endforeach
 @foreach(['is_safe','allows_hunting'] as $field)<div class="col-md-3"><label>{{ $field }}</label><select class="form-select" name="{{ $field }}"><option value="0">No</option><option value="1" @if(old($field,$zone->{$field})) selected @endif>Sí</option></select></div>@endforeach
 <div class="col-12"><fieldset class="card"><div class="card-body"><legend class="h5">Cantidad de monstruos por encuentro</legend><p class="text-secondary">Define la probabilidad de generar encuentros con 1, 2 o 3 monstruos. La suma debe ser exactamente 100%.</p><p class="small">Estos valores determinan cuántos monstruos aparecen; los pesos configurados para los monstruos de la zona determinan cuáles aparecen.</p><div class="row g-3">@foreach([1,2,3] as $count)<div class="col-md-4"><label for="encounter-size-{{ $count }}">{{ $count }} {{ $count===1?'monstruo':'monstruos' }} (%)</label><div class="input-group"><input id="encounter-size-{{ $count }}" class="form-control encounter-size-input @error('encounter_sizes.'.$count) is-invalid @enderror" type="number" min="0" max="100" step="1" name="encounter_sizes[{{ $count }}]" value="{{ old('encounter_sizes.'.$count,$options['encounter_sizes'][$count]) }}"><span class="input-group-text">%</span></div>@error('encounter_sizes.'.$count)<div class="text-danger small">{{ $message }}</div>@enderror</div>@endforeach</div>@error('encounter_sizes')<div class="text-danger small mt-2">{{ $message }}</div>@enderror<p class="mt-3 mb-1">Total: <strong id="encounter-size-total">100%</strong></p><p class="text-secondary mb-0">Máximo administrable actual: 3 monstruos por encuentro.</p></div></fieldset></div>
</div>
<button class="btn btn-primary mt-3">Guardar</button>
</form>
<script>(function(){const fields=document.querySelectorAll('.encounter-size-input'),total=document.getElementById('encounter-size-total');function update(){let sum=0;fields.forEach(function(field){sum+=Number(field.value)||0;});total.textContent=sum+'%';total.classList.toggle('text-danger',sum!==100);total.classList.toggle('text-success',sum===100);}fields.forEach(function(field){field.addEventListener('input',update);});update();})();</script>
@endsection
