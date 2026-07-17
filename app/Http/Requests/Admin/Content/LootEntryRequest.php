<?php
namespace App\Http\Requests\Admin\Content;use App\Domain\WorldCatalog\CatalogStatus;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class LootEntryRequest extends FormRequest{public function authorize(){return true;}public function rules(){return['item_id'=>'required|integer|exists:items,id','drop_chance_basis_points'=>'required|integer|min:0|max:10000','minimum_quantity'=>'required|integer|min:1','maximum_quantity'=>'required|integer|min:1|gte:minimum_quantity','status'=>['required',Rule::in(CatalogStatus::values())],'sort_order'=>'required|integer|min:0'];}}
