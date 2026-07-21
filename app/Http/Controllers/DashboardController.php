<?php

namespace App\Http\Controllers;


class DashboardController extends Controller
{
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
