<?php

namespace App\Http\Requests\ManualCombat;

use Illuminate\Foundation\Http\FormRequest;

class CreateManualCombatRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() { return []; }
}
