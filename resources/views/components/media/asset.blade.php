@props([
    'model' => null,
    'type',
    'alt' => '',
    'width' => null,
    'height' => null,
    'class' => '',
    'placeholderText' => null,
])
@php
    $mediaState = $model && method_exists($model, 'relationLoaded') && $model->relationLoaded('mediaAssets') ? 'missing' : 'not-loaded';
    $asset = null;
    if ($mediaState === 'missing') {
        $asset = $model->getRelation('mediaAssets')
            ->filter(function ($candidate) use ($type) { return $candidate->asset_type === $type; })
            ->sortBy(function ($candidate) {
                return sprintf('%d-%010d-%020d', $candidate->is_primary ? 0 : 1, (int) $candidate->sort_order, (int) $candidate->id);
            })
            ->first();
        $mediaState = $asset ? 'loaded' : 'missing';
    }
    $resolvedPlaceholder = $placeholderText !== null ? $placeholderText : ($alt !== '' ? $alt : 'Recurso visual no disponible');
    $style = trim(($width ? 'width: '.(int) $width.'px;' : '').($height ? 'height: '.(int) $height.'px;' : ''));
@endphp
@if($asset)
<img src="{{ $asset->url() }}" alt="{{ $alt }}" @if($width) width="{{ (int) $width }}" @endif @if($height) height="{{ (int) $height }}" @endif class="{{ $class }}" data-media-state="{{ $mediaState }}">
@else
<span role="img" aria-label="{{ $resolvedPlaceholder }}" @if($style !== '') style="{{ $style }}" @endif class="d-inline-flex align-items-center justify-content-center bg-light border rounded text-secondary overflow-hidden {{ $class }}" data-media-state="{{ $mediaState }}"><span aria-hidden="true" class="fw-semibold">{{ mb_substr($resolvedPlaceholder, 0, 1) }}</span><span class="visually-hidden">{{ $resolvedPlaceholder }}</span></span>
@endif
