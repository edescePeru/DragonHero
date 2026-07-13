@extends('layouts.game')
@section('title',$zone->name)
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mundo','url'=>route('worlds.index')],['label'=>$zone->region->world->name,'url'=>route('worlds.show',$zone->region->world)],['label'=>$zone->region->name,'url'=>route('regions.show',$zone->region)],['label'=>$zone->name,'url'=>null]])
@section('content')
<h1 class="fs-3">{{ $zone->name }}</h1>
<p>Nivel recomendado: {{ $zone->recommended_level_min }} - {{ $zone->recommended_level_max ?: 'Sin límite' }}</p>
@if($errors->has('hunt'))<div class="alert alert-warning">{{ $errors->first('hunt') }}</div>@endif
@if($navigationCharacter && $zone->status === 'active' && $zone->allows_hunting)<form method="POST" action="{{ route('characters.hunts.store',[$navigationCharacter,$zone]) }}" class="mb-4" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn btn-primary">Iniciar cacería</button></form>@endif
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
