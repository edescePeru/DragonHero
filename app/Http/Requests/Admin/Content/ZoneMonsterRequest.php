<?php
namespace App\Http\Requests\Admin\Content;use App\Domain\WorldCatalog\CatalogStatus;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class ZoneMonsterRequest extends FormRequest{public function authorize(){return true;}public function rules(){return['monster_id'=>'required|integer|exists:monsters,id','weight'=>'required|integer|min:1','minimum_character_level'=>'required|integer|min:1','maximum_character_level'=>'nullable|integer|gte:minimum_character_level','status'=>['required',Rule::in(CatalogStatus::values())]];}}
