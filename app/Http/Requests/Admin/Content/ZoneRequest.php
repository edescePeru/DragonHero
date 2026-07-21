<?php

namespace App\Http\Requests\Admin\Content;

use App\Domain\WorldCatalog\CatalogStatus;
use App\Domain\WorldCatalog\ZoneType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if (!$this->has('remove_combat_background')) $this->merge(['remove_combat_background' => 0]);
    }

    public function authorize(){return true;}

    public function rules()
    {
        $zone = $this->route('zone');
        return [
            'region_id'=>'required|integer|exists:regions,id',
            'code'=>['required','string','max:64',Rule::unique('zones','code')->where(function($q){return$q->where('region_id',$this->input('region_id'));})->ignore($zone?$zone->id:null)],
            'name'=>'required|string|max:255','description'=>'nullable|string','zone_type'=>['required',Rule::in(ZoneType::values())],
            'recommended_level_min'=>'required|integer|min:1','recommended_level_max'=>'nullable|integer|gte:recommended_level_min',
            'is_safe'=>'required|boolean','allows_hunting'=>'required|boolean','status'=>['required',Rule::in(CatalogStatus::values())],
            'sort_order'=>'required|integer|min:0','encounter_sizes'=>'required|array|size:3',
            'encounter_sizes.1'=>'required|integer|min:0|max:100','encounter_sizes.2'=>'required|integer|min:0|max:100','encounter_sizes.3'=>'required|integer|min:0|max:100',
            'combat_background'=>'nullable|file|max:5120|mimetypes:image/png,image/jpeg,image/webp',
            'remove_combat_background'=>'required|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function($validator){
            if($this->hasFile('combat_background')&&$this->boolean('remove_combat_background'))$validator->errors()->add('remove_combat_background','No puedes reemplazar y eliminar el escenario al mismo tiempo.');
            $values=$this->input('encounter_sizes');
            if(!is_array($values)||array_map('intval',array_keys($values))!==[1,2,3]){$validator->errors()->add('encounter_sizes','La configuración debe contener únicamente las cantidades 1, 2 y 3.');return;}
            foreach($values as$value)if(filter_var($value,FILTER_VALIDATE_INT)===false)return;
            if(array_sum(array_map('intval',$values))!==100)$validator->errors()->add('encounter_sizes','La suma de las probabilidades para 1, 2 y 3 monstruos debe ser 100%.');
        });
    }
}
