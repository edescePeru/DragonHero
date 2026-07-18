@extends('layouts.game')
@section('title','Vista compacta de '.$overview['summary']['name'])
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mi personaje','url'=>route('characters.show',$character)],['label'=>'Vista compacta','url'=>null]])
@section('content')
<div class="character-overview" data-character-overview>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="fs-3 mb-1">Vista compacta</h1><p class="text-secondary mb-0">Personaje, equipo e inventario en una sola pantalla.</p></div><a class="btn btn-outline-secondary btn-sm" href="{{ route('characters.show',$character) }}">Ver ficha detallada</a></div>
  <div class="alert alert-danger d-none" role="alert" tabindex="-1" data-overview-error></div>
  <div class="row g-3 align-items-stretch mb-3"><div class="col-xl-4">@include('characters.overview.summary')</div><div class="col-xl-8">@include('characters.overview.equipment')</div></div>
  @include('characters.overview.inventory')
  @include('characters.overview.stats')
  @include('characters.overview.action-panel')
</div>
@endsection
