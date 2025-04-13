<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Enforcer extends Authenticatable
{
    //
    use HasFactory, Notifiable;
    protected $table = "enforcers";
    protected $fillable = [
        "badge_num",
        "fname",
        "mname",
        "lname",
        "phone",
        "password",
    ];
    protected $hidden = [
        'password',
    ];
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
