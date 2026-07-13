@extends('layouts.game')
@section('title','Cacería conectada')
@section('content')
<div id="hunting-session" data-tick-url="{{ route('characters.hunting-sessions.tick',[$character,$session['session_id']]) }}" data-zones-url="{{ $zonesUrl }}">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3"><div><a class="link-secondary" href="{{ $zonesUrl }}" id="back-to-zones">← Volver a zonas</a><h1 class="fs-3 mb-0 mt-1">Cacería conectada</h1></div><button class="btn btn-primary align-self-start d-none" type="button" id="stop-and-return">Detener y volver a zonas</button></div>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body">Estado <strong id="session-status">{{ $session['status'] }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">Victorias <strong id="victories-count">{{ $session['victories_count'] }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">Derrotas <strong id="defeats-count">{{ $session['defeats_count'] }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">Empates <strong id="draws-count">{{ $session['draws_count'] }}</strong></div></div></div>
    </div>
    <div class="card mb-3"><div class="card-body">
        <p>Resultados no ganados consecutivos: <strong id="consecutive-count">{{ $session['consecutive_defeats'] }}</strong></p>
        <p>Siguiente encuentro: <strong id="countdown">{{ $session['seconds_until_next_encounter'] === null ? 'Detenido' : $session['seconds_until_next_encounter'].' s' }}</strong></p>
        <p id="stop-message"></p>
    </div></div>
    <form method="POST" action="{{ route('characters.hunting-sessions.stop',[$character,$session['session_id']]) }}" id="stop-form">@csrf<button class="btn btn-danger" type="submit">Detener cacería</button></form>
    <div class="card border-warning mt-3 d-none" id="leave-confirmation" role="dialog" aria-modal="true" aria-labelledby="leave-confirmation-title"><div class="card-body"><h2 class="h5" id="leave-confirmation-title">Tu cacería sigue activa</h2><p>Si sales de esta pantalla, dejarás de enviar heartbeat y la sesión se detendrá por pérdida de conexión. ¿Deseas continuar?</p><div class="d-flex flex-column flex-sm-row gap-2"><button class="btn btn-secondary" type="button" id="keep-hunting">Seguir cazando</button><button class="btn btn-outline-danger" type="button" id="leave-anyway">Salir de todos modos</button></div></div></div>
</div>

<div class="card mt-3" id="latest-hunt-card"><div class="card-header" id="latest-hunt-title">Último encuentro</div><div class="card-body" id="latest-hunt-content"></div></div>
<style>
.combat-log-scroll{height:26rem;overflow-y:auto;overflow-x:hidden;scroll-behavior:smooth}.combat-log-entry{padding:.65rem 0;border-bottom:1px solid rgba(0,0,0,.08);margin:0}.combat-log-entry--critical{font-weight:700;color:#8a5900}.combat-log-entry--miss{color:#5f6872}.combat-log-entry--defeat{font-weight:600}.combat-log-entry--result{font-weight:700}.combat-log-entry--loot{font-weight:600;color:#146c43}@media(max-width:767.98px){.combat-log-scroll{height:20rem}}
</style>
<div class="row g-3 mt-1"><div class="col-lg-8"><div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Participantes</span><button type="button" class="btn btn-sm btn-outline-secondary" id="skip-playback">Saltar animación</button></div><div class="card-body" id="combat-participants"></div></div></div><div class="col-lg-4"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><span id="combat-log-heading">Log de combate</span><button type="button" class="btn btn-sm btn-outline-primary d-none" id="combat-log-new-events">Nuevos eventos · Ir al último</button></div><div class="card-body combat-log-scroll" id="combat-log-scroll" tabindex="0" aria-labelledby="combat-log-heading"><p class="small text-secondary d-none" id="combat-log-truncated">Hay eventos anteriores no mostrados.</p><div id="combat-log" role="log" aria-live="polite" aria-relevant="additions"></div></div></div></div></div>
<div class="card mt-3 d-none" id="generated-reward-card"><div class="card-header">Resultado del último encuentro</div><div class="card-body" id="generated-reward-content"></div></div>
<div class="card mt-3" id="pending-rewards"><div class="card-header">Recompensas pendientes</div><div class="card-body" id="pending-rewards-content"></div></div>
<div class="card mt-3" id="inventory-capacity-card"><div class="card-header">Capacidad de inventario</div><div class="card-body" id="inventory-capacity-content"></div></div>

<script>
(function(){
    const root=document.getElementById('hunting-session');
    const token=document.querySelector('#stop-form input[name="_token"]').value;
    let timer=null,requestInFlight=false,stopRequested=false,stopInFlight=false,redirectAfterStop=false,lastState=@json($session),lastRenderedProcessedHuntId=null,playback=null,playbackFrame=null,initialHistoryRendered=false;
    const renderedHuntIds=new Set(),renderedEventsByHunt=new Map(),huntBlocks=new Map();
    function clearNode(node){while(node.firstChild)node.removeChild(node.firstChild);}
    function paragraph(text){const node=document.createElement('p');node.textContent=text;return node;}
    function clearTimer(){if(timer!==null){clearTimeout(timer);timer=null;}}
    function delayFor(state){if(state.status==='stopped')return null;const seconds=state.seconds_until_next_encounter;if(seconds===null)return 10000;if(seconds<=0)return 250;return Math.max(250,Math.min(10000,seconds*1000));}
    function renderLatestHunt(hunt,isProcessed){
        if(!hunt)return;
        document.getElementById('latest-hunt-title').textContent=isProcessed?'Encuentro recién terminado':'Último encuentro';
        const content=document.getElementById('latest-hunt-content');clearNode(content);
        content.appendChild(paragraph('Resultado: '+hunt.status));
        content.appendChild(paragraph('Rondas: '+hunt.rounds_count+' · Enemigos: '+hunt.enemy_count));
        content.appendChild(paragraph('Vida: '+hunt.character_health_before+' → '+hunt.character_health_after));
    }
    function renderGeneratedReward(processedHunt,reward,isNewProcessedHunt){
        if(!processedHunt||!isNewProcessedHunt)return;
        const card=document.getElementById('generated-reward-card');const content=document.getElementById('generated-reward-content');clearNode(content);
        if(processedHunt.status!=='character_victory'){card.classList.add('d-none');return;}
        card.classList.remove('d-none');
        if(!reward||Number(reward.hunt_id)!==Number(processedHunt.hunt_id)){content.appendChild(paragraph('Victoria.'));return;}
        if(reward.item_lines_count===0||!reward.items||reward.items.length===0){content.appendChild(paragraph('Victoria. Este encuentro no dejó objetos.'));return;}
        content.appendChild(paragraph('Victoria. Objetos encontrados:'));
        const list=document.createElement('ul');reward.items.forEach(function(item){const row=document.createElement('li');row.textContent=item.item_name+' x'+item.quantity;list.appendChild(row);});content.appendChild(list);
    }
    function renderPendingRewardsSummary(summary){
        if(!summary)return;const content=document.getElementById('pending-rewards-content');clearNode(content);
        content.appendChild(paragraph('Recompensas procesadas: '+summary.rewards_count));
        content.appendChild(paragraph('Recompensas con objetos: '+summary.rewards_with_items_count));
        content.appendChild(paragraph('Líneas: '+summary.item_lines_count+' · Unidades: '+summary.total_quantity));
        if(summary.items&&summary.items.length){const list=document.createElement('ul');summary.items.forEach(function(item){const row=document.createElement('li');row.textContent=item.item_name+' x'+item.quantity;list.appendChild(row);});content.appendChild(list);}else content.appendChild(paragraph('No hay objetos pendientes.'));
        const note=document.createElement('p');note.className='text-secondary mb-0';note.textContent='Las recompensas se almacenarán hasta que se implemente la reclamación.';content.appendChild(note);
    }
    function renderInventoryCapacity(capacity){
        if(!capacity)return;const content=document.getElementById('inventory-capacity-content');clearNode(content);
        content.appendChild(paragraph('Capacidad efectiva: '+capacity.effective_capacity));
        content.appendChild(paragraph('Actual: '+capacity.current_used_slots+' usados · '+capacity.current_free_slots+' libres'));
        content.appendChild(paragraph('Proyectado: '+capacity.projected_used_slots+' usados · '+capacity.projected_free_slots+' libres'));
        content.appendChild(paragraph('Reserva: '+capacity.effective_reserve+' · Faltantes para reclamar: '+capacity.missing_slots_for_claim));
        content.appendChild(paragraph('Reclamación cabe: '+(capacity.claim_fits?'sí':'no')+' · Cacería puede continuar: '+(capacity.hunting_can_continue?'sí':'no')));
    }
    function participantMap(hunt){const map=new Map();(hunt.participants||[]).forEach(function(participant){map.set(participant.identifier,participant);});return map;}
    function buildParticipants(hunt){const root=document.getElementById('combat-participants');clearNode(root);const nodes=new Map();(hunt.participants||[]).forEach(function(participant){const wrap=document.createElement('div');wrap.className='mb-3';const label=document.createElement('div');label.className='d-flex justify-content-between';const name=document.createElement('strong');name.textContent=participant.display_name;const health=document.createElement('span');health.textContent=participant.initial_health+' / '+participant.initial_health;label.appendChild(name);label.appendChild(health);const progress=document.createElement('div');progress.className='progress';const bar=document.createElement('div');bar.className='progress-bar bg-success';bar.style.width='100%';progress.appendChild(bar);wrap.appendChild(label);wrap.appendChild(progress);root.appendChild(wrap);nodes.set(participant.identifier,{health:health,bar:bar,max:Number(participant.initial_health)});});return nodes;}
    function updateParticipant(nodes,identifier,health){const node=nodes.get(identifier);if(!node)return;const safe=Math.max(0,Number(health));node.health.textContent=safe+' / '+node.max;node.bar.style.width=(node.max>0?Math.min(100,safe*100/node.max):0)+'%';if(safe===0){node.bar.classList.remove('bg-success');node.bar.classList.add('bg-danger');}}
    function combatLogEntry(text,modifier){const row=paragraph(text);row.className='combat-log-entry'+(modifier?' combat-log-entry--'+modifier:'');return row;}
    function preserveLogPosition(callback){const scroll=document.getElementById('combat-log-scroll');const wasAtTop=scroll.scrollTop<=20;callback();if(wasAtTop)scroll.scrollTop=0;else document.getElementById('combat-log-new-events').classList.remove('d-none');}
    function ensureHuntBlock(hunt){const id=Number(hunt.hunt_id);if(renderedHuntIds.has(id))return huntBlocks.get(id);const block=document.createElement('section');block.className='combat-log-hunt border-bottom pb-2 mb-2';block.dataset.huntId=String(id);const heading=document.createElement('h3');heading.className='h6 mb-1';heading.textContent='Encuentro '+hunt.encounter_number;const meta=document.createElement('p');meta.className='small text-secondary mb-2';meta.textContent='Rondas: '+hunt.rounds_count+' · Resultado: '+resultMessage(hunt.status);const entries=document.createElement('div');entries.className='combat-log-hunt-events';block.appendChild(heading);block.appendChild(meta);block.appendChild(entries);preserveLogPosition(function(){document.getElementById('combat-log').prepend(block);});renderedHuntIds.add(id);renderedEventsByHunt.set(id,new Set());huntBlocks.set(id,{root:block,entries:entries});return huntBlocks.get(id);}
    function prependCombatLog(playbackState,node){preserveLogPosition(function(){playbackState.block.entries.prepend(node);});}
    function appendEventLog(event,names,playbackState){const actor=names.get(event.actor_identifier);const target=names.get(event.target_identifier);const actorName=actor?actor.display_name:event.actor_identifier;const targetName=target?target.display_name:event.target_identifier;let message,modifier='';if(!event.did_hit){message='FALLO · '+actorName+' atacó a '+targetName+', pero falló.';modifier='miss';}else if(event.is_critical){message='CRÍTICO · '+actorName+' hizo '+event.damage+' de daño a '+targetName+'.';modifier='critical';}else message=actorName+' atacó a '+targetName+' e hizo '+event.damage+' de daño.';prependCombatLog(playbackState,combatLogEntry(message,modifier));if(Number(event.target_health_before)>0&&Number(event.target_health_after)===0)prependCombatLog(playbackState,combatLogEntry(targetName+' fue derrotado.','defeat'));}
    function resultMessage(status){if(status==='character_victory')return'Victoria.';if(status==='monster_victory')return'Derrota.';return'El combate terminó en empate.';}
    function renderPlaybackAt(playbackState,elapsed){const hunt=playbackState.hunt;const names=playbackState.names;(hunt.events||[]).forEach(function(event){const end=Number(event.playback_offset_ms)+Number(event.playback_duration_ms);if(end<=elapsed&&!playbackState.renderedSequences.has(Number(event.sequence))){appendEventLog(event,names,playbackState);updateParticipant(playbackState.nodes,event.target_identifier,event.target_health_after);playbackState.renderedSequences.add(Number(event.sequence));}});if(elapsed>=Number(hunt.combat_events_duration_ms)&&!playbackState.resultRendered){prependCombatLog(playbackState,combatLogEntry(resultMessage(hunt.status),'result'));playbackState.resultRendered=true;}const lootStart=Number(hunt.combat_events_duration_ms)+Number(hunt.result_reveal_duration_ms);if(elapsed>=lootStart&&hunt.status==='character_victory'&&!playbackState.lootRendered){const reward=playbackState.reward;const card=document.getElementById('generated-reward-card');const content=document.getElementById('generated-reward-content');clearNode(content);card.classList.remove('d-none');let lootMessage;if(!reward||!reward.items||reward.items.length===0){lootMessage='Recompensas obtenidas · Este encuentro no dejó objetos.';content.appendChild(paragraph('Victoria. Este encuentro no dejó objetos.'));}else{lootMessage='Recompensas obtenidas · '+reward.items.map(function(item){return item.item_name+' x'+item.quantity;}).join(', ');content.appendChild(paragraph('Objetos encontrados:'));const list=document.createElement('ul');reward.items.forEach(function(item){const row=document.createElement('li');row.textContent=item.item_name+' x'+item.quantity;list.appendChild(row);});content.appendChild(list);}prependCombatLog(playbackState,combatLogEntry(lootMessage,'loot'));playbackState.lootRendered=true;}if(playbackState.initialRender){document.getElementById('combat-log-scroll').scrollTop=0;document.getElementById('combat-log-new-events').classList.add('d-none');playbackState.initialRender=false;}}
    function playbackLoop(){if(!playback)return;const elapsed=Math.min(Number(playback.hunt.playback_duration_ms),playback.initialElapsed+(performance.now()-playback.startedPerformance));renderPlaybackAt(playback,elapsed);if(elapsed<Number(playback.hunt.playback_duration_ms))playbackFrame=requestAnimationFrame(playbackLoop);else playbackFrame=null;}
    function playbackStateFor(hunt,reward,buildMainParticipants){const id=Number(hunt.hunt_id),block=ensureHuntBlock(hunt);return{hunt:hunt,reward:reward||hunt.historical_reward,nodes:buildMainParticipants?buildParticipants(hunt):new Map(),names:participantMap(hunt),renderedSequences:renderedEventsByHunt.get(id),resultRendered:false,lootRendered:false,initialRender:false,block:block};}
    function renderInitialHistory(history,serverTime){if(initialHistoryRendered||!history)return;initialHistoryRendered=true;if(history.has_more)document.getElementById('combat-log-truncated').classList.remove('d-none');const hunts=history.hunts||[];for(let index=hunts.length-1;index>=1;index--){const state=playbackStateFor(hunts[index],hunts[index].historical_reward,false);renderPlaybackAt(state,Number(hunts[index].playback_duration_ms));}if(hunts.length>0)startPlayback(hunts[0],hunts[0].historical_reward,serverTime);document.getElementById('combat-log-scroll').scrollTop=0;}
    function startPlayback(hunt,reward,serverTime){if(!hunt)return;if(playback&&Number(playback.hunt.hunt_id)===Number(hunt.hunt_id))return;if(playbackFrame!==null)cancelAnimationFrame(playbackFrame);const elapsed=Math.max(0,Date.parse(serverTime)-Date.parse(hunt.resolved_at));playback=playbackStateFor(hunt,reward,true);playback.initialElapsed=elapsed;playback.startedPerformance=performance.now();playback.initialRender=true;if(!hunt.events||hunt.events.length===0){renderPlaybackAt(playback,Number(hunt.playback_duration_ms));return;}playbackLoop();}
    function completeCurrentPlaybackImmediately(){if(!playback)return;if(playbackFrame!==null){cancelAnimationFrame(playbackFrame);playbackFrame=null;}renderPlaybackAt(playback,Number(playback.hunt.playback_duration_ms));const scroll=document.getElementById('combat-log-scroll');scroll.scrollTop=0;document.getElementById('combat-log-new-events').classList.add('d-none');}
    function render(state){
        lastState=state;
        document.getElementById('session-status').textContent=state.status;
        document.getElementById('victories-count').textContent=state.victories_count;
        document.getElementById('defeats-count').textContent=state.defeats_count;
        document.getElementById('draws-count').textContent=state.draws_count;
        document.getElementById('consecutive-count').textContent=state.consecutive_defeats;
        const processed=state.processed_hunt;
        const isNew=processed!==null&&Number(processed.hunt_id)!==Number(lastRenderedProcessedHuntId);
        if(!initialHistoryRendered&&state.session_hunt_history)renderInitialHistory(state.session_hunt_history,state.server_time);
        if(processed)renderLatestHunt(processed,true);else if(lastRenderedProcessedHuntId===null)renderLatestHunt(state.latest_hunt,false);
        if(processed&&isNew)startPlayback(processed,state.generated_reward,state.server_time);else if(!playback&&state.latest_hunt)startPlayback(state.latest_hunt,state.latest_hunt.historical_reward,state.server_time);
        if(processed&&isNew)lastRenderedProcessedHuntId=Number(processed.hunt_id);
        renderPendingRewardsSummary(state.pending_rewards_summary);
        renderInventoryCapacity(state.inventory_capacity);
        document.getElementById('countdown').textContent=state.seconds_until_next_encounter===null?'Detenido':state.seconds_until_next_encounter+' s';
        const stop=document.getElementById('stop-message');stop.textContent='';
        if(state.stop_reason==='consecutive_defeats')stop.textContent='La cacería se detuvo después de 3 encuentros no ganados consecutivos.';
        if(state.stop_reason==='heartbeat_timeout')stop.textContent='La cacería se detuvo por pérdida de conexión.';
        if(state.stop_reason==='pending_inventory_capacity')stop.textContent='La cacería se detuvo para proteger la capacidad del inventario.';
        if(state.status==='stopped')completeCurrentPlaybackImmediately();
        document.querySelector('#stop-form button').disabled=stopRequested||state.status==='stopped';const returnButton=document.getElementById('stop-and-return');returnButton.classList.toggle('d-none',state.status!=='running');returnButton.disabled=stopRequested;
    }
    function schedule(delay){clearTimer();if(!stopRequested&&delay!==null)timer=setTimeout(tick,delay);}
    async function sendStop(){
        if(stopInFlight||!stopRequested||requestInFlight)return;stopInFlight=true;
        const form=document.getElementById('stop-form');
        try{const response=await fetch(form.action,{method:'POST',headers:{'X-CSRF-TOKEN':token,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!response.ok)throw new Error();const state=await response.json();render(state);if(state.status==='stopped'){clearTimer();document.getElementById('stop-message').textContent='Cacería detenida. Se conservaron todos los encuentros y recompensas obtenidos hasta este momento.';if(redirectAfterStop)window.location.assign(root.dataset.zonesUrl);}}
        catch(error){document.getElementById('stop-message').textContent='No se pudo confirmar la detención. Revisa el estado de la sesión y vuelve a intentarlo.';document.querySelector('#stop-form button').disabled=false;document.getElementById('stop-and-return').disabled=false;}
        finally{stopInFlight=false;}
    }
    function requestStop(shouldRedirect){
        if(stopInFlight)return;redirectAfterStop=Boolean(shouldRedirect);stopRequested=true;clearTimer();document.querySelector('#stop-form button').disabled=true;document.getElementById('stop-and-return').disabled=true;completeCurrentPlaybackImmediately();if(!requestInFlight)sendStop();
    }
    async function tick(){
        if(requestInFlight||stopRequested)return;requestInFlight=true;clearTimer();
        try{const response=await fetch(root.dataset.tickUrl,{method:'POST',headers:{'X-CSRF-TOKEN':token,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!response.ok)throw new Error();const state=await response.json();render(state);if(stopRequested)completeCurrentPlaybackImmediately();else schedule(delayFor(state));}
        catch(error){if(!stopRequested)schedule(5000);}
        finally{requestInFlight=false;if(stopRequested)sendStop();}
    }
    document.getElementById('skip-playback').addEventListener('click',completeCurrentPlaybackImmediately);
    document.getElementById('stop-form').addEventListener('submit',function(event){event.preventDefault();requestStop(false);});
    document.getElementById('stop-and-return').addEventListener('click',function(){requestStop(true);});
    document.getElementById('back-to-zones').addEventListener('click',function(event){if(lastState.status!=='running')return;event.preventDefault();document.getElementById('leave-confirmation').classList.remove('d-none');document.getElementById('keep-hunting').focus();});
    document.getElementById('keep-hunting').addEventListener('click',function(){document.getElementById('leave-confirmation').classList.add('d-none');document.getElementById('back-to-zones').focus();});
    document.getElementById('leave-anyway').addEventListener('click',function(){window.location.assign(root.dataset.zonesUrl);});
    document.getElementById('combat-log-new-events').addEventListener('click',function(){const scroll=document.getElementById('combat-log-scroll');scroll.scrollTop=0;this.classList.add('d-none');});
    document.getElementById('combat-log-scroll').addEventListener('scroll',function(){if(this.scrollTop<=20)document.getElementById('combat-log-new-events').classList.add('d-none');});
    render(lastState);schedule(lastState.status==='running'?250:null);
})();
</script>
@endsection
