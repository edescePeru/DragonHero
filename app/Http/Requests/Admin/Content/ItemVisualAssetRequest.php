<?php
namespace App\Http\Requests\Admin\Content;use Illuminate\Foundation\Http\FormRequest;
class ItemVisualAssetRequest extends FormRequest{public function authorize(){return true;}public function rules(){return['body_type'=>'required|string|max:64','visual_slot'=>'required|string|max:32','equipment_layer'=>'required|file|max:5120|mimetypes:image/png,image/webp'];}}
