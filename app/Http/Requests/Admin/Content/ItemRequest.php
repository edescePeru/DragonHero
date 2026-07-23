<?php

namespace App\Http\Requests\Admin\Content;

use App\Domain\Equipment\EquipmentItemFamily;
use App\Domain\Equipment\EquipmentType;
use App\Domain\Equipment\EquippableItemValidator;
use App\Domain\Equipment\HandRequirement;
use App\Domain\Inventory\Instances\ItemRefinementConfiguration;
use App\Domain\Shops\NpcSaleItemValidator;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Domain\WorldCatalog\ItemRarity;
use App\Domain\WorldCatalog\ItemType;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ItemRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $item = $this->route('item');
        $weapon = $this->input('equipment_type') === EquipmentType::WEAPON && ($this->input('equipment_family') === null || EquipmentItemFamily::isWeapon($this->input('equipment_family')));
        $defensive = in_array($this->input('equipment_type'), [EquipmentType::HELMET,EquipmentType::ARMOR,EquipmentType::GLOVES,EquipmentType::BOOTS], true) || $this->input('equipment_family') === EquipmentItemFamily::SHIELD;
        $this->merge([
            'is_sellable' => $this->boolean('is_sellable'),
            'allows_refinement' => $this->has('allows_refinement') ? $this->boolean('allows_refinement') : ($weapon || $defensive),
            'refinement_stat' => $this->has('refinement_stat') ? $this->input('refinement_stat') : ($weapon ? 'attack' : ($defensive ? 'defense' : 'none')),
            'sell_price' => $this->has('sell_price') ? $this->input('sell_price') : 0,
            'rarity' => $item && $item->exists ? $item->rarity : ItemRarity::COMMON,
        ]);
        if (!$this->has('required_level')) $this->merge(['required_level' => 1]);
        if (!$this->has('character_class_ids')) $this->merge(['character_class_ids' => []]);
        if (!$this->has('allowed_rarity_ids')) {$common=\App\Models\ItemRarity::where('code','common')->value('id');$this->merge(['allowed_rarity_ids'=>$common?[(int)$common]:[]]);}
        if ($this->input('equipment_type') !== EquipmentType::WEAPON) $this->merge(['hand_requirement' => null, 'equipment_family' => null]);
        $this->merge(['absorb_damage_basis_points' => $this->basisPoints($this->input('absorb_damage_percent', '0'))]);
    }

    public function authorize() { return true; }

    public function rules()
    {
        $item = $this->route('item');
        $id = $item ? $item->id : null;
        $legacy = $item && $item->exists && $item->equipment_type === EquipmentType::WEAPON && $item->hand_requirement === null && $item->equipment_family === null;
        $handRule = $legacy ? 'nullable' : 'required_if:equipment_type,'.EquipmentType::WEAPON;
        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('items', 'code')->ignore($id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'item_type' => ['required', Rule::in(ItemType::values())],
            'equipment_type' => ['nullable', Rule::in(EquipmentType::all())],
            'hand_requirement' => ['nullable', $handRule, Rule::in(HandRequirement::all())],
            'equipment_family' => ['nullable', $handRule, Rule::in(EquipmentItemFamily::all())],
            'required_level' => 'required|integer|min:1|max:65535',
            'character_class_ids' => 'nullable|array',
            'character_class_ids.*' => 'integer|distinct|exists:character_classes,id',
            'allowed_rarity_ids' => 'required|array|min:1',
            'allowed_rarity_ids.*' => 'integer|distinct|exists:item_rarities,id',
            'rarity' => ['required', Rule::in(ItemRarity::values())],
            'allows_refinement' => 'required|boolean',
            'refinement_stat' => ['required', Rule::in(ItemRefinementConfiguration::values())],
            'is_stackable' => 'required|boolean',
            'max_stack' => 'required|integer|min:1',
            'is_sellable' => 'required|boolean',
            'sell_price' => 'required|integer|min:0',
            'status' => ['required', Rule::in(CatalogStatus::values())],
            'max_health_bonus' => 'required|integer|min:0',
            'attack_bonus' => 'required|integer|min:0',
            'defense_bonus' => 'required|integer|min:0',
            'accuracy_bonus' => 'required|integer|min:0',
            'evasion_bonus' => 'required|integer|min:0',
            'critical_chance_bonus' => 'required|numeric|min:0|max:999.99',
            'attack_speed_bonus' => 'required|numeric|min:0|max:999.99',
            'absorb_damage_percent' => [
                'nullable',
                'regex:/^(?:0|[1-9]|10)(?:\.[0-9]{1,2})?$/',
            ],
            'absorb_damage_basis_points' => 'required|integer|min:0|max:1000',
            'image' => 'nullable|file|max:5120|mimetypes:image/png,image/jpeg,image/webp',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            $candidate = new Item($this->only(['item_type','equipment_type','hand_requirement','equipment_family','is_stackable','max_stack','max_health_bonus','attack_bonus','defense_bonus','accuracy_bonus','evasion_bonus','critical_chance_bonus','attack_speed_bonus','absorb_damage_basis_points','is_sellable','sell_price','allows_refinement','refinement_stat']));
            try {
                app(EquippableItemValidator::class)->equipmentType($candidate);
                app(ItemRefinementConfiguration::class)->validate($candidate);
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('content', $e->getMessage());
                return;
            }
            if (!$candidate->is_sellable) return;
            try {
                app(NpcSaleItemValidator::class)->validate($candidate);
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('sell_price', $e->getMessage());
            }
        });
    }

    private function basisPoints($value)
    {
        $text = trim((string) $value);
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $text, $matches)) return -1;
        return (int) $matches[1] * 100 + (isset($matches[2]) ? (int) str_pad($matches[2], 2, '0') : 0);
    }

    public function messages()
    {
        return [
            'allowed_rarity_ids.min' => 'Selecciona al menos una rareza permitida.',
            'hand_requirement.required_if' => 'Debes indicar el requisito de manos para un nuevo objeto de mano.',
            'equipment_family.required_if' => 'Debes indicar la familia para un nuevo objeto de mano.',
            'absorb_damage_percent.regex' => 'AbsorbDamage debe ser un porcentaje entre 0 y 10 con hasta dos decimales.',
            'image.file' => 'La imagen seleccionada no es un archivo valido.',
            'image.max' => 'La imagen no puede superar 5 MB.',
            'image.mimetypes' => 'Solo se permiten imagenes PNG, JPG o WebP validas.',
        ];
    }
}
