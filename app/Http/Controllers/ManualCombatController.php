<?php

namespace App\Http\Controllers;

use App\Domain\Combat\Manual\ManualCombatCreationService;
use App\Domain\Combat\Manual\ManualCombatReadService;
use App\Domain\Combat\Manual\ManualCombatActionService;
use App\Domain\Combat\Manual\Exceptions\ManualCombatConflictException;
use App\Domain\Combat\Manual\Exceptions\ActiveManualCombatConflictException;
use App\Http\Requests\ManualCombat\CreateManualCombatRequest;
use App\Http\Requests\ManualCombat\ManualCombatActionRequest;
use App\Models\Character;
use App\Models\CombatSession;
use App\Models\HuntingSession;
use InvalidArgumentException;
use App\Domain\Combat\Manual\Rewards\ManualCombatRewardClaimService;
use App\Domain\Combat\Manual\Rewards\Exceptions\CombatRewardDeliveryUnavailableException;
use App\Domain\Combat\Manual\ManualCombatAbandonService;
use App\Http\Requests\ManualCombat\AbandonManualCombatRequest;
use App\Domain\Combat\Manual\ManualCombatPresentationService;
use App\Domain\Combat\Manual\ManualCombatEntryService;
use App\Models\Zone;

final class ManualCombatController extends Controller
{
    public function storeForZone(\Illuminate\Http\Request $request, Character $character, Zone $zone, ManualCombatEntryService $service)
    {
        $this->authorize('view', $character);
        try {
            $entry = $service->enter($request->user(), $character, $zone);
            $payload = $entry['state']->toArray();
            $payload['play_url'] = route('characters.manual-combats.play', [$character, $payload['combat_id']]);
            if ($entry['reused_other_zone']) $payload['message'] = 'Ya existía un combate manual activo en otra zona. Se abrió ese combate.';
            if ($request->expectsJson()) return response()->json($payload);
            return redirect($payload['play_url'])->with('status', isset($payload['message']) ? $payload['message'] : 'Combate manual preparado.');
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) return response()->json(['message' => $exception->getMessage()], 422);
            return back()->withErrors(['hunt' => $exception->getMessage()]);
        }
    }

    public function store(CreateManualCombatRequest $request, Character $character, HuntingSession $huntingSession, ManualCombatCreationService $service)
    {
        $this->authorize('view', $character);
        try {
            $result = $service->create($request->user(), $character, $huntingSession);
            $payload = $result->toArray();
            $payload['play_url'] = route('characters.manual-combats.play', [$character, $payload['combat_id']]);
            return response()->json($payload, 200);
        } catch (ActiveManualCombatConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(\Illuminate\Http\Request $request, Character $character, CombatSession $combatSession, ManualCombatReadService $service, ManualCombatPresentationService $presentation)
    {
        $this->authorize('view', $character);
        if ((int) $combatSession->character_id !== (int) $character->id) abort(404);
        $after = max(0, (int) $request->query('after_sequence', 0));
        return response()->json($this->decorateCombatPayload($service->readFresh($request->user(), $combatSession, $after)->toArray(), $presentation));
    }

    public function play(\Illuminate\Http\Request $request, Character $character, CombatSession $combatSession, ManualCombatPresentationService $presentation)
    {
        $this->authorize('view', $character);
        if ((int) $combatSession->character_id !== (int) $character->id || (int) $combatSession->owner_user_id !== (int) $request->user()->id) abort(404);
        if ($combatSession->hunting_session_id === null) abort(404);
        $combatSession->loadMissing('huntingSession');

        return view('characters.manual-combats.play', [
            'character' => $character,
            'combatSession' => $combatSession,
            'presentation' => $presentation->prepare($character, $combatSession),
            'stateUrl' => route('characters.manual-combats.show', [$character, $combatSession]),
            'actionUrl' => route('characters.manual-combats.actions.store', [$character, $combatSession]),
            'claimUrl' => route('characters.manual-combats.rewards.claim', [$character, $combatSession]),
            'abandonUrl' => route('characters.manual-combats.abandon', [$character, $combatSession]),
            'returnUrl' => $presentation->returnUrl($combatSession),
            'manualCombatConfig' => [
                'stateUrl' => route('characters.manual-combats.show', [$character, $combatSession]),
                'actionUrl' => route('characters.manual-combats.actions.store', [$character, $combatSession]),
                'claimUrl' => route('characters.manual-combats.rewards.claim', [$character, $combatSession]),
                'abandonUrl' => route('characters.manual-combats.abandon', [$character, $combatSession]),
                'returnUrl' => $presentation->returnUrl($combatSession),
                'csrfToken' => csrf_token(),
                'combatId' => (int) $combatSession->id,
                'characterId' => (int) $character->id,
            ],
        ]);
    }

    public function action(ManualCombatActionRequest $request, Character $character, CombatSession $combatSession, ManualCombatActionService $service, ManualCombatPresentationService $presentation)
    {
        $this->authorize('view', $character);
        if ((int) $combatSession->character_id !== (int) $character->id) abort(404);
        try {
            $result = $service->execute($request->user(), $character, $combatSession, $request->validated())->toArray();
            $result['combat'] = $this->decorateCombatPayload($result['combat'], $presentation);
            return response()->json($result);
        } catch (ManualCombatConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function claimRewards(\Illuminate\Http\Request $request, Character $character, CombatSession $combatSession, ManualCombatRewardClaimService $service, ManualCombatPresentationService $presentation)
    {
        $this->authorize('view', $character);
        if ((int) $combatSession->character_id !== (int) $character->id) abort(404);
        try {
            $result = $service->claim($character, $combatSession)->toArray();
            $result['rewards'] = $presentation->decorateRewards($result['rewards']);
            $result['inventory_html'] = $presentation->inventoryHtml($character);
            return response()->json($result);
        } catch (CombatRewardDeliveryUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'inventory_capacity' => $exception->capacity()->toArray()], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    public function abandon(AbandonManualCombatRequest $request,Character $character,CombatSession $combatSession,ManualCombatAbandonService $service,ManualCombatPresentationService $presentation)
    {
        $this->authorize('view',$character);if((int)$combatSession->character_id!==(int)$character->id)abort(404);
        try{$result=$service->abandon($request->user(),$character,$combatSession,$request->validated())->toArray();$result['combat']=$this->decorateCombatPayload($result['combat'],$presentation);return response()->json($result);}
        catch(ManualCombatConflictException $exception){return response()->json(['message'=>$exception->getMessage()],409);}
    }

    private function decorateCombatPayload(array $combat, ManualCombatPresentationService $presentation)
    {
        $combat['rewards'] = $presentation->decorateRewards($combat['rewards']);
        return $combat;
    }
}
