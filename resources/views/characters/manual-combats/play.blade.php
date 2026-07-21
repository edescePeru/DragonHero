@extends('layouts.game')
@section('title', 'Combate manual')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/manual-combat.css') }}?v={{ filemtime(public_path('assets/css/manual-combat.css')) }}">
@endpush
@section('content')
<div class="manual-combat-page" id="manual-combat-page">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3 manual-combat-page__header">
        <div><a href="{{ $returnUrl }}" class="link-secondary">← Regresar al mapa</a><h1 class="fs-3 mb-0 mt-1">Combate manual</h1></div>
        <span class="badge bg-secondary" id="manual-combat-status">Cargando…</span>
    </header>

    <div class="alert d-none" id="manual-combat-message" role="alert" aria-live="assertive"></div>

    <div class="manual-combat-layout">
        <main class="manual-combat-main">
            <div class="manual-combat-stage-wrap">
                <div class="manual-combat-stage{{ $presentation['background']['transparent'] ? ' manual-combat-stage--transparent' : '' }}" aria-label="Escenario de combate" @if($presentation['background']['url']) style="background-image:url('{{ $presentation['background']['url'] }}')" @endif>
                    <div class="manual-combat-stage__participants">
                        @foreach($combatSession->participants->sortBy('position') as $participant)
                            @php($visual = $presentation['participant_visuals'][(int) $participant->id])
                            <article class="manual-combatant{{ $visual['kind'] === 'character' ? ' manual-combatant--player' : ' manual-combatant--enemy' }}" data-participant-card="{{ $participant->id }}" @if($visual['kind'] === 'monster') data-select-target="{{ $participant->id }}" role="button" tabindex="0" aria-label="Seleccionar a {{ $participant->display_name }}" @endif>
                                <div class="manual-combatant__visual">
                                    @if($visual['kind'] === 'character')
                                        <x-character.appearance :appearance="$visual['appearance']" :name="$participant->display_name" />
                                    @elseif($visual['image_url'])
                                        <img src="{{ $visual['image_url'] }}" alt="{{ $participant->display_name }}" loading="lazy">
                                    @else
                                        <span class="manual-combatant__placeholder" aria-hidden="true">◇</span>
                                    @endif
                                </div>
                                <strong data-participant-name>{{ $participant->display_name }}</strong>
                                <span class="badge bg-secondary" data-participant-state>—</span>
                                <div class="manual-combatant__health-text" data-participant-health>{{ $participant->current_hp }} / {{ $participant->max_hp }} HP</div>
                                <div class="progress manual-combatant__health" role="progressbar" aria-label="Vida de {{ $participant->display_name }}" aria-valuemin="0" aria-valuemax="{{ $participant->max_hp }}" aria-valuenow="{{ $participant->current_hp }}"><div class="progress-bar bg-success" data-participant-health-bar></div></div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

            <section class="card manual-combat-actions" aria-labelledby="manual-combat-actions-title">
                <div class="card-body d-flex flex-wrap align-items-center gap-2">
                    <strong id="manual-combat-actions-title">ACCIONES</strong>
                    <button class="btn btn-primary manual-combat-action-button" type="button" id="manual-combat-attack" disabled><span class="spinner-border spinner-border-sm d-none" id="manual-combat-loader" aria-hidden="true"></span> Ataque básico</button>
                    <span class="small" id="manual-combat-target-message">Selecciona un Monster vivo.</span>
                </div>
            </section>

            @include('characters.manual-combats.partials.equipment', ['equipment' => $presentation['equipment']])
            @include('characters.manual-combats.partials.inventory', ['inventory' => $presentation['inventory']])
            @include('characters.manual-combats.partials.loot')
        </main>

        <aside class="manual-combat-side">
            <section class="card manual-combat-controls">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2" aria-live="polite"><span>Ronda <strong id="manual-combat-round">—</strong></span><span id="manual-combat-turn">Consultando turno…</span></div>
                    <p id="manual-combat-expiration" class="small text-secondary mb-2"></p>
                    <div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-danger" type="button" id="manual-combat-abandon" disabled>Abandonar combate</button></div>
                    <section class="alert alert-secondary mt-3 mb-0 d-none" id="manual-combat-terminal" aria-live="polite"><h2 class="h4" id="manual-combat-terminal-title"></h2><p class="mb-2" id="manual-combat-terminal-message"></p><a class="btn btn-outline-primary" href="{{ $returnUrl }}">Regresar al mapa</a></section>
                </div>
            </section>

            <section class="card manual-combat-log-wrap">
                <div class="card-header">Log de combate</div>
                <div class="card-body manual-combat-log" id="manual-combat-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
            </section>

            @include('characters.manual-combats.partials.combat-stats', ['stats' => $presentation['combat_stats']])
        </aside>
    </div>

    <div class="modal fade" id="manual-combat-abandon-modal" tabindex="-1" aria-labelledby="manual-combat-abandon-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="manual-combat-abandon-title">Abandonar combate</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">Abandonar perderá todas las recompensas pendientes de este combate.</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continuar combatiendo</button><button type="button" class="btn btn-danger" id="manual-combat-confirm-abandon">Abandonar definitivamente</button></div></div></div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.ManualCombatConfig = @json($manualCombatConfig);</script>
<script src="{{ asset('assets/js/manual-combat.js') }}?v={{ filemtime(public_path('assets/js/manual-combat.js')) }}" defer></script>
@endpush
