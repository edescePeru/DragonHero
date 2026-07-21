<div class="character-appearance" role="img" aria-label="Apariencia equipada de {{ $name }}">
  <img class="character-appearance__layer" src="{{ $appearance['base_body']['url'] }}" alt="" width="256" height="384">
  @foreach($appearance['layers'] as $layer)<img class="character-appearance__layer" src="{{ $layer['url'] }}" alt="" width="256" height="384" data-visual-slot="{{ $layer['visual_slot'] }}">@endforeach
</div>
@once
<style>.character-appearance{position:relative;width:100%;max-width:256px;margin-inline:auto;aspect-ratio:2/3;overflow:hidden}.character-appearance__layer{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;pointer-events:none}</style>
@endonce
