<?php
namespace App\Http\Controllers\Admin\Content;
use App\Domain\Inventory\Instances\ItemRarityCode;use App\Domain\Inventory\Instances\Rarity\ItemRarityDropConfigurationService;use App\Domain\Probability\PercentagePpmConverter;use App\Http\Controllers\Controller;use App\Http\Requests\Admin\Content\UpdateItemRarityDropRatesRequest;
final class ItemRarityDropRateController extends Controller
{
    public function index(ItemRarityDropConfigurationService $service,PercentagePpmConverter $converter){$config=$service->current();$rows=[];foreach(ItemRarityCode::values() as $code)$rows[]=['code'=>$code,'ppm'=>$config->probability($code),'percent'=>$converter->toPercentageString($config->probability($code))];return view('admin.content.item-rarity-drop-rates.index',['rows'=>$rows,'version'=>$config->version()]);}
    public function update(UpdateItemRarityDropRatesRequest $request,ItemRarityDropConfigurationService $service){try{$service->update($request->user(),$request->probabilities(),(int)$request->input('version'));return redirect()->route('admin.content.item-rarity-drop-rates.index')->with('success','Probabilidades de rareza actualizadas correctamente.');}catch(\InvalidArgumentException $e){return back()->withInput()->withErrors(['version'=>$e->getMessage()]);}}
}
