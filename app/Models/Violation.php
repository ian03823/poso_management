<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{

    use HasFactory;
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

}
