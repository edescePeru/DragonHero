@props(['model', 'type' => \App\Domain\Media\MediaAssetType::SPRITE, 'alt' => '', 'width' => null, 'height' => null, 'class' => '', 'placeholderText' => null])
<x-media.asset :model="$model" :type="$type" :alt="$alt" :width="$width" :height="$height" :class="$class" :placeholder-text="$placeholderText" />
