@extends('layouts.game')
@section('title',$zone->name)
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mundo','url'=>route('worlds.index')],['label'=>$zone->region->world->name,'url'=>route('worlds.show',$zone->region->world)],['label'=>$zone->region->name,'url'=>route('regions.show',$zone->region)],['label'=>$zone->name,'url'=>null]])
@section('content')
<h1 class="fs-3">{{ $zone->name }}</h1>
<p>Nivel recomendado: {{ $zone->recommended_level_min }} - {{ $zone->recommended_level_max ?: 'Sin límite' }}</p>
@if($errors->has('hunt'))<div class="alert alert-warning">{{ $errors->first('hunt') }}</div>@endif
@if($navigationCharacter && $zone->status === 'active' && $zone->allows_hunting)
<div class="row g-3 mb-4">
 <div class="col-md-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Cacería automática</h2><p class="text-secondary">El servidor ejecutará y resolverá los encuentros automáticamente.</p><form method="POST" action="{{ route('characters.hunting-sessions.store',[$navigationCharacter,$zone]) }}" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn btn-success">Cacería automática</button></form></div></div></div>
 <div class="col-md-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Combate manual</h2><p class="text-secondary">Elige objetivos y ejecuta las acciones de tu personaje.</p><form method="POST" action="{{ route('characters.manual-combats.zones.store',[$navigationCharacter,$zone]) }}" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn btn-primary">Combate manual</button></form></div></div></div>
</div>
@endif
@if(count($shops)>0)
<section class="mb-4" aria-labelledby="zone-shops-title">
 <h2 id="zone-shops-title" class="h4 mb-3">Tiendas disponibles</h2>
 <div class="row g-3">
 @foreach($shops as $availableShop)
  <div class="col-md-6 col-xl-4"><article class="card h-100 overflow-hidden">
   @if($availableShop['banner_url'])<img src="{{ $availableShop['banner_url'] }}" class="card-img-top" style="height:130px;object-fit:cover" alt="Banner de {{ $availableShop['name'] }}">@endif
   <div class="card-body d-flex flex-column"><div class="d-flex gap-3 align-items-center mb-3">
    @if($availableShop['portrait_url'])<img src="{{ $availableShop['portrait_url'] }}" width="56" height="56" class="rounded-circle object-fit-cover" alt="Retrato de {{ $availableShop['npc_name'] }}">@else<div class="rounded-circle bg-light border d-flex align-items-center justify-content-center" style="width:56px;height:56px" aria-hidden="true"><i class="ti ti-user"></i></div>@endif
    <div><h3 class="h5 mb-0">{{ $availableShop['name'] }}</h3><small class="text-secondary">{{ $availableShop['npc_name'] }}</small></div>
   </div><p class="text-secondary flex-grow-1">{{ $availableShop['description'] ?: 'Comerciante disponible en esta zona.' }}</p><p class="small">{{ $availableShop['offers_count'] }} ofertas visibles</p><a class="btn btn-primary" href="{{ $availableShop['url'] }}">Visitar tienda</a></div>
  </article></div>
 @endforeach
 </div>
</section>
@endif
<div class="row g-3">
 <div class="col-lg-6"><div class="card"><div class="card-header">Conexiones disponibles</div><ul class="list-group list-group-flush">
 @foreach($zone->outgoingConnections as $connection)@if($connection->toZone)<li class="list-group-item"><a href="{{ route('zones.show',$connection->toZone) }}">{{ $connection->toZone->name }}</a></li>@endif @endforeach
 @foreach($zone->incomingConnections as $connection)@if($connection->fromZone)<li class="list-group-item"><a href="{{ route('zones.show',$connection->fromZone) }}">{{ $connection->fromZone->name }}</a></li>@endif @endforeach
 </ul></div></div>
 <div class="col-lg-6"><div class="card"><div class="card-header">Monstruos posibles <small>Pesos de configuración; no son porcentajes.</small></div><table class="table"><tbody>
 @foreach($zone->monsters as $monster)<tr><td><div class="d-flex align-items-center gap-2"><x-media.portrait :model="$monster" :alt="'Retrato de '.$monster->name" :placeholder-text="$monster->name" :width="48" :height="48" class="flex-shrink-0 object-fit-cover" /><span>{{ $monster->name }}</span></div></td><td>{{ $monster->level }}</td><td>{{ $monster->pivot->weight }}</td></tr>@endforeach
 </tbody></table></div></div>
</div>
@endsection
