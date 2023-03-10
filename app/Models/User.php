<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'password',
        'google_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function emails()
    {
        return $this->hasMany(Email::class);
    }
    public function movies()
    {
        return $this->hasMany(Movie::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
