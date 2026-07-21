<?php
namespace App\Http\Middleware;
use App\Domain\Characters\Accounts\ActiveCharacterContext;use Closure;
final class EnsureUserHasCharacter{private $context;public function __construct(ActiveCharacterContext $context){$this->context=$context;}public function handle($request,Closure $next){$user=$request->user();if(!$user->characters()->exists())return redirect()->route('characters.create');if(!$this->context->current($user)){if($user->active_character_id)$this->context->clear($user);return redirect()->route('characters.select');}return$next($request);}}
