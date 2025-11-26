<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketRange extends Model
{
    //
    protected $table = 'ticket_range'; 
    protected $fillable = [
        'badge_num',
        'ticket_start',
        'ticket_end',
    ];

    public function enforcer()
    {
        return $this->belongsTo(Enforcer::class, 'badge_num', 'badge_num');
    }
}
