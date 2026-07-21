<section class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <span>Inventario</span>
        <span>Usados <strong>{{ $inventory['inventory_status']['current_used_slots'] }}</strong> de <strong>{{ $inventory['inventory_status']['effective_capacity'] }}</strong> · Libres <strong>{{ $inventory['inventory_status']['current_free_slots'] }}</strong></span>
    </div>
    <div class="card-body">
        <div class="manual-combat-item-grid">
            @forelse($inventory['stackable_items'] as $item)
                @foreach($item['stack_quantities'] as $stackQuantity)
                    <article class="manual-combat-item-card" data-item-id="{{ $item['item_id'] }}">
                        <div class="manual-combat-item-image">@if($item['image_url'])<img src="{{ $item['image_url'] }}" alt="" loading="lazy">@else<span aria-hidden="true">◇</span>@endif</div>
                        <span>{{ $item['item_name'] }}</span><strong>×{{ $stackQuantity }}</strong><small class="text-secondary">Stack máximo {{ $item['max_stack'] }}</small>
                    </article>
                @endforeach
            @empty
            @endforelse
            @foreach($inventory['item_instances'] as $item)
                <article class="manual-combat-item-card">
                    <div class="manual-combat-item-image">@if($item['image_url'])<img src="{{ $item['image_url'] }}" alt="" loading="lazy">@else<span aria-hidden="true">◇</span>@endif</div>
                    <span>{{ $item['item_name'] }}</span><strong>+{{ $item['refinement_level'] }}</strong>
                </article>
            @endforeach
            @if(count($inventory['stackable_items']) === 0 && count($inventory['item_instances']) === 0)<p class="mb-0 text-secondary">Tu inventario está vacío.</p>@endif
        </div>
    </div>
</section>
