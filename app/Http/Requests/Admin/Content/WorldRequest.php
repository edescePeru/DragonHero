<?php

namespace App\Http\Requests\Admin\Content;

use App\Domain\WorldCatalog\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WorldRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $world = $this->route('world');

        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('worlds', 'code')->ignore($world ? $world->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(CatalogStatus::values())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'image' => ['nullable', 'file', 'max:5120', 'mimetypes:image/png,image/jpeg,image/webp'],
        ];
    }
}
