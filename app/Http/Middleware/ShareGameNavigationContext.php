<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Content\ContentAdministratorAuthorization;
use Closure;
use Illuminate\Support\Facades\View;

class ShareGameNavigationContext
{
    private $contentAuthorization;

    public function __construct(ContentAdministratorAuthorization $contentAuthorization)
    {
        $this->contentAuthorization = $contentAuthorization;
    }

    public function handle($request, Closure $next)
    {
        $user = $request->user();
        $character = $user->characters()->orderBy('id')->first();

        View::share('navigationCharacter', $character);
        View::share('canAdministerContent', $this->contentAuthorization->allows($user));

        return $next($request);
    }
}
