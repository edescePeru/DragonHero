<?php

namespace App\Http\Middleware;

use Closure;

class EnsureUserHasCharacter
{
    public function handle($request, Closure $next)
    {
        if (! $request->user()->characters()->exists()) {
            return redirect()->route('characters.create');
        }

        return $next($request);
    }
}
