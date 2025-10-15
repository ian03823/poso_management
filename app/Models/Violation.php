<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Violation extends Model
{

    use SoftDeletes;
    protected $table = "violations";
    protected $primaryKey = 'violation_code';
    public $incrementing = false;
    protected $keyType = 'string';

    
    protected $fillable = [
        'violation_code', 
        'violation_name', 
        'fine_amount', 
        'penalty_points', 
        'description', 
        'category'
    ];
    protected $casts = [
        'fine_amount' => 'float',
        'deleted_at'  => 'datetime',
    ];
    public function tickets()
    {
        return $this->belongsTo(Ticket::class);
    }
}
