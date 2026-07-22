<?php

namespace App\Http\Requests\Admin\Content;

use App\Domain\WorldCatalog\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CharacterClassRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if (is_string($this->input('code'))) $this->merge(['code' => strtolower(trim($this->input('code')))]);
        $this->merge(['can_dual_wield' => $this->boolean('can_dual_wield')]);
    }

    public function authorize(){return true;}

    public function rules()
    {
        $class = $this->route('character_class');
        return [
            'code' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('character_classes', 'code')->ignore($class ? $class->id : null)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(CatalogStatus::values())],
            'sort_order' => 'required|integer|min:0',
            'can_dual_wield' => 'required|boolean',
            'icon' => 'nullable|file|max:5120|mimetypes:image/png,image/jpeg,image/webp',
        ];
    }
}
