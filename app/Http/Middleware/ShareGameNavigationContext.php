<?php
namespace App\Http\Middleware;
use App\Domain\Admin\Content\ContentAdministratorAuthorization;use App\Domain\Characters\Accounts\ActiveCharacterContext;use Closure;use Illuminate\Support\Facades\View;
final class ShareGameNavigationContext{private $contentAuthorization;private $context;public function __construct(ContentAdministratorAuthorization $contentAuthorization,ActiveCharacterContext $context){$this->contentAuthorization=$contentAuthorization;$this->context=$context;}public function handle($request,Closure $next){$user=$request->user();View::share('navigationCharacter',$user?$this->context->current($user):null);View::share('canAdministerContent',$this->contentAuthorization->allows($user));return$next($request);}}
