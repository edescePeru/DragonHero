<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Content\ContentAdministratorAuthorization;
use Closure;

final class EnsureContentAdministrator
{
    private $authorization;

    public function __construct(ContentAdministratorAuthorization $authorization)
    {
        $this->authorization = $authorization;
    }

    public function handle($request, Closure $next)
    {
        if (!$this->authorization->allows($request->user())) {
            abort(403);
        }

        return $next($request);
    }
}
