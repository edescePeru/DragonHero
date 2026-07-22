@extends('layouts.game')
@section('title', 'Admin · Progresión de personajes')
@section('content')
@include('admin.content.partials.navigation')
@include('admin.content.partials.messages')
<h1>Progresión de personajes</h1>
<p class="text-secondary">La experiencia se conserva como total acumulado. Los cambios se aplican de forma atómica a toda la curva.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admin.content.progression.update') }}">
    @csrf @method('PUT')
    <input type="hidden" name="version" value="{{ $setting->version }}">
    <section class="card mb-4">
        <div class="card-header">Configuración global</div>
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label" for="max-character-level">Nivel máximo actual</label><input id="max-character-level" class="form-control" name="max_character_level" inputmode="numeric" value="{{ old('max_character_level', $setting->max_character_level) }}" required></div>
            <div class="col-md-3"><span class="form-label d-block">Último nivel configurado</span><strong data-last-configured-level>{{ $last_configured_level }}</strong></div>
            <div class="col-md-3"><span class="form-label d-block">Nivel más alto existente</span><strong>{{ $highest_character_level }}</strong></div>
            <div class="col-md-3"><span class="form-label d-block">Versión</span><strong>{{ $setting->version }}</strong></div>
            <div class="col-md-6"><span class="form-label d-block">Última actualización</span>{{ $setting->updated_at }}</div>
            <div class="col-md-6"><span class="form-label d-block">Último administrador</span>{{ $setting->administrator ? $setting->administrator->email : 'Configuración inicial' }}</div>
            <div class="col-12"><label class="form-label" for="progression-reason">Motivo del cambio</label><textarea id="progression-reason" class="form-control" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea><div class="form-text">Obligatorio. Quedará registrado junto con los valores anteriores y nuevos.</div></div>
        </div>
    </section>
    <section class="card">
        <div class="card-header">Curva de experiencia</div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nivel</th><th>EXP acumulada requerida para alcanzar este nivel</th><th>EXP desde el nivel anterior</th><th>Estado</th></tr></thead><tbody data-progression-rows>
        @foreach($curve as $index => $row)
            <tr data-progression-row data-level="{{ $row['level'] }}"><td><input type="hidden" name="curve[{{ $index }}][level]" value="{{ $row['level'] }}"><strong data-level-label>{{ $row['level'] }}</strong></td><td><input class="form-control" data-experience-input name="curve[{{ $index }}][required_experience]" inputmode="numeric" value="{{ old('curve.'.$index.'.required_experience', $row['required_experience']) }}" required></td><td data-experience-delta>{{ $row['experience_from_previous'] === null ? '—' : $row['experience_from_previous'] }}</td><td>@if($row['status']==='maximum')<span class="badge bg-primary">NIVEL MÁXIMO ACTUAL</span>@elseif($row['status']==='future')<span class="badge bg-secondary">PREPARADO PARA FUTURA AMPLIACIÓN</span>@else<span class="badge bg-success">ACTIVO</span>@endif</td></tr>
        @endforeach
        </tbody></table></div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary" type="button" data-add-level>+ Agregar siguiente nivel</button>
            <button class="btn btn-outline-danger" type="button" data-remove-level @if($last_configured_level <= $setting->max_character_level) disabled @endif>Eliminar último nivel</button>
            <button class="btn btn-primary ms-auto" type="submit">Guardar curva completa</button>
        </div>
    </section>
</form>
<template data-progression-row-template><tr data-progression-row><td><input type="hidden"><strong data-level-label></strong></td><td><input class="form-control" data-experience-input inputmode="numeric" value="" required></td><td data-experience-delta>—</td><td><span class="badge bg-secondary">PREPARADO PARA FUTURA AMPLIACIÓN</span> <span class="badge bg-warning text-dark">SIN GUARDAR</span></td></tr></template>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var body = document.querySelector('[data-progression-rows]');
    var add = document.querySelector('[data-add-level]');
    var remove = document.querySelector('[data-remove-level]');
    var lastLabel = document.querySelector('[data-last-configured-level]');
    var capInput = document.getElementById('max-character-level');
    var template = document.querySelector('[data-progression-row-template]');
    function rows() { return Array.prototype.slice.call(body.querySelectorAll('[data-progression-row]')); }
    function refresh() {
        var list = rows();
        list.forEach(function (row, index) {
            var level = index + 1;
            row.dataset.level = String(level);
            row.querySelector('input[type="hidden"]').name = 'curve[' + index + '][level]';
            row.querySelector('input[type="hidden"]').value = String(level);
            row.querySelector('[data-level-label]').textContent = String(level);
            row.querySelector('[data-experience-input]').name = 'curve[' + index + '][required_experience]';
            var current = row.querySelector('[data-experience-input]').value;
            var previous = index === 0 ? null : list[index - 1].querySelector('[data-experience-input]').value;
            var valid = /^\d+$/.test(current) && (index === 0 ? Number(current) === 0 : /^\d+$/.test(previous) && Number(current) > Number(previous));
            row.querySelector('[data-experience-delta]').textContent = valid ? (index === 0 ? '—' : String(Number(current) - Number(previous))) : '—';
        });
        var last = list.length;
        lastLabel.textContent = String(last);
        remove.disabled = last <= Number(capInput.value || 0) || last <= 1;
    }
    add.addEventListener('click', function () { body.appendChild(template.content.firstElementChild.cloneNode(true)); refresh(); });
    remove.addEventListener('click', function () { var list = rows(); if (!remove.disabled && list.length) { list[list.length - 1].remove(); refresh(); } });
    body.addEventListener('input', function (event) { if (event.target.matches('[data-experience-input]')) refresh(); });
    capInput.addEventListener('input', refresh);
    refresh();
});
</script>
@endpush
@endsection
