<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RefineItemInstanceRequest extends FormRequest { public function authorize(){return true;} public function rules(){return ['refinement_token'=>['required','string','max:4096']];} }
