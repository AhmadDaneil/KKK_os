<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderParent extends Model
{
    protected $fillable = ['order_package_side_id', 'father_name', 'mother_name'];
    public function packageSide() { return $this->belongsTo(OrderPackageSide::class, 'order_package_side_id'); }
}
