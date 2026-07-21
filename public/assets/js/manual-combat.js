(function () {
    'use strict';
    var config = window.ManualCombatConfig;
    var root = document.getElementById('manual-combat-page');
    if (!config || !root) return;

    var state = null;
    var selectedTargetId = null;
    var lastEventSequence = 0;
    var requestInFlight = false;
    var uncertainRequest = null;
    var expirationTimer = null;
    var expirationRefreshSent = false;

    function byId(id) { return document.getElementById(id); }
    function uuid() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        var bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes); bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        return Array.prototype.map.call(bytes, function (byte, index) { return ([4, 6, 8, 10].indexOf(index) !== -1 ? '-' : '') + byte.toString(16).padStart(2, '0'); }).join('');
    }
    function clearNode(node) { while (node.firstChild) node.removeChild(node.firstChild); }
    function message(text, type) { var box = byId('manual-combat-message'); box.textContent = text || ''; box.className = text ? 'alert alert-' + (type || 'info') : 'alert d-none'; }
    function request(url, options) {
        options = options || {}; options.headers = options.headers || {};
        options.headers.Accept = 'application/json'; options.headers['X-Requested-With'] = 'XMLHttpRequest'; options.headers['X-CSRF-TOKEN'] = config.csrfToken;
        return fetch(url, options).then(function (response) {
            if (response.status === 401) { window.location.assign('/login'); throw new Error('Sesión expirada.'); }
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) { var error = new Error(data.message || 'No se pudo completar la solicitud.'); error.status = response.status; error.data = data; throw error; }
                return data;
            });
        });
    }
    function participantName(id) { if (!state) return 'Participante'; var found = state.participants.find(function (row) { return Number(row.id) === Number(id); }); return found ? found.name : 'Participante'; }
    function formatEvent(event) {
        var payload = event.payload || {}, target, actor;
        switch (event.type) {
            case 'combat_started': return 'Comienza el combate.';
            case 'round_started': return 'Ronda ' + (payload.round || event.round) + '.';
            case 'turn_started': return 'Turno de ' + participantName(payload.participant_id || event.actor_participant_id) + '.';
            case 'turn_finished': return 'Finaliza el turno de ' + participantName(payload.participant_id || event.actor_participant_id) + '.';
            case 'basic_attack':
                actor = payload.actor ? payload.actor.name : participantName(event.actor_participant_id); target = payload.targets && payload.targets[0] ? payload.targets[0] : null;
                if (!target) return actor + ' realizó un ataque.';
                if (!target.hit) return 'FALLO · ' + actor + ' atacó a ' + target.name + ', pero falló.';
                return (target.critical ? 'CRÍTICO · ' : '') + actor + ' causó ' + target.damage + ' de daño a ' + target.name + '.';
            case 'participant_defeated': return (payload.participant ? payload.participant.name : 'Un participante') + ' fue derrotado.';
            case 'reward_generated': return 'Recompensa provisional generada.';
            case 'combat_won': return 'Victoria.';
            case 'combat_lost': return 'Derrota.';
            case 'rewards_granted': return 'Recompensas entregadas.';
            case 'rewards_pending_claim': return 'Recompensas pendientes de reclamar por falta de espacio.';
            case 'rewards_forfeited': return 'Las recompensas se perdieron.';
            case 'combat_abandoned': return 'El combate fue abandonado.';
            case 'combat_expired': return 'El combate expiró por inactividad.';
            default: return 'Evento de combate: ' + String(event.type).replace(/_/g, ' ') + '.';
        }
    }
    function appendEvents(events) {
        (events || []).slice().sort(function (a, b) { return Number(a.sequence) - Number(b.sequence); }).forEach(function (event) {
            if (Number(event.sequence) <= lastEventSequence) return;
            var row = document.createElement('div'); row.className = 'manual-combat-log__entry'; row.dataset.sequence = String(event.sequence);
            if (event.type === 'basic_attack' && event.payload && event.payload.targets && event.payload.targets[0]) {
                var target = event.payload.targets[0];
                if (target.critical) row.classList.add('manual-combat-log__entry--critical');
                if (!target.hit) row.classList.add('manual-combat-log__entry--miss');
            }
            if (['combat_won', 'combat_lost', 'combat_abandoned', 'combat_expired'].indexOf(event.type) !== -1) row.classList.add('manual-combat-log__entry--result');
            if (event.type.indexOf('reward') !== -1) row.classList.add('manual-combat-log__entry--reward');
            row.textContent = formatEvent(event); byId('manual-combat-log').prepend(row); lastEventSequence = Math.max(lastEventSequence, Number(event.sequence));
        });
        if (lastEventSequence > 0 && byId('manual-combat-log').dataset.initialized !== 'true') { byId('manual-combat-log').scrollTop = 0; byId('manual-combat-log').dataset.initialized = 'true'; }
    }
    function rewardLabel(status) { return { none: 'Sin recompensas', pending: 'Provisionales', pending_claim: 'Pendientes de reclamar', granted: 'Entregadas', forfeited: 'Perdidas' }[status] || status; }
    function renderRewards(rewards) {
        rewards = rewards || { status: 'none', gold: 0, experience: 0, items: [] };
        byId('manual-combat-reward-status').textContent = rewardLabel(rewards.status); byId('manual-combat-reward-gold').textContent = String(rewards.gold || 0); byId('manual-combat-reward-experience').textContent = String(rewards.experience || 0);
        var list = byId('manual-combat-reward-items'); clearNode(list); (rewards.items || []).forEach(function (item) {
            var row = document.createElement('article'); row.className = 'manual-combat-item-card';
            var visual = document.createElement('div'); visual.className = 'manual-combat-item-image';
            if (item.image_url) { var image = document.createElement('img'); image.src = item.image_url; image.alt = ''; image.loading = 'lazy'; visual.appendChild(image); }
            else { var placeholder = document.createElement('span'); placeholder.setAttribute('aria-hidden', 'true'); placeholder.textContent = '◇'; visual.appendChild(placeholder); }
            var name = document.createElement('span'); name.textContent = item.name; var quantity = document.createElement('strong'); quantity.textContent = '×' + item.quantity;
            row.appendChild(visual); row.appendChild(name); row.appendChild(quantity); list.appendChild(row);
        });
        byId('manual-combat-claim').classList.toggle('d-none', !rewards.claim_available);
    }
    function terminalTitle(status) { return { won: 'Victoria', lost: 'Derrota', abandoned: 'Combate abandonado', expired: 'Combate expirado' }[status] || 'Combate finalizado'; }
    function renderTerminal(current) {
        var terminal = ['won', 'lost', 'abandoned', 'expired'].indexOf(current.status) !== -1, box = byId('manual-combat-terminal'); box.classList.toggle('d-none', !terminal);
        if (terminal) { byId('manual-combat-terminal-title').textContent = terminalTitle(current.status); byId('manual-combat-terminal-message').textContent = current.rewards && current.rewards.status === 'pending_claim' ? 'Libera espacio para reclamar todas las recompensas.' : 'El combate ha terminado.'; }
    }
    function renderParticipants(current) {
        var selectable = [];
        current.participants.forEach(function (participant) {
            var card = document.querySelector('[data-participant-card="' + participant.id + '"]'); if (!card) return;
            var defeated = participant.status === 'defeated' || Number(participant.current_hp) <= 0, percent = participant.max_hp > 0 ? Math.max(0, Math.min(100, participant.current_hp * 100 / participant.max_hp)) : 0;
            card.classList.toggle('manual-combatant--defeated', defeated); card.classList.toggle('manual-combatant--current', participant.is_current_turn); card.classList.toggle('manual-combatant--selected', Number(selectedTargetId) === Number(participant.id) && !defeated);
            card.querySelector('[data-participant-state]').textContent = defeated ? 'Derrotado' : (participant.is_current_turn ? 'Turno actual' : 'Vivo'); card.querySelector('[data-participant-health]').textContent = participant.current_hp + ' / ' + participant.max_hp + ' HP';
            var bar = card.querySelector('[data-participant-health-bar]'); bar.style.width = percent + '%'; bar.parentElement.setAttribute('aria-valuenow', String(participant.current_hp));
            if (card.hasAttribute('data-select-target')) { var enabled = participant.selectable && !requestInFlight; card.setAttribute('aria-disabled', enabled ? 'false' : 'true'); card.tabIndex = enabled ? 0 : -1; if (participant.selectable) selectable.push(Number(participant.id)); }
        });
        if (selectedTargetId !== null && selectable.indexOf(Number(selectedTargetId)) === -1) selectedTargetId = null;
        if (selectedTargetId === null && selectable.length === 1) selectedTargetId = selectable[0];
        document.querySelectorAll('[data-participant-card]').forEach(function (card) { card.classList.toggle('manual-combatant--selected', Number(card.dataset.participantCard) === Number(selectedTargetId)); });
        byId('manual-combat-target-message').textContent = selectedTargetId === null ? 'Selecciona un Monster vivo.' : 'Objetivo: ' + participantName(selectedTargetId);
    }
    function renderExpiration(current) {
        if (expirationTimer) clearInterval(expirationTimer); expirationRefreshSent = false;
        var node = byId('manual-combat-expiration'); if (current.seconds_until_expiration === null) { node.textContent = ''; return; }
        var remaining = Number(current.seconds_until_expiration); function tick() { node.textContent = 'Expira por inactividad en ' + Math.max(0, remaining) + ' s'; if (remaining <= 0 && !expirationRefreshSent) { expirationRefreshSent = true; loadCombatState(true); } remaining -= 1; } tick(); expirationTimer = setInterval(tick, 1000);
    }
    function render(current, events) {
        state = current; byId('manual-combat-status').textContent = current.status; byId('manual-combat-round').textContent = String(current.round); byId('manual-combat-turn').textContent = current.status === 'waiting_player' ? 'Tu turno' : (current.current_participant_id ? 'Turno de ' + participantName(current.current_participant_id) : 'Sin turno activo');
        renderParticipants(current); renderRewards(current.rewards); renderTerminal(current); renderExpiration(current); appendEvents(events || current.events);
        var canAttack = !requestInFlight && current.status === 'waiting_player' && (current.actions_available || []).indexOf('basic_attack') !== -1 && selectedTargetId !== null;
        byId('manual-combat-attack').disabled = !canAttack; byId('manual-combat-abandon').disabled = requestInFlight || !current.can_abandon; byId('manual-combat-abandon').classList.toggle('d-none', !current.can_abandon); byId('manual-combat-loader').classList.toggle('d-none', !requestInFlight);
    }
    function loadCombatState(incremental) {
        var url = config.stateUrl + (incremental && lastEventSequence ? '?after_sequence=' + lastEventSequence : '');
        return request(url).then(function (data) { render(data, data.events); return data; }).catch(function (error) { handleError(error, false); });
    }
    function handleError(error, uncertain) {
        requestInFlight = false; if (state) render(state, []);
        if (error.status === 409) { message('El combate cambió en otra ventana. Se actualizó el estado.', 'warning'); return loadCombatState(true); }
        if (error.status === 403 || error.status === 404) { message('El combate no está disponible.', 'danger'); return; }
        if (error.status === 422) { message(error.message, 'warning'); return; }
        if (uncertain) { message('La respuesta de red es incierta. Se consultará el estado antes de permitir reintentar.', 'warning'); return loadCombatState(true); }
        message(error.message || 'Ocurrió un error inesperado.', 'danger');
    }
    function submitBasicAttack() {
        if (requestInFlight || !state || selectedTargetId === null || state.actions_available.indexOf('basic_attack') === -1) return;
        requestInFlight = true; var payload = uncertainRequest || { action_type: 'basic_attack', target_participant_id: selectedTargetId, client_action_id: uuid(), expected_lock_version: state.lock_version }; uncertainRequest = payload; render(state, []); message('', 'info');
        request(config.actionUrl, { method: 'POST', body: JSON.stringify(payload), headers: { 'Content-Type': 'application/json' } }).then(function (data) {
            uncertainRequest = null; requestInFlight = false;
            try {
                render(data.combat, data.events);
            } catch (error) {
                message('El ataque fue procesado, pero la pantalla tuvo que actualizarse.', 'warning');
                loadCombatState(false);
            }
        }, function (error) { requestInFlight = false; handleError(error, !error.status); });
    }
    function submitClaim() { if (requestInFlight) return; requestInFlight = true; render(state, []); request(config.claimUrl, { method: 'POST' }).then(function (data) { requestInFlight = false; state.rewards = data.rewards; render(state, []); message('Recompensas reclamadas correctamente.', 'success'); return loadCombatState(true); }).catch(function (error) { requestInFlight = false; handleError(error, false); }); }
    function submitAbandon() { if (requestInFlight || !state || !state.can_abandon) return; requestInFlight = true; render(state, []); var payload = { client_request_id: uuid(), expected_lock_version: state.lock_version }; request(config.abandonUrl, { method: 'POST', body: JSON.stringify(payload), headers: { 'Content-Type': 'application/json' } }).then(function (data) { requestInFlight = false; if (window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(byId('manual-combat-abandon-modal')).hide(); render(data.combat, data.combat.events); }).catch(function (error) { requestInFlight = false; handleError(error, false); }); }

    document.querySelectorAll('[data-select-target]').forEach(function (target) {
        function select() { if (target.getAttribute('aria-disabled') === 'true') return; selectedTargetId = Number(target.dataset.selectTarget); render(state, []); }
        target.addEventListener('click', select);
        target.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(); } });
    });
    byId('manual-combat-attack').addEventListener('click', submitBasicAttack); byId('manual-combat-claim').addEventListener('click', submitClaim); byId('manual-combat-confirm-abandon').addEventListener('click', submitAbandon);
    byId('manual-combat-abandon').addEventListener('click', function () { if (window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(byId('manual-combat-abandon-modal')).show(); else if (window.confirm('Abandonar perderá todas las recompensas pendientes. ¿Deseas continuar?')) submitAbandon(); });
    loadCombatState(false);
}());
