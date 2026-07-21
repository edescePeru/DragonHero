<?php

namespace App\Http\Requests\ManualCombat;

use App\Domain\Combat\CombatActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualCombatActionRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules()
    {
        return [
            'action_type' => ['required', Rule::in([CombatActionType::BASIC_ATTACK])],
            'target_participant_id' => 'required|integer|min:1',
            'client_action_id' => 'required|uuid|max:36',
            'expected_lock_version' => 'required|integer|min:0',
        ];
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['actor_participant_id','damage','critical','hit','stats','speed','round','team'] as $field) if ($this->exists($field)) $validator->errors()->add($field,'This field is controlled by the server.');
        });
    }
}
