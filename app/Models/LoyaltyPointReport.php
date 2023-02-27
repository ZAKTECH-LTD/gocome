<?php

namespace App\Models;


class LoyaltyPointReport extends BaseModel
{
    public function order()
    {
        return $this->belongsTo('App\Models\Order', 'order_id', 'id')->withTrashed();
    }
    
}
