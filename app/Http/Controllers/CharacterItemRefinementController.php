<?php
namespace App\Http\Controllers;
use App\Domain\Inventory\Instances\Refinement\ItemRefinementService;use App\Http\Requests\RefineItemInstanceRequest;use App\Models\Character;use App\Models\ItemInstance;use InvalidArgumentException;
final class CharacterItemRefinementController extends Controller { public function __invoke(RefineItemInstanceRequest $request,Character $character,ItemInstance $itemInstance,ItemRefinementService $service){$this->authorize('view',$character);try{$result=$service->refine($character,$itemInstance,$request->validated()['refinement_token']);return redirect()->route('characters.inventory.index',$character)->with('status',$result->message());}catch(InvalidArgumentException $e){return back()->withErrors(['refinement'=>$e->getMessage()]);}} }
