<?php

namespace App\Http\Controllers;

use App\Domain\Shops\ShopReadService;
use App\Models\Character;
use App\Models\Shop;
use Illuminate\Http\Request;

final class ShopController extends Controller
{
    public function show(Request $request, Character $character, Shop $shop, ShopReadService $service)
    {
        $zoneId = $request->query('zone');
        $view = $service->shop($request->user(), $character, $shop, $zoneId);

        return view('shops.show', $view->toArray());
    }
}
