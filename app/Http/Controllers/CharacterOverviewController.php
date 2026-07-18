<?php

namespace App\Http\Controllers;

use App\Domain\Characters\Overview\CharacterOverviewService;
use App\Models\Character;

final class CharacterOverviewController extends Controller
{
    public function __invoke(Character $character, CharacterOverviewService $overview)
    {
        $this->authorize('view', $character);

        return view('characters.overview', [
            'character' => $character,
            'overview' => $overview->snapshot($character),
        ]);
    }
}
