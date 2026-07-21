@extends('layouts.game')
@section('title','Admin · Mundos')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Mundos</h1>
    <a class="btn btn-primary" href="{{ route('admin.content.worlds.create') }}">Crear mundo</a>
</div>
<div class="table-responsive">
<table class="table align-middle">
    <thead><tr><th>Portada</th><th>Mundo</th><th>Estado</th><th>Orden</th><th>Regiones</th><th>Actualizado</th><th>Acciones</th></tr></thead>
    <tbody>
    @forelse($worlds as $row)
        @php($world=$row['model'])
        <tr>
            <td><img src="{{ $row['image']->url64() }}" alt="Portada de {{ $world->name }}" width="64" height="64" class="catalog-image-thumbnail" loading="lazy" decoding="async"></td>
            <td><strong>{{ $world->name }}</strong><br><small>{{ $world->code }}</small></td>
            <td>{{ $world->status }}</td>
            <td>{{ $world->sort_order }}</td>
            <td>{{ $world->regions_count }}</td>
            <td>{{ optional($world->updated_at)->format('Y-m-d H:i') }}</td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.content.worlds.edit',$world) }}">Editar</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.content.worlds.regions.index',$world) }}">Ver regiones</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.content.worlds.regions.create',$world) }}">Crear región</a>
                @if($world->status === 'active')
                    <a class="btn btn-sm btn-outline-success" href="{{ route('worlds.show',$world) }}">Vista pública</a>
                    <form class="d-inline" method="POST" action="{{ route('admin.content.worlds.deactivate',$world) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning">Desactivar</button></form>
                @else
                    <form class="d-inline" method="POST" action="{{ route('admin.content.worlds.activate',$world) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Activar</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-secondary">No hay mundos configurados.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $worlds->links() }}
@endsection
