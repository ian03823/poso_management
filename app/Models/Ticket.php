<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use app\Models\Violator;
use app\Models\Enforcer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Ticket extends Model
{
    //

    protected $table = "tickets";
    protected $fillable = [
        'enforcer_id',
        'violator_id',
        'vehicle_id',
        'violation_codes',
        'location',
        'issued_at',
        'status',
        'offline',
        'confiscated',
        'is_impounded',
    ];

    protected $casts = [
        'violation_codes' => 'array',
        'offline' => 'boolean',
        'issued_at' => 'datetime',
    ];

    public function violator()
    {
        return $this->belongsTo(Violator::class);
    }

    public function enforcer()
    {
        return $this->belongsTo(Enforcer::class);
    }
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
