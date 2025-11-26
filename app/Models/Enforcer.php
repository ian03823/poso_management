<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enforcer extends Authenticatable
{
    //
    use HasFactory, Notifiable, SoftDeletes;
    protected $table = "enforcers";
    protected $dates = ['deleted_at'];
    protected $fillable = [
        "badge_num",
        "fname",
        "mname",
        "lname",
        "phone",
        "password",
        "defaultPassword",
        'ticket_start',
        'ticket_end',
    ];
    protected $casts = [
        'failed_attempts' => 'integer',
        'lockouts_count'  => 'integer',
        'lockout_until'   => 'datetime',
    ];
    protected $hidden = [
        'password',
        'defaultPassword',
    ];
    public function ticketRange()
    {
        return $this->hasMany(TicketRange::class, 'badge_num', 'badge_num');
    }
    public function latestTicketRange()
    {
        // last assigned batch (for pages that only show “current”)
        return $this->hasOne(TicketRange::class, 'badge_num', 'badge_num')->latestOfMany();
    }
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
