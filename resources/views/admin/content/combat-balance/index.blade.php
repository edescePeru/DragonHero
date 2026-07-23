@extends('layouts.game') @section('title','Admin · Balance de combate') @section('content')
@include('admin.content.partials.navigation') @include('admin.content.partials.messages')
<h1>Balance defensivo de combate</h1><p class="text-secondary">Porcentajes almacenados exactamente en basis points. Los cambios afectan combates nuevos.</p>
<form method="POST" action="{{ route('admin.content.combat-balance.update') }}">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $setting->version }}"><div class="row g-3">
@foreach(['defense_reduction_cap'=>'Cap de reducción por Defensa','absorb_damage_cap'=>'Cap de AbsorbDamage','total_mitigation_cap'=>'Cap de mitigación combinada'] as $field=>$label)<div class="col-md-4"><label class="form-label">{{ $label }} (%)</label><input class="form-control" name="{{ $field }}_percent" type="number" min="0" max="99" step="0.01" value="{{ old($field.'_percent', number_format(((int) $setting->{$field.'_basis_points'}) / 100, 2, '.', '')) }}" required></div>@endforeach
<div class="col-md-4"><label class="form-label">Daño mínimo recibido</label><input class="form-control" name="minimum_damage" type="number" min="1" step="1" value="{{ old('minimum_damage',$setting->minimum_damage) }}" required></div></div><button class="btn btn-primary mt-3">Guardar balance</button></form>
@endsection
