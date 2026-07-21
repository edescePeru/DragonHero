<?php
namespace App\Http\Middleware;
use App\Domain\Characters\Accounts\ActiveCharacterContext;use Closure;
final class EnsureRouteUsesActiveCharacter{private $context;public function __construct(ActiveCharacterContext $context){$this->context=$context;}public function handle($request,Closure $next){$routeCharacter=$request->route('character');if(!$routeCharacter)return$next($request);if((int)$routeCharacter->user_id!==(int)$request->user()->id)abort(403);$active=$this->context->current($request->user());if(!$active||$active->id!==$routeCharacter->id)return redirect()->route('characters.select')->withErrors(['character'=>'Selecciona ese personaje antes de continuar.']);return$next($request);}}
