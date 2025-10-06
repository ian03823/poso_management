<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;

<<<<<<< HEAD
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;
=======
>>>>>>> fix-detached
class Admin extends Authenticatable implements CanResetPasswordContract
{
    //
    use HasFactory, Notifiable, CanResetPassword;
    protected $table = 'admins';
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
