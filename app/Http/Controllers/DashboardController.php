<?php

namespace App\Http\Controllers;

use App\Domain\Characters\CharacterStatsCalculator;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CharacterStatsCalculator $calculator)
    {
        $character = $request->user()->characters()->firstOrFail();
        $stats = $calculator->calculate($character);

        return view('admin', compact('character', 'stats'));
    }

    public function inventory()
    {
        return view('inventory');
    }

    public function reports()
    {
        return view('reports');
    }

    public function createProduct()
    {
        return view('create-product');
    }

    public function docs()
    {
        return view('docs');
    }
}
