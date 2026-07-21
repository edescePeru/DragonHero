<section class="card">
    <div class="card-header">Estadísticas del combate</div>
    <div class="card-body">
        <div class="manual-combat-stats-grid">
            @foreach([
                'Vida máxima' => $stats['max_health'],
                'Ataque' => $stats['attack'],
                'Defensa' => $stats['defense'],
                'Precisión' => $stats['accuracy_rate'].'%',
                'Evasión' => $stats['evasion_rate'].'%',
                'Crítico' => $stats['critical_chance'].'%',
                'Multiplicador crítico' => '×'.$stats['critical_damage_multiplier'],
                'Velocidad de ataque' => $stats['attack_speed'],
                'Reducción de daño' => $stats['damage_reduction_rate'].'%',
            ] as $label => $value)
                <div class="manual-combat-stat"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
            @endforeach
        </div>
    </div>
</section>
