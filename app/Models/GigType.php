<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GigType extends Model
{
    use SoftDeletes,HasFactory;
    protected $fillable = [
        'title',
        'shortcode',
        'waiver_activities'
    ];
}
