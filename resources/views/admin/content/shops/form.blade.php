@extends('layouts.game')
@section('title','Admin · Shop')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<h1>{{ $shop->exists?'Editar Shop':'Crear Shop' }}</h1>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" enctype="multipart/form-data" action="{{ $shop->exists?route('admin.content.shops.update',$shop):route('admin.content.shops.store') }}">
@csrf
@if($shop->exists)@method('PUT')@endif
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Código</label><input class="form-control" name="code" value="{{ old('code',$shop->code) }}" required></div>
<div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" name="name" value="{{ old('name',$shop->name) }}" required></div>
<div class="col-md-4"><label class="form-label">NPC</label><select class="form-select" name="npc_id" required>@foreach($npcs as $npc)<option value="{{ $npc->id }}" @if((int)old('npc_id',$shop->npc_id)===(int)$npc->id) selected @endif>{{ $npc->name }} · {{ $npc->status }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="description">{{ old('description',$shop->description) }}</textarea></div>
<div class="col-md-3"><div class="form-check mt-4"><input type="hidden" name="buys_items" value="0"><input class="form-check-input" type="checkbox" name="buys_items" value="1" id="buys-items" @if(old('buys_items',$shop->buys_items)) checked @endif><label class="form-check-label" for="buys-items">Compra Items a jugadores</label></div></div>
<div class="col-md-3"><label class="form-label">Porcentaje de compra</label><div class="input-group"><input class="form-control @error('purchase_rate_percent') is-invalid @enderror" name="purchase_rate_percent" inputmode="decimal" value="{{ old('purchase_rate_percent',$purchase_rate_percent) }}" required><span class="input-group-text">%</span>@error('purchase_rate_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="form-text">Entre 0.01% y 100%, máximo dos decimales. Se conserva al desactivar compras.</div></div>
<div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="status">@foreach($statuses as $status)<option value="{{ $status }}" @if(old('status',$shop->status?:'active')===$status) selected @endif>{{ $status }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Orden</label><input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order',$shop->sort_order?:0) }}"></div>
<div class="col-md-3"><label class="form-label">Inicio UTC</label><input class="form-control" type="datetime-local" name="starts_at" value="{{ old('starts_at',$shop->starts_at?$shop->starts_at->format('Y-m-d\TH:i'):'') }}"></div>
<div class="col-md-3"><label class="form-label">Fin UTC exclusivo</label><input class="form-control" type="datetime-local" name="ends_at" value="{{ old('ends_at',$shop->ends_at?$shop->ends_at->format('Y-m-d\TH:i'):'') }}"></div>
<div class="col-12"><label class="form-label">Zones</label><select class="form-select" name="zone_ids[]" multiple size="8">@foreach($zones as $zone)<option value="{{ $zone->id }}" @if(in_array($zone->id,old('zone_ids',$selected_zone_ids))) selected @endif>{{ $zone->region->world->name }} › {{ $zone->region->name }} › {{ $zone->name }}</option>@endforeach</select><div class="form-text">Sin selección = Shop global. El servidor fija el tipo de localización como Zone.</div></div>
</div>
@include('admin.content.partials.shop-media-field',['asset'=>$banner,'label'=>'Banner','inputName'=>'shop_banner','removeName'=>'remove_shop_banner','previewClass'=>'w-100'])
@include('admin.content.partials.shop-media-field',['asset'=>$background,'label'=>'Background','inputName'=>'shop_background','removeName'=>'remove_shop_background','previewClass'=>'w-100'])
<button class="btn btn-primary mt-3">Guardar Shop</button>
</form>
@if($shop->exists)
<div class="d-flex gap-2 my-3"><form method="POST" action="{{ route('admin.content.shops.activate',$shop) }}">@csrf @method('PATCH')<button class="btn btn-outline-success">Activar</button></form><form method="POST" action="{{ route('admin.content.shops.deactivate',$shop) }}">@csrf @method('PATCH')<button class="btn btn-outline-warning">Desactivar</button></form><form method="POST" action="{{ route('admin.content.shops.hide',$shop) }}">@csrf @method('PATCH')<button class="btn btn-outline-secondary">Ocultar</button></form></div>
<section class="card mb-4"><div class="card-header">Ofertas configuradas</div><div class="card-body">
@forelse($offers as $offerRow)<div class="d-flex justify-content-between"><strong>{{ $offerRow['item']['name'] }} × {{ $offerRow['model']->quantity }}</strong><span class="badge bg-secondary">{{ $offerRow['state'] }}</span></div>@include('admin.content.shops.partials.offer-form',['offerRow'=>$offerRow])<div class="d-flex gap-2 mb-4"><form method="POST" action="{{ route('admin.content.shops.offers.activate',[$shop,$offerRow['model']]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Activar</button></form><form method="POST" action="{{ route('admin.content.shops.offers.deactivate',[$shop,$offerRow['model']]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning">Desactivar</button></form></div>@empty<p class="text-secondary">Sin ofertas.</p>@endforelse
<h2 class="h5">Nueva oferta</h2>@include('admin.content.shops.partials.offer-form',['offerRow'=>null])
</div></section>
@endif
@include('admin.content.partials.shop-media-preview-script')
@push('scripts')
<script>document.querySelectorAll('[data-offer-form]').forEach(function(form){var search=form.querySelector('[data-item-search]'),id=form.querySelector('[data-item-id]'),results=form.querySelector('[data-item-results]'),preview=form.querySelector('[data-item-preview]'),timer=null;search.addEventListener('input',function(){id.value='';clearTimeout(timer);var q=search.value.trim();if(q.length<2){results.classList.add('d-none');return;}timer=setTimeout(function(){fetch('{{ route('admin.content.shops.items.search') }}?q='+encodeURIComponent(q),{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(payload){results.textContent='';payload.data.forEach(function(item){var button=document.createElement('button');button.type='button';button.className='list-group-item list-group-item-action';button.textContent=item.name+' · '+item.code+' · '+item.item_type;button.addEventListener('click',function(){id.value=String(item.id);search.value=item.name+' · '+item.code;preview.textContent=item.item_type+' · '+item.rarity+' · '+item.classification+' · stack '+item.max_stack+' · nivel propio '+item.required_level;results.classList.add('d-none');});results.appendChild(button);});results.classList.toggle('d-none',payload.data.length===0);});},250);});var stock=form.querySelector('[data-stock-mode]');stock.addEventListener('change',function(){var unlimited=stock.value==='unlimited';['stock_limit','stock_remaining'].forEach(function(name){var input=form.querySelector('[name="'+name+'"]');input.disabled=unlimited;if(unlimited)input.value='';});});stock.dispatchEvent(new Event('change'));});</script>
@endpush
@endsection
