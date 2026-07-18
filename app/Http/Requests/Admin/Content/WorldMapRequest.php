<?php
namespace App\Http\Requests\Admin\Content;
use App\Domain\WorldMaps\WorldMapStatus;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class WorldMapRequest extends FormRequest
{
    public function authorize(){return true;}
    protected function prepareForValidation(){$this->merge(['world_id'=>$this->filled('world_id')?$this->input('world_id'):null,'region_id'=>$this->filled('region_id')?$this->input('region_id'):null,'is_default'=>$this->boolean('is_default'),'confirm_aspect_ratio_change'=>$this->boolean('confirm_aspect_ratio_change')]);}
    public function rules(){$map=$this->route('worldMap');return['world_id'=>'nullable|integer|exists:worlds,id','region_id'=>'nullable|integer|exists:regions,id','code'=>['required','string','max:64','regex:/^[a-z0-9_-]+$/',Rule::unique('world_maps','code')->ignore($map?$map->id:null)],'name'=>'required|string|max:255','description'=>'nullable|string|max:5000','image'=>[$map?'nullable':'required','file','max:'.config('world_maps.max_file_size_kb',5120)],'status'=>['required',Rule::in(WorldMapStatus::values())],'is_default'=>'required|boolean','sort_order'=>'required|integer|min:0','version'=>[$map?'required':'nullable','integer','min:1'],'confirm_aspect_ratio_change'=>'boolean'];}
    public function withValidator($validator){$validator->after(function($validator){$contexts=($this->filled('world_id')?1:0)+($this->filled('region_id')?1:0);if($contexts!==1){$message='Selecciona un Mundo o una Región, pero no ambos.';$validator->errors()->add('world_id',$message);$validator->errors()->add('region_id',$message);}});}
    public function messages(){return['image.required'=>'La imagen es obligatoria al crear un mapa.','code.regex'=>'El código solo puede contener minúsculas, números, guiones y guiones bajos.'];}
}
