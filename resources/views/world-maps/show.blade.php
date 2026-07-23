@extends('layouts.game')

@section('title', $worldMap['map']['name'])

@section('content')
<div id="world-map-player" data-world-map-player data-csrf="{{ csrf_token() }}" data-player-state="loading">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <div><h1>{{ $worldMap['map']['name'] }}</h1><p>{{ $worldMap['map']['context']['name'] }}</p></div>
        <div>
            @if(isset($worldMap['navigation']))
                <div class="d-flex align-items-center flex-wrap gap-2" data-world-navigation-context>
                    <span><span class="text-secondary">Mundo:</span> <strong>{{ $worldMap['navigation']['world']->name }}</strong></span>
                    <label class="form-label mb-0" for="world-region-selector">Región:</label>
                    <select id="world-region-selector" class="form-select w-auto" onchange="if(this.value) window.location.assign(this.value)">
                        @foreach($worldMap['navigation']['regions'] as $region)<option value="{{ $region['url'] }}" @if($region['id']===$worldMap['navigation']['region']->id) selected @endif>{{ $region['name'] }}</option>@endforeach
                    </select>
                </div>
            @else
                <label class="form-label">World / Region</label>
                <select class="form-select" onchange="if(this.value) window.location.assign(this.value)">
                    <option value="">Seleccionar mapa</option>
                    @foreach($worldMap['selectors'] as $world)
                        @if($world['map_url'])<option value="{{ $world['map_url'] }}">{{ $world['name'] }}</option>@endif
                        @foreach($world['regions'] as $region)<option value="{{ $region['map_url'] }}">{{ $world['name'] }} › {{ $region['name'] }}</option>@endforeach
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="world-map-scroll">
                <div class="world-map-player-canvas" data-world-map-player-canvas>
                    <div class="world-map-status" data-world-map-status role="status">Cargando mapa…</div>
                    <img class="world-map-image" data-world-map-player-image src="{{ $worldMap['map']['image_url'] }}" alt="{{ $worldMap['map']['name'] }}">
                    <svg class="world-map-overlay" data-world-map-player-svg viewBox="0 0 {{ $worldMap['map']['width'] }} {{ $worldMap['map']['height'] }}" preserveAspectRatio="xMidYMid meet" role="group" aria-label="Destinos del mapa">
                        @foreach($worldMap['areas'] as $area)
                            <polygon
                                class="world-map-player-area {{ $area['action']['enabled'] ? 'is-enabled' : 'is-disabled' }}"
                                data-world-map-area
                                data-area-id="{{ $area['id'] }}"
                                tabindex="0"
                                role="button"
                                aria-label="{{ $area['action']['label'] }}"
                                aria-disabled="{{ $area['action']['enabled'] ? 'false' : 'true' }}"
                                aria-pressed="false"
                                data-action='@json($area['action'])'
                                points="{{ $area['svg_points'] }}"
                                style="fill:{{ $area['style']['fill_color'] }};fill-opacity:{{ $area['style']['fill_opacity'] }};stroke:{{ $area['style']['stroke_color'] }};stroke-width:{{ $area['style']['stroke_width'] }};--world-map-hover-fill:{{ $area['style']['hover_fill_color'] }};--world-map-hover-opacity:{{ $area['style']['hover_fill_opacity'] }}"
                            ></polygon>
                        @endforeach
                    </svg>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <aside class="card d-none" data-world-map-panel aria-live="polite"><div class="card-body"><h2 class="h4" data-panel-title>Destino</h2><div data-panel-body></div><a class="btn btn-primary d-none mt-3" data-panel-link href="#">Abrir destino</a></div></aside>
            <div class="card" data-map-help><div class="card-body">Selecciona un área del mapa mediante click, Enter o Espacio.</div></div>
        </div>
    </div>
</div>
@endsection
