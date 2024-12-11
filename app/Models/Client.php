<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Client extends Model
{
    use SoftDeletes, HasFactory;
    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'other_name',
        'phone_number',
        'email',
        'dob',
        'location_id',
        'created_by',
        'supervisor_id',
        'plan_of_care',
        'city',
        'zip_code',
        'address1',
        'address2',
        'coordinate',
        'status',
        'poc_activities'
    ];
    
    protected static function boot()
    {
        parent::boot();

        // Apply global scope to exclude inactive clients
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('status', '!=', 'inactive');
        });
    }

    public function location() {
        return $this->belongsTo(Location::class);
    }

    public function gigs() {
        return $this->hasMany(Gig::class);
    }
    public function created_by() {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function supervisor() {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
    
    
    /*Override Scope (Optional): If you ever need to include inactive clients in a specific query, you can use withoutGlobalScope:

    $clients = Client::withoutGlobalScope('active')->get();*/
}
