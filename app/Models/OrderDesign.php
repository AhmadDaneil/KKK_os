<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDesign extends Model
{
    protected $fillable = ['order_package_side_id', 'theme', 'design_code', 'card_title', 'card_image_path'];
    public function packageSide() { return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id'); }
}
