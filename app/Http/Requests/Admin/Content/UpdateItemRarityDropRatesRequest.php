<?php
namespace App\Http\Requests\Admin\Content;
use App\Domain\Inventory\Instances\ItemRarityCode;use App\Domain\Probability\PercentagePpmConverter;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Validator;
final class UpdateItemRarityDropRatesRequest extends FormRequest
{
    protected function prepareForValidation(){$converter=app(PercentagePpmConverter::class);$data=[];foreach(ItemRarityCode::values() as $code){try{$data[$code.'_probability_ppm']=$converter->toPpm(trim((string)$this->input($code.'_probability_percent')));}catch(\InvalidArgumentException $e){$data[$code.'_probability_ppm']=-1;}}$this->merge($data);}
    public function authorize(){return true;}
    public function rules(){return['version'=>'required|integer|min:1','common_probability_percent'=>'required','rare_probability_percent'=>'required','mythic_probability_percent'=>'required','legendary_probability_percent'=>'required','common_probability_ppm'=>'required|integer|min:0|max:1000000','rare_probability_ppm'=>'required|integer|min:0|max:1000000','mythic_probability_ppm'=>'required|integer|min:0|max:1000000','legendary_probability_ppm'=>'required|integer|min:0|max:1000000'];}
    public function withValidator(Validator $validator){$validator->after(function($v){$sum=0;foreach(ItemRarityCode::values() as $code)$sum+=(int)$this->input($code.'_probability_ppm');if($sum!==1000000)$v->errors()->add('total','El total debe ser exactamente 100.0000 %.');});}
    public function probabilities():array{$p=[];foreach(ItemRarityCode::values() as $code)$p[$code]=(int)$this->input($code.'_probability_ppm');return$p;}
}
