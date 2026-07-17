<?php
namespace App\Http\Requests\Admin\Content;use Illuminate\Foundation\Http\FormRequest;
class StoreRefinementLevelRequest extends FormRequest {public function authorize(){return true;}public function rules(){return['from_level'=>['required','integer','min:0','max:14'],'to_level'=>['required','integer','min:1','max:15'],'success_chance_basis_points'=>['required','integer','in:10000'],'gold_cost'=>['required','integer','min:0'],'failure_behavior'=>['required','in:keep_level'],'status'=>['required','in:active,inactive,hidden']];}}
