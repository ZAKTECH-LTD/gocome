<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fee extends NoDeleteBaseModel
{
    use HasFactory;
    
     protected $casts = [
        'id' => 'integer',
        'percentage' => 'int',
        'is_active' => 'int',
        'for_admin' => 'int',
    ];
    
}
