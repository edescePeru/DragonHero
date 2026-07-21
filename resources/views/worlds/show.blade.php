@extends('layouts.game')
@section('title',$world->name)
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mundo','url'=>route('worlds.index')],['label'=>$world->name,'url'=>null]])
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
<div><h1 class="fs-3 mb-1">{{ $world->name }}</h1>@if($region)<p class="text-secondary mb-0">{{ $region->name }}</p>@endif</div>
@if(count($regions)>0)<div><label class="form-label" for="world-region-selector">Región</label><select id="world-region-selector" class="form-select" onchange="if(this.value) window.location.assign(this.value)">@foreach($regions as $option)<option value="{{ $option['url'] }}" @if($region && $option['id']===$region->id) selected @endif>{{ $option['name'] }}</option>@endforeach</select></div>@endif
</div>
<div class="alert alert-secondary" role="status">{{ $message }}</div>
@endsection
