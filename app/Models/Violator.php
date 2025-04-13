<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use app\Models\Ticket;
use app\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Violator extends Authenticatable
{
    //
    use Notifiable;
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

}
