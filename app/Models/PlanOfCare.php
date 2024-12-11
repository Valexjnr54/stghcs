<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanOfCare extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'client_id',
        'plan_care'
    ];
}
