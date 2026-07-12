<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $character = $request->user()->characters()->firstOrFail();

        return view('admin', compact('character'));
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
