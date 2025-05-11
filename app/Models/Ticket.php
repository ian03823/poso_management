<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Violator;
use App\Models\Enforcer;
use App\Models\Violation;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Ticket extends Model
{
    //

    protected $table = "tickets";
    protected $fillable = [
        'enforcer_id',
        'ticket_number',
        'violator_id',
        'vehicle_id',
        'violation_codes',
        'location',
        'issued_at',
        'offline',
        'status_id',
        'confiscation_type_id',
    ];

    protected $casts = [
        'violation_codes' => 'array',
        'offline' => 'boolean',
        'issued_at' => 'datetime',
        'status_id'            => 'integer',
        'confiscation_type_id' => 'integer',
    ];
    protected $with = ['status', 'confiscationType', 'violations', 'flags'];

    public function violator()
    {
        return $this->belongsTo(Violator::class );
    }
    public function flags()
    {
        return $this->belongsToMany(Flag::class, 'ticket_flags','ticket_id',
            'flag_id');
    }
    public function status()
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }
    public function confiscationType()
    {
        return $this->belongsTo(ConfiscationType::class, 'confiscation_type_id');
    }

    public function enforcer()
    {
        return $this->belongsTo(Enforcer::class);
    }
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
    public function getIsImpoundedAttribute()
    {
        return $this->flags->contains('key','is_impounded');
    }
    public function getIsResidentAttribute()
    {
        return $this->flags->contains('key','is_resident');
    }
    public function violations()
    {
        return $this->belongsToMany(Violation::class,
            'ticket_violation',
            'ticket_id',
            'violation_id'
        );
    }
    public function getViolationNamesAttribute(): string
    {
        // if your column is JSON: it's already an array
        // if it's comma-separated, you can json_decode or explode(',',$this->violation_codes)
        $raw = $this->getAttribute('violation_codes');

        if (! $raw || trim($raw) === '') {
            return '';
        }

        // 2) Decode if JSON, otherwise split CSV
        if (Str::startsWith(trim($raw), '[')) {
            $codes = json_decode($raw, true) ?: [];
        } else {
            $codes = array_filter(array_map('trim', explode(',', $raw)));
        }

        if (empty($codes)) {
            return '';
        }

        // 3) Lookup names in one query
        $names = Violation::whereIn('violation_code', $codes)
                          ->pluck('violation_name')
                          ->toArray();

        return implode(', ', $names);
    }

}
