<section class="card h-100" aria-labelledby="overview-character-heading"><div class="card-body d-flex flex-column">
  <div class="d-flex align-items-center gap-3 mb-3">
    @if($overview['summary']['portrait_url'])<img class="overview-portrait" src="{{ $overview['summary']['portrait_url'] }}" alt="Retrato de {{ $overview['summary']['name'] }}">@else<div class="overview-portrait overview-placeholder" role="img" aria-label="Sin retrato">{{ $overview['summary']['portrait_initial'] }}</div>@endif
    <div><h2 id="overview-character-heading" class="h4 mb-1">{{ $overview['summary']['name'] }}</h2><p class="text-secondary mb-0">{{ $overview['summary']['class_name'] }} · Nivel {{ $overview['summary']['level'] }}</p></div>
  </div>
  <div class="d-flex justify-content-between small mb-1"><span>EXP {{ $overview['summary']['experience'] }}</span><span>{{ $overview['summary']['progress']['percentage'] }}%</span></div>
  <div class="progress mb-2" role="progressbar" aria-label="Progreso de experiencia" aria-valuenow="{{ $overview['summary']['progress']['percentage'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:{{ $overview['summary']['progress']['percentage'] }}%"></div></div>
  @if($overview['summary']['progress']['maximum_level'])<p class="small text-secondary">Nivel máximo actual alcanzado.</p>@else<p class="small text-secondary">{{ $overview['summary']['progress']['current'] }} / {{ $overview['summary']['progress']['required'] }} EXP · Faltan {{ $overview['summary']['progress']['remaining'] }} para nivel {{ $overview['summary']['progress']['next_level'] }}</p>@endif
  <div class="mt-auto border rounded p-3 d-flex justify-content-between"><span>Oro</span><strong>{{ $overview['summary']['gold'] }}</strong></div>
</div></section>
