@props(['model', 'alt' => '', 'width' => null, 'height' => null, 'class' => '', 'placeholderText' => null])
<x-media.asset :model="$model" :type="\App\Domain\Media\MediaAssetType::ICON" :alt="$alt" :width="$width" :height="$height" :class="$class" :placeholder-text="$placeholderText" />
