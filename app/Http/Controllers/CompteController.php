<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Compte;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\StoreCompteRequest;
use App\Http\Requests\UpdateCompteRequest;
use App\Http\Requests\BloquerCompteRequest;
use App\Http\Requests\DebloquerCompteRequest;

class CompteController extends Controller
{
   public function index(Request $request): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user) {
            Log::error('No authenticated user in comptes index');
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Utilisateur non authentifié'
                ]
            ], 401);
        }

        Log::info('User accessing comptes index', [
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'user_email' => $user->email,
            'role' => $user->role ?? 'no_role',
        ]);

        $query = Compte::with('client:id,titulaire');

        // Vérifier si c'est un admin (User model) ou un client (Client model)
        $isAdmin = $user instanceof \App\Models\Admin && ($user->role === 'admin');
        $isClient = $user instanceof \App\Models\Client;

        if ($isClient && !$isAdmin) {
            // Client : voir uniquement ses comptes
            $query->where('client_id', $user->id);
            Log::info('Filtering comptes for client', ['client_id' => $user->id]);
        } elseif ($isAdmin) {
            // Admin : voir tous les comptes
            Log::info('Admin accessing all comptes', ['user_id' => $user->id]);
        } else {
            Log::error('Unknown user type', ['user_type' => get_class($user), 'role' => $user->role ?? 'none']);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCESS_DENIED',
                    'message' => 'Type d\'utilisateur non autorisé'
                ]
            ], 403);
        }

        if ($request->has('type')) $query->where('type', $request->type);
        if ($request->has('statut')) $query->where('statut', $request->statut);
        else $query->where('statut', 'actif');

        $query->orderBy('created_at', 'desc');

        $limit = min($request->input('limit', 10), 100);
        $comptes = $query->paginate($limit);

        return response()->json($this->formatPaginatedResponse($comptes));
    } catch (\Exception $e) {
        Log::error('Error in CompteController@index', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'Une erreur interne s\'est produite'
            ]
        ], 500);
    }
}

    public function show(Compte $compte): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in show method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Utilisateur non authentifié']
                ], 401);
            }

            if (($user->role ?? null) === 'client' && $compte->client_id !== $user->id) {
                Log::warning('Client accessing unauthorized compte', ['user_id'=>$user->id,'compte_id'=>$compte->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code'=>'ACCESS_DENIED','message'=>'Vous n\'avez pas accès à ce compte']
                ],403);
            }

            $cacheKey = "compte_{$compte->id}";
            $compteData = Cache::remember($cacheKey, 1800, fn() => $compte->load('client:id,titulaire'));

            return response()->json([
                'success' => true,
                'data' => $this->formatCompteData($compteData)
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CompteController@show', ['message'=>$e->getMessage()]);
            return response()->json([
                'success'=>false,
                'error'=>['code'=>'INTERNAL_ERROR','message'=>'Une erreur interne s\'est produite']
            ],500);
        }
    }

    public function store(StoreCompteRequest $request): JsonResponse
    {
        try {
            $client = $this->getOrCreateClient($request->client);

            $compte = new Compte();
            $compte->client_id = $client->id;
            $compte->type = $request->type;
            $compte->solde = $request->soldeInitial;
            $compte->devise = $request->devise;
            $compte->statut = 'actif';
            $compte->save();

            Cache::forget("compte_{$compte->id}");

            return response()->json([
                'success'=>true,
                'message'=>'Compte créé avec succès',
                'data'=>$this->formatCompteData($compte->load('client:id,titulaire'))
            ],201);
        } catch (\Exception $e) {
            Log::error('Error in CompteController@store',['message'=>$e->getMessage()]);
            return response()->json([
                'success'=>false,
                'error'=>['code'=>'CREATION_FAILED','message'=>'Erreur lors de la création du compte']
            ],500);
        }
    }

    private function getOrCreateClient(array $clientData): Client
    {
        if (!empty($clientData['id'])) return Client::findOrFail($clientData['id']);

        $client = new Client();
        $client->titulaire = $clientData['titulaire'] ?? 'Nom Inconnu';
        $client->email = $clientData['email'] ?? null;
        $client->telephone = $clientData['telephone'] ?? null;
        $client->save();

        return $client;
    }

    private function formatCompteData(Compte $compte): array
    {
        return [
            'id' => $compte->id,
            'numeroCompte' => $compte->numero_compte,
            'type' => $compte->type,
            'solde' => $compte->solde,
            'devise' => $compte->devise,
            'statut' => $compte->statut,
            'titulaire' => $compte->client->titulaire ?? null,
            'createdAt' => $compte->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function update(UpdateCompteRequest $request, Compte $compte): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in update method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Utilisateur non authentifié']
                ], 401);
            }

            // Vérifier que c'est un admin
            $isAdmin = $user instanceof \App\Models\Admin && ($user->role === 'admin');
            if (!$isAdmin) {
                Log::warning('Non-admin user attempting to update compte', ['user_id' => $user->id, 'compte_id' => $compte->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Seul un administrateur peut modifier un compte']
                ], 403);
            }

            Log::info('Admin updating compte', [
                'admin_id' => $user->id,
                'compte_id' => $compte->id,
                'data' => $request->all()
            ]);

            // Mettre à jour les informations du client associé
            $client = $compte->client;
            if ($request->has('titulaire')) {
                $client->titulaire = $request->titulaire;
            }

            $clientInfo = $request->input('informationsClient', []);
            if (isset($clientInfo['telephone'])) {
                $client->telephone = $clientInfo['telephone'];
            }
            if (isset($clientInfo['email'])) {
                $client->email = $clientInfo['email'];
            }
            if (isset($clientInfo['password'])) {
                $client->password = bcrypt($clientInfo['password']);
            }
            if (isset($clientInfo['nci'])) {
                $client->nci = $clientInfo['nci'];
            }

            $client->save();

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");

            return response()->json([
                'success' => true,
                'message' => 'Compte mis à jour avec succès',
                'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CompteController@update', [
                'message' => $e->getMessage(),
                'compte_id' => $compte->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UPDATE_FAILED', 'message' => 'Erreur lors de la mise à jour du compte']
            ], 500);
        }
    }

    public function destroy(Compte $compte): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in destroy method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Utilisateur non authentifié']
                ], 401);
            }

            // Vérifier que c'est un admin
            $isAdmin = $user instanceof \App\Models\Admin && ($user->role === 'admin');
            if (!$isAdmin) {
                Log::warning('Non-admin user attempting to delete compte', ['user_id' => $user->id, 'compte_id' => $compte->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Seul un administrateur peut supprimer un compte']
                ], 403);
            }

            Log::info('Admin deleting compte', [
                'admin_id' => $user->id,
                'compte_id' => $compte->id
            ]);

            // Fermer le compte (soft delete)
            if (!$compte->fermer()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'DELETE_FAILED', 'message' => 'Impossible de supprimer ce compte']
                ], 400);
            }

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CompteController@destroy', [
                'message' => $e->getMessage(),
                'compte_id' => $compte->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'DELETE_FAILED', 'message' => 'Erreur lors de la suppression du compte']
            ], 500);
        }
    }

    public function bloquer(BloquerCompteRequest $request, Compte $compte): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in bloquer method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Utilisateur non authentifié']
                ], 401);
            }

            // Vérifier que c'est un admin
            $isAdmin = $user instanceof \App\Models\Admin && ($user->role === 'admin');
            if (!$isAdmin) {
                Log::warning('Non-admin user attempting to block compte', ['user_id' => $user->id, 'compte_id' => $compte->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Seul un administrateur peut bloquer un compte']
                ], 403);
            }

            Log::info('Admin blocking compte', [
                'admin_id' => $user->id,
                'compte_id' => $compte->id,
                'motif' => $request->motif,
                'duree' => $request->duree,
                'unite' => $request->unite
            ]);

            // Bloquer le compte
            if (!$compte->bloquer($request->motif, $request->duree, $request->unite)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'BLOCK_FAILED', 'message' => 'Impossible de bloquer ce compte (doit être un compte épargne actif)']
                ], 400);
            }

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");

            return response()->json([
                'success' => true,
                'message' => 'Compte bloqué avec succès',
                'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CompteController@bloquer', [
                'message' => $e->getMessage(),
                'compte_id' => $compte->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'BLOCK_FAILED', 'message' => 'Erreur lors du blocage du compte']
            ], 500);
        }
    }

    public function debloquer(DebloquerCompteRequest $request, Compte $compte): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in debloquer method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Utilisateur non authentifié']
                ], 401);
            }

            // Vérifier que c'est un admin
            $isAdmin = $user instanceof \App\Models\Admin && ($user->role === 'admin');
            if (!$isAdmin) {
                Log::warning('Non-admin user attempting to unblock compte', ['user_id' => $user->id, 'compte_id' => $compte->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Seul un administrateur peut débloquer un compte']
                ], 403);
            }

            Log::info('Admin unblocking compte', [
                'admin_id' => $user->id,
                'compte_id' => $compte->id,
                'motif' => $request->motif
            ]);

            // Débloquer le compte
            if (!$compte->debloquer($request->motif)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNBLOCK_FAILED', 'message' => 'Impossible de débloquer ce compte (doit être bloqué)']
                ], 400);
            }

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");

            return response()->json([
                'success' => true,
                'message' => 'Compte débloqué avec succès',
                'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CompteController@debloquer', [
                'message' => $e->getMessage(),
                'compte_id' => $compte->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNBLOCK_FAILED', 'message' => 'Erreur lors du déblocage du compte']
            ], 500);
        }
    }

    private function formatPaginatedResponse($paginatedData): array
    {
        return [
            'success' => true,
            'data' => $paginatedData->items(),
            'pagination' => [
                'total' => $paginatedData->total(),
                'perPage' => $paginatedData->perPage(),
                'currentPage' => $paginatedData->currentPage(),
                'lastPage' => $paginatedData->lastPage()
            ]
        ];
    }
}
