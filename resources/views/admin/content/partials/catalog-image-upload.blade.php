<section class="card mt-3 catalog-image-field" data-catalog-image-preview>
  <div class="card-header"><h2 class="h5 mb-0">Imagen</h2></div><div class="card-body"><div class="d-flex flex-wrap align-items-center gap-3">
    <img src="{{ $catalogImage->url128() }}" alt="Vista previa de imagen" width="128" height="128" class="catalog-image-preview" data-catalog-image-target>
    <div class="flex-grow-1"><label class="form-label" for="catalog-image-input">{{ $model->exists && $catalogImage->exists() ? 'Reemplazar imagen' : 'Subir imagen' }}</label><input id="catalog-image-input" class="form-control @error('image') is-invalid @enderror" type="file" name="image" accept="image/png,image/jpeg,image/webp" data-catalog-image-input><div class="form-text">PNG, JPG o WebP. Máximo 5 MB. La imagen se ajustará a un formato cuadrado sin deformarse.</div><p class="small text-secondary mb-0 mt-2" data-catalog-image-info aria-live="polite">@if($catalogImage->exists())Imagen actual disponible.@elseSe usará la imagen predeterminada.@endif</p>@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
  </div></div>
</section>
