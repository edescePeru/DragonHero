<?php

namespace App\Http\Requests\Characters;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCharacterRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge([
                'name' => preg_replace('/\s+/u', ' ', trim($name)),
            ]);
        }
    }

    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('characters', 'name'),
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras, números, espacios, guiones y guiones bajos.',
        ];
    }
}
