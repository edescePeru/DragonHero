<section class="card">
    <div class="card-header">Equipamiento actual</div>
    <div class="card-body">
        <div class="manual-combat-equipment-grid">
            @foreach($equipment as $slot)
                <article class="manual-combat-equipment-slot" data-equipment-slot="{{ $slot['slot'] }}">
                    <small>{{ $slot['label'] }}</small>
                    <div class="manual-combat-item-image">
                        @if($slot['image_url'])<img src="{{ $slot['image_url'] }}" alt="" loading="lazy">@else<span aria-hidden="true">◇</span>@endif
                    </div>
                    @if($slot['occupied'])<span>{{ $slot['item_name'] }}</span><strong>+{{ $slot['refinement_level'] }}</strong>@else<span class="text-secondary">Vacío</span>@endif
                </article>
            @endforeach
        </div>
    </div>
</section>
