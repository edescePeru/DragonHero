<?php
namespace App\Http\Requests\Characters;
use App\Domain\Characters\Accounts\CharacterNameNormalizer;use App\Domain\Characters\Accounts\ReservedCharacterNames;use App\Domain\WorldCatalog\CatalogStatus;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class CreateCharacterRequest extends FormRequest
{
    protected function prepareForValidation(){if(is_string($this->input('name')))$this->merge(['name'=>app(CharacterNameNormalizer::class)->visible($this->input('name'))]);}
    public function authorize(){return$this->user()!==null;}
    public function rules(){return['template_id'=>['required','integer',Rule::exists('character_templates','id')->where(function($q){$q->where('status',CatalogStatus::ACTIVE);})],'name'=>['required','string','min:3','max:20','regex:/^(?![0-9 ]+$)[\pL\pN]+(?: [\pL\pN]+)*$/u',function($attribute,$value,$fail){if(app(ReservedCharacterNames::class)->contains($value))$fail('Ese nombre está reservado.');},Rule::unique('characters','normalized_name')->where(function($q){$q->where('normalized_name',app(CharacterNameNormalizer::class)->normalized($this->input('name')));})]];}
    public function messages(){return['name.regex'=>'El nombre solo puede contener letras Unicode, números y espacios simples, y no puede contener únicamente números.','name.min'=>'El nombre debe tener al menos 3 caracteres.','name.max'=>'El nombre no puede superar 20 caracteres.','name.unique'=>'Ese nombre ya está siendo utilizado por otro personaje.'];}
}
