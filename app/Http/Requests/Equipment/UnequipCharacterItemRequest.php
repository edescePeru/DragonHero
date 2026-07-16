<?php
namespace App\Http\Requests\Equipment;use App\Domain\Equipment\CharacterEquipmentSlot;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class UnequipCharacterItemRequest extends FormRequest{public function authorize(){return$this->user()!==null;}public function rules(){return['slot'=>['required','string',Rule::in(CharacterEquipmentSlot::all())]];}}
