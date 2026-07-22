<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ShopPurchase extends Model{use HasFactory;protected $guarded=[];protected $casts=['quantity_purchased'=>'integer','gold_spent'=>'integer','purchased_at'=>'immutable_datetime','metadata'=>'array'];public function shop(){return$this->belongsTo(Shop::class);}public function offer(){return$this->belongsTo(ShopOffer::class,'shop_offer_id');}public function character(){return$this->belongsTo(Character::class);}public function item(){return$this->belongsTo(Item::class);}}
