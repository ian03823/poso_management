<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Ticket;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Violator extends Authenticatable
{
    //
    use Notifiable, HasFactory;
    protected $table = "violators";

    protected $fillable = [
        'name', 
        'address',
        'birthdate',
        'license_number', 
        'username', 
        'password',
    ];
    protected $hidden = [
        'password',
    ];
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
    public function tickets()  
    { 
        return $this->hasMany(Ticket::class);  
    }
}
