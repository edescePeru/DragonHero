<?php

namespace App\Http\Controllers;

use App\Domain\Characters\Actions\CreateCharacterAction;
use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Media\MediaAssetType;
use App\Http\Requests\Characters\CreateCharacterRequest;
use App\Models\Character;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function create(Request $request)
    {
        $existingCharacter = $request->user()->characters()->first();

        if ($existingCharacter) {
            return redirect()->route('characters.show', $existingCharacter);
        }

        $this->authorize('create', Character::class);

        return view('characters.create');
    }

    public function store(CreateCharacterRequest $request, CreateCharacterAction $action)
    {
        $this->authorize('create', Character::class);

        $character = $action->execute($request->user(), $request->validated()['name']);

        return redirect()->route('characters.show', $character);
    }

    public function show(Character $character, CharacterStatsCalculator $calculator)
    {
        $this->authorize('view', $character);

        $character->load(['mediaAssets' => function ($query) {
            $query->where('asset_type', MediaAssetType::PORTRAIT);
        }]);

        $stats = $calculator->calculate($character);

        return view('characters.show', compact('character', 'stats'));
    }
}
