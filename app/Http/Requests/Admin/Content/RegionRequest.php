<?php

namespace App\Http\Requests\Admin\Content;

use App\Domain\WorldCatalog\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $region = $this->route('region');
        $worldId = $this->input('world_id');

        return [
            'world_id' => ['required', 'integer', 'exists:worlds,id'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('regions', 'code')->where(function ($query) use ($worldId) {
                    return $query->where('world_id', $worldId);
                })->ignore($region ? $region->id : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'recommended_level_min' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'recommended_level_max' => ['nullable', 'integer', 'gte:recommended_level_min', 'max:4294967295'],
            'status' => ['required', Rule::in(CatalogStatus::values())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $world = $this->route('world');
            $region = $this->route('region');

            if ($world && (int) $this->input('world_id') !== (int) $world->id) {
                $validator->errors()->add('world_id', 'El mundo no coincide con el contexto de la ruta.');
            }

            if ($region && (int) $this->input('world_id') !== (int) $region->world_id) {
                $validator->errors()->add('world_id', 'Una región existente no puede cambiar de mundo en esta fase.');
            }
        });
    }
}
