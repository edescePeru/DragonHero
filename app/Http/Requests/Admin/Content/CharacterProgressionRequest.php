<?php
namespace App\Http\Requests\Admin\Content;
use Illuminate\Foundation\Http\FormRequest;
final class CharacterProgressionRequest extends FormRequest{public function authorize(){return true;}public function rules(){return['max_character_level'=>['required','regex:/^(0|[1-9][0-9]*)$/'],'version'=>['required','regex:/^(0|[1-9][0-9]*)$/'],'reason'=>'required|string|max:1000','curve'=>'required|array|min:1','curve.*.level'=>['required','regex:/^(0|[1-9][0-9]*)$/'],'curve.*.required_experience'=>['required','regex:/^(0|[1-9][0-9]*)$/']];}}
