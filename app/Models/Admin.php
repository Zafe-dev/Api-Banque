<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = $model->id ?? (string) Str::uuid();
        });
    }

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Vérifier si l'utilisateur est un admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
