<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use app\Models\Violator;

class Vehicle extends Model
{
    //
    protected $primaryKey = 'vehicle_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'violator_id',
        'plate_number',
        'owner_name',
        'vehicle_type',
        'is_owner',
    ];
    public function violator(){
        return $this->belongsTo(Violator::class);
    }
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
