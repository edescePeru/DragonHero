<?php
namespace App\Http\Requests\Admin\Content;use Illuminate\Foundation\Http\FormRequest;
class StoreRefinementMaterialRequest extends FormRequest {public function authorize(){return true;}public function rules(){return['item_id'=>['required','integer','exists:items,id'],'quantity'=>['required','integer','min:1']];}}
