<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Compte extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'numero_compte',
        'client_id',
        'user_id',
        'type',
        'solde',
        'devise',
        'statut',
        'motif_blocage',
        'date_debut_blocage',
        'date_fin_blocage',
        'derniere_modification',
        'version',
    ];

    protected $casts = [
        'solde' => 'decimal:2',
        'date_debut_blocage' => 'datetime',
        'date_fin_blocage' => 'datetime',
        'derniere_modification' => 'datetime',
        'metadata' => 'array',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Générer automatiquement le numéro de compte lors de la création
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($compte) {
            if (empty($compte->id)) {
                $compte->id = (string) Str::uuid();
            }
            if (empty($compte->numero_compte)) {
                $compte->numero_compte = static::generateNumeroCompte();
            }
            if (empty($compte->statut)) {
                $compte->statut = 'actif';
            }
            $compte->version = 1;
        });

        static::updating(function ($compte) {
            $compte->version = $compte->version + 1;
            $compte->derniere_modification = now();
        });
    }

    /**
     * Générer un numéro de compte unique
     */
    public static function generateNumeroCompte(): string
    {
        do {
            $numero = 'C' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        } while (static::where('numero_compte', $numero)->exists());

        return $numero;
    }

    /**
     * Mutator pour numéro de compte
     */
    public function setNumeroCompteAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['numero_compte'] = static::generateNumeroCompte();
        } else {
            $this->attributes['numero_compte'] = $value;
        }
    }

    /**
     * Relation avec l'administrateur
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    /**
     * Relation avec Client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeParStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope pour comptes actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Scope pour comptes bloqués
     */
    public function scopeBloques($query)
    {
        return $query->where('statut', 'bloque');
    }

    /**
     * Scope pour comptes épargne
     */
    public function scopeEpargne($query)
    {
        return $query->where('type', 'epargne');
    }

    /**
     * Scope pour comptes chèque
     */
    public function scopeCheque($query)
    {
        return $query->where('type', 'cheque');
    }

    /**
     * Scope pour recherche par titulaire ou numéro
     */
    public function scopeRecherche($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('numero_compte', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('titulaire', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Scope pour tri
     */
    public function scopeTrierPar($query, $sort, $order = 'asc')
    {
        $allowedSorts = ['dateCreation', 'solde', 'titulaire'];
        $sortField = match($sort) {
            'dateCreation' => 'created_at',
            'solde' => 'solde',
            'titulaire' => 'client.titulaire',
            default => 'created_at'
        };

        if ($sortField === 'client.titulaire') {
            return $query->join('clients', 'comptes.client_id', '=', 'clients.id')
                        ->orderBy('clients.titulaire', $order)
                        ->select('comptes.*');
        }

        return $query->orderBy($sortField, $order);
    }

    /**
     * Vérifier si le compte peut être bloqué
     */
    public function peutEtreBloque(): bool
    {
        return $this->type === 'epargne' && $this->statut === 'actif';
    }

    /**
     * Vérifier si le compte peut être débloqué
     */
    public function peutEtreDebloque(): bool
    {
        return $this->statut === 'bloque';
    }

    /**
     * Bloquer le compte
     */
    public function bloquer(string $motif, int $duree, string $unite): bool
    {
        if (!$this->peutEtreBloque()) {
            return false;
        }

        $dateDebut = now();
        $dateFin = match($unite) {
            'jours' => $dateDebut->copy()->addDays($duree),
            'mois' => $dateDebut->copy()->addMonths($duree),
            default => $dateDebut->copy()->addMonths($duree)
        };

        $this->update([
            'statut' => 'bloque',
            'motif_blocage' => $motif,
            'date_debut_blocage' => $dateDebut,
            'date_fin_blocage' => $dateFin,
        ]);

        return true;
    }

    /**
     * Débloquer le compte
     */
    public function debloquer(string $motif): bool
    {
        if (!$this->peutEtreDebloque()) {
            return false;
        }

        $this->update([
            'statut' => 'actif',
            'motif_blocage' => null,
            'date_debut_blocage' => null,
            'date_fin_blocage' => null,
        ]);

        return true;
    }

    /**
     * Fermer le compte (soft delete)
     */
    public function fermer(): bool
    {
        if ($this->statut === 'ferme') {
            return false;
        }

        $this->update(['statut' => 'ferme']);
        $this->delete(); // Soft delete

        return true;
    }
}
