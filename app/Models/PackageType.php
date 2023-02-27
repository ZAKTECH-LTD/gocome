<?php

namespace App\Models;


class PackageType extends BaseModel
{

    public function package_type_pricings(){
        return $this->hasMany(PackageTypePricing::class);
    }
}
