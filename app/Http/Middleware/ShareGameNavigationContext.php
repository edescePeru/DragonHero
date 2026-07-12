<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\View;
class ShareGameNavigationContext { public function handle($request,Closure $next){$character=$request->user()->characters()->orderBy('id')->first();View::share('navigationCharacter',$character);return $next($request);} }
