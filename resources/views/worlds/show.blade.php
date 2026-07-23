@extends('layouts.game')
@section('title',$world->name)
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mundo','url'=>route('worlds.index')],['label'=>$world->name,'url'=>null]])
@section('content')
<div class="d-flex align-items-center flex-wrap gap-2 mb-4" data-world-navigation-context>
<span><span class="text-secondary">Mundo:</span> <strong>{{ $world->name }}</strong></span>
@if(count($regions)>0)<label class="form-label mb-0" for="world-region-selector">Región:</label><select id="world-region-selector" class="form-select w-auto" onchange="if(this.value) window.location.assign(this.value)">@foreach($regions as $option)<option value="{{ $option['url'] }}" @if($region && $option['id']===$region->id) selected @endif>{{ $option['name'] }}</option>@endforeach</select>@elseif($region)<span><span class="text-secondary">Región:</span> <strong>{{ $region->name }}</strong></span>@endif
</div>
<div class="alert alert-secondary" role="status">{{ $message }}</div>
@endsection
