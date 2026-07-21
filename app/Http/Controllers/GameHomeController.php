<?php
namespace App\Http\Controllers;use App\Domain\GameHome\GameHomeService;use Illuminate\Http\Request;
final class GameHomeController extends Controller{public function __invoke(Request $request,GameHomeService $home){return view('game-home.index',['cards'=>$home->cards($request->user())]);}}
