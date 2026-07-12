@extends('layouts.game')
@section('title','Inventario de '.$character->name)
@php($breadcrumbs=[['label'=>'Inicio','url'=>route('dashboard')],['label'=>'Mi personaje','url'=>route('characters.show',$character)],['label'=>'Inventario','url'=>null]])
@section('content')
<div class="mb-4"><a href="{{ route('characters.show',$character) }}">Volver al personaje</a><h1 class="fs-3 mt-2">Inventario</h1><p class="text-secondary">Objetos distintos: {{ $entries->count() }}</p></div>
<div class="card"><div class="card-header"><h2 class="h5 mb-0">Objetos apilables</h2></div>
@if($entries->isEmpty())<div class="card-body p-5 text-center text-secondary">El inventario está vacío.</div>@else
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Objeto</th><th>Tipo</th><th>Rareza</th><th>Total</th><th>Bloqueado</th><th>Disponible</th></tr></thead><tbody>
@foreach($entries as $entry)<tr><td>{{ $entry->itemName() }}<br><small>{{ $entry->itemCode() }}</small></td><td><span class="badge bg-light text-dark">{{ $entry->itemType() }}</span></td><td>{{ $entry->itemRarity() }}</td><td>{{ $entry->totalQuantity() }}</td><td>{{ $entry->lockedQuantity() }}</td><td><strong>{{ $entry->availableQuantity() }}</strong></td></tr>@endforeach
</tbody></table></div>@endif</div>
@endsection
