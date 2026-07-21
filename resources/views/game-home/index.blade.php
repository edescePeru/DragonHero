@extends('layouts.game')
@section('title','Inicio')
@php($breadcrumbs=[['label'=>'Inicio','url'=>null]])
@section('content')
<div class="mb-4"><h1 class="fs-3 mb-1">Menú del juego</h1><p class="text-secondary">Selecciona el destino de tu aventura.</p></div>
<div class="row g-3 game-home-grid">@foreach($cards as $card)<div class="col-12 col-lg-6"><a class="game-home-card d-block rounded overflow-hidden text-decoration-none" href="{{ $card['destination_url'] }}" aria-label="Abrir {{ $card['name'] }}" @if($card['opens_new_tab']) target="_blank" rel="noopener noreferrer" @endif>@if($card['banner_url'])<img src="{{ $card['banner_url'] }}" alt="{{ $card['name'] }}" width="1200" height="400" loading="lazy">@else<span class="game-home-card__fallback"><strong>{{ $card['name'] }}</strong></span>@endif</a></div>@endforeach</div>
@if(empty($cards))<div class="alert alert-info">No hay accesos disponibles en este momento.</div>@endif
@once<style>.game-home-card{aspect-ratio:3/1;background:#19263d;box-shadow:0 .25rem .75rem rgba(0,0,0,.15)}.game-home-card:focus-visible{outline:3px solid #0d6efd;outline-offset:3px}.game-home-card img{display:block;width:100%;height:100%;object-fit:cover}.game-home-card__fallback{display:flex;width:100%;height:100%;align-items:center;justify-content:center;padding:1rem;color:#fff;background:linear-gradient(135deg,#16243a,#355b85);font-size:clamp(1.1rem,3vw,2rem);text-align:center}</style>@endonce
@endsection
