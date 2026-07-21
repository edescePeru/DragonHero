<?php

namespace App\Http\Controllers;

use App\Domain\Characters\Actions\CreateCharacterAction;
use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Characters\Progression\CharacterProgressionService;
use App\Domain\Media\MediaAssetType;
use App\Domain\Equipment\CharacterEquipmentSummaryService;
use App\Http\Requests\Characters\CreateCharacterRequest;
use App\Models\Character;
use App\Domain\Characters\Templates\CharacterTemplateReadService;
use App\Domain\Characters\Accounts\CharacterAccountLimit;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function create(Request $request, CharacterTemplateReadService $templates)
    {
        $this->authorize('create', Character::class);

        return view('characters.create', $templates->creationOptions());
    }

    public function store(CreateCharacterRequest $request, CreateCharacterAction $action)
    {
        $this->authorize('create', Character::class);

        $data = $request->validated();
        $character = $action->execute($request->user(), $data['name'], $data['template_id']);

        return redirect()->route('dashboard');
    }

    public function show(
        Character $character,
        CharacterStatsCalculator $calculator,
        CharacterProgressionService $progressionService,
        CharacterEquipmentSummaryService $equipmentService
    )
    {
        $this->authorize('view', $character);

        $character->load(['mediaAssets' => function ($query) {
            $query->where('asset_type', MediaAssetType::PORTRAIT);
        }]);

        $statsBreakdown = $calculator->breakdown($character);
        $stats = $statsBreakdown->effective();
        $experienceProgress = $progressionService->experienceProgress($character);
        $equipmentSummary = $equipmentService->snapshot($character, $statsBreakdown->equipmentSources());

        return view('characters.show', compact('character', 'stats', 'statsBreakdown', 'experienceProgress', 'equipmentSummary'));
    }
}
