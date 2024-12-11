<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySheet extends Model
{
    use HasFactory;
    protected $fillable=
    [
        'support_worker_id',
        'gig_id',
        'client_id',
        'timesheet_id',
        'activity_id',
        'activity_sheet',
        'activity_time',
        'activity_day',
        'activity_date',
        'activity_week_number',
        'activity_year',
    ];
    
    public function gig()
    {
        return $this->belongsTo(Gig::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'support_worker_id', 'id');
    }
    
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }
    
    public function weekLog()
    {
        return $this->belongsTo(WeekLog::class, 'timesheet_id', 'timesheet_id');
    }
}