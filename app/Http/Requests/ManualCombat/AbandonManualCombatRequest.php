<?php
namespace App\Http\Requests\ManualCombat;
use Illuminate\Foundation\Http\FormRequest;
class AbandonManualCombatRequest extends FormRequest
{
    public function authorize(){return true;}
    public function rules(){return['client_request_id'=>'required|uuid|max:36','expected_lock_version'=>'required|integer|min:0'];}
    public function withValidator($validator){$validator->after(function($validator){foreach(['status','reason','rewards','active_slot','completed_at']as$field)if($this->exists($field))$validator->errors()->add($field,'This field is controlled by the server.');});}
}
