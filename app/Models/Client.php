<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Client extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    //  CORRECTION: Spécifier la table clients explicitement
    protected $table = 'clients';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role',
        'titulaire',
        'nci',
        'telephone',
        'adresse',
        'code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'code_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Relation avec les comptes
     */
    public function comptes(): HasMany
    {
        return $this->hasMany(Compte::class);
    }

    /**
     * Scope pour les clients actifs
     */
    public function scopeActifs($query)
    {
        return $query->whereHas('comptes', function ($q) {
            $q->where('statut', 'actif');
        });
    }

    /**
     * Générer un code unique pour le client
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Vérifier si l'utilisateur est un client
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }
}