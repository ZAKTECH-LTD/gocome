<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name',"vendor_type_id"];

    public function products()
    {
        return $this->belongsToMany('App\Models\Product');
    }

    public function vendor_type()
    {
        return $this->belongsTo('App\Models\VendorType');
    }
}
