@extends('layouts.game')
@section('title','Admin · Clases de personaje')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="mb-1">Clases de personaje</h1><p class="text-secondary mb-0">Identidad, disponibilidad y capacidades generales de cada clase.</p></div><a class="btn btn-primary" href="{{ route('admin.content.character-classes.create') }}">Crear clase</a></div>
<div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Icono</th><th>Nombre</th><th>Código</th><th>Estado</th><th>Dual wield</th><th>Plantillas</th><th>Characters</th><th>Items</th><th>Orden</th><th>Acciones</th></tr></thead><tbody>
@foreach($classes as $row) @php($class=$row['model'])
<tr><td><img src="{{ $row['icon']->url64() }}" width="64" height="64" alt="Icono de {{ $class->name }}" class="catalog-image-thumbnail"></td><td>{{ $class->name }}</td><td><code>{{ $class->code }}</code></td><td>{{ $class->status }}</td><td>{{ $class->can_dual_wield?'Sí':'No' }}</td><td>{{ $class->character_templates_count }}</td><td>{{ $class->characters_count }}</td><td>{{ $class->items_count }}</td><td>{{ $class->sort_order }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.content.character-classes.edit',$class) }}">Editar</a></td></tr>
@endforeach
</tbody></table></div>{{ $classes->links() }}
@endsection
