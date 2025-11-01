<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Compte;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Requests\StoreCompteRequest;
use App\Http\Requests\UpdateCompteRequest;
use App\Http\Requests\BloquerCompteRequest;
use App\Http\Requests\DebloquerCompteRequest;

/**
 * @OA\Schema(
 *     schema="Compte",
 *     type="object",
 *     @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440001"),
 *     @OA\Property(property="numeroCompte", type="string", example="C00123456"),
 *     @OA\Property(property="type", type="string", enum={"epargne", "cheque"}, example="epargne"),
 *     @OA\Property(property="solde", type="number", format="float", example=100000.00),
 *     @OA\Property(property="devise", type="string", example="FCFA"),
 *     @OA\Property(property="statut", type="string", enum={"actif", "bloque", "ferme"}, example="actif"),
 *     @OA\Property(property="titulaire", type="string", example="Fatou Sow"),
 *     @OA\Property(property="createdAt", type="string", format="date-time", example="2025-10-29T04:00:00.000000Z")
 * )
 * @OA\Schema(
 *     schema="ClientInput",
 *     type="object",
 *     @OA\Property(property="id", type="string", description="ID du client existant (optionnel)", example="550e8400-e29b-41d4-a716-446655440001"),
 *     @OA\Property(property="titulaire", type="string", description="Nom du titulaire (requis si nouveau client)", example="Fatou Sow"),
 *     @OA\Property(property="email", type="string", format="email", description="Email (requis si nouveau client)", example="fatou.sow@example.com"),
 *     @OA\Property(property="telephone", type="string", description="Téléphone (requis si nouveau client)", example="+221782345678")
 * )
 * @OA\Schema(
 *     schema="BlocageRequest",
 *     type="object",
 *     required={"motif", "duree", "unite"},
 *     @OA\Property(property="motif", type="string", example="Suspicion de fraude"),
 *     @OA\Property(property="duree", type="integer", example=30),
 *     @OA\Property(property="unite", type="string", enum={"jours", "mois"}, example="jours")
 * )
 */

class CompteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/comptes",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="📋 Liste des comptes",
     *     description="Récupère la liste des comptes selon le rôle de l'utilisateur. Admin voit tous les comptes actifs, client voit uniquement ses comptes actifs.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page pour la pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=100, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type de compte",
     *         required=false,
     *         @OA\Schema(type="string", enum={"epargne", "cheque"}, example="epargne")
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"actif", "bloque", "ferme"}, example="actif")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Rechercher par titulaire ou numéro de compte",
     *         required=false,
     *         @OA\Schema(type="string", example="Fatou")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Champ de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"dateCreation", "solde", "titulaire"}, example="solde")
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Ordre de tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Response(response=200, description="✅ Liste des comptes récupérée"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé")
     * )
     */
    public function index(Request $request): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user) {
            Log::error('No authenticated user in comptes index');
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Authentification requise. Veuillez vous connecter pour accéder à la liste des comptes.'
                ]
            ], 401);
        }

        Log::info('User accessing comptes index', [
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'user_email' => $user->email,
            'role' => $user->role ?? 'no_role',
        ]);

        $query = Compte::with('client:id,titulaire,telephone');

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
                    'code' => 'INVALID_USER_TYPE',
                    'message' => 'Type d\'utilisateur non reconnu. Seuls les administrateurs et clients peuvent accéder aux comptes.'
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

    /**
     * @OA\Get(
     *     path="/api/v1/comptes/telephone/{telephone}",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="👁️ Détail d'un compte par téléphone client",
     *     description="Récupère les informations détaillées d'un compte spécifique à partir du numéro de téléphone du client. Les clients ne peuvent voir que leurs propres comptes.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="telephone",
     *         in="path",
     *         required=true,
     *         description="Numéro de téléphone du client",
     *         @OA\Schema(type="string", example="+221782345678")
     *     ),
     *     @OA\Response(response=200, description="✅ Détail du compte récupéré"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé"),
     *     @OA\Response(response=404, description="🔍 Compte non trouvé")
     * )
     */
    public function showByTelephone(string $telephone): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in showByTelephone method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Utilisateur non authentifié']
                ], 401);
            }

            // Rechercher le client par numéro de téléphone
            $client = \App\Models\Client::where('telephone', $telephone)->first();

            if (!$client) {
                Log::warning('Client not found by telephone', ['telephone' => $telephone, 'user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Client non trouvé avec ce numéro de téléphone']
                ], 404);
            }

            // Vérifier les permissions : clients ne peuvent voir que leurs comptes
            if (($user->role ?? null) === 'client' && $client->id !== $user->id) {
                Log::warning('Client accessing unauthorized compte by telephone', ['user_id' => $user->id, 'client_id' => $client->id, 'telephone' => $telephone]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Vous n\'avez pas accès aux comptes de ce client']
                ], 403);
            }

            // Récupérer les comptes du client
            $comptes = Compte::where('client_id', $client->id)
                            ->with('client:id,titulaire,telephone')
                            ->get();

            if ($comptes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Aucun compte trouvé pour ce numéro de téléphone']
                ], 404);
            }

            // Formater les données des comptes
            $formattedComptes = $comptes->map(function ($compte) {
                return $this->formatCompteData($compte);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedComptes,
                'total' => $comptes->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in CompteController@showByTelephone', ['telephone' => $telephone, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Une erreur interne s\'est produite']
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/comptes/numero/{numero}",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="👁️ Détail d'un compte par numéro",
     *     description="Récupère les informations détaillées d'un compte spécifique à partir de son numéro. Les clients ne peuvent voir que leurs propres comptes.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="numero",
     *         in="path",
     *         required=true,
     *         description="Numéro du compte",
     *         @OA\Schema(type="string", example="C00123456")
     *     ),
     *     @OA\Response(response=200, description="✅ Détail du compte récupéré"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé"),
     *     @OA\Response(response=404, description="🔍 Compte non trouvé")
     * )
     */
    public function showByNumero(string $numero): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in showByNumero method');
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'AUTHENTICATION_REQUIRED', 'message' => 'Authentification requise pour consulter les détails d\'un compte.']
                ], 401);
            }

            // Rechercher le compte par numéro
            $compte = Compte::where('numero_compte', $numero)->first();

            if (!$compte) {
                Log::warning('Compte not found by numero', ['numero' => $numero, 'user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'NOT_FOUND', 'message' => 'Compte non trouvé']
                ], 404);
            }

            // Vérifier les permissions : clients ne peuvent voir que leurs comptes
            if (($user->role ?? null) === 'client' && $compte->client_id !== $user->id) {
                Log::warning('Client accessing unauthorized compte by numero', ['user_id' => $user->id, 'compte_id' => $compte->id, 'numero' => $numero]);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Accès refusé. Vous ne pouvez consulter que vos propres comptes.']
                ], 403);
            }

            $cacheKey = "compte_numero_{$numero}";
            $compteData = Cache::remember($cacheKey, 1800, fn() => $compte->load('client:id,titulaire,telephone'));

            return response()->json([
                'success' => true,
                'data' => $this->formatCompteData($compteData)
            ]);
        } catch (\Exception $e) {
            Log::error('Error in CompteController@showByNumero', ['numero' => $numero, 'message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Une erreur interne s\'est produite']
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/comptes/{compte}",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="👁️ Détail d'un compte",
     *     description="Récupère les informations détaillées d'un compte spécifique. Les clients ne peuvent voir que leurs propres comptes.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compte",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\Response(response=200, description="✅ Détail du compte récupéré"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé"),
     *     @OA\Response(response=404, description="🔍 Compte non trouvé")
     * )
     */
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
            $compteData = Cache::remember($cacheKey, 1800, fn() => $compte->load('client:id,titulaire,telephone'));

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

    /**
     * @OA\Post(
     *     path="/api/v1/comptes",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="➕ Créer un compte",
     *     description="Crée un nouveau compte bancaire avec un client existant ou nouveau (Admin seulement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"type", "soldeInitial", "devise", "client"},
     *             @OA\Property(property="type", type="string", enum={"epargne", "cheque"}, example="epargne", description="Type de compte"),
     *             @OA\Property(property="soldeInitial", type="number", format="float", example=50000, description="Solde initial du compte"),
     *             @OA\Property(property="devise", type="string", example="FCFA", description="Devise du compte"),
     *             @OA\Property(property="client", ref="#/components/schemas/ClientInput", description="Informations du client")
     *         )
     *     ),
     *     @OA\Response(response=201, description="✅ Compte créé avec succès"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé - Admin seulement"),
     *     @OA\Response(response=422, description="❌ Données invalides")
     * )
     */
    public function store(StoreCompteRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                Log::error('No authenticated user in store method');
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTHENTICATION_REQUIRED',
                        'message' => 'Authentification requise pour créer un compte bancaire.'
                    ]
                ], 401);
            }

            // Vérifier que c'est un admin
            $isAdmin = ($user->role === 'admin');
            if (!$isAdmin) {
                Log::warning('Non-admin user attempting to create compte', ['user_id' => $user->id, 'user_type' => get_class($user), 'user_role' => $user->role]);
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ADMIN_ONLY_OPERATION',
                        'message' => 'Opération réservée aux administrateurs. Seuls les administrateurs peuvent créer des comptes bancaires.'
                    ]
                ], 403);
            }

            Log::info('Admin creating compte', [
                'admin_id' => $user->id,
                'data' => $request->all()
            ]);

            // Créer ou récupérer le client
            $client = $this->getOrCreateClient($request->client);

            // Créer le compte pour ce client
            $compte = new Compte();
            $compte->client_id = $client->id;
            $compte->type = $request->type;
            $compte->solde = $request->soldeInitial;
            $compte->devise = $request->devise;
            $compte->statut = 'actif';
            $compte->save();

            Cache::forget("compte_{$compte->id}");

            $formattedData = $this->formatCompteData($compte->load('client:id,titulaire,telephone'));

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès',
                'data' => $formattedData
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in CompteController@store', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CREATION_FAILED', 'message' => 'Erreur lors de la création du compte']
            ], 500);
        }
    }

    private function getOrCreateClient(array $clientData): Client
    {
        if (!empty($clientData['id'])) return Client::findOrFail($clientData['id']);

        $client = new Client([
            'id' => (string) Str::uuid(), // Générer un UUID pour l'id
            'titulaire' => $clientData['titulaire'] ?? 'Nom Inconnu',
            'email' => $clientData['email'] ?? null,
            'telephone' => $clientData['telephone'] ?? null,
            'role' => 'client', // Définir le rôle par défaut
            'password' => bcrypt('password123'), // Mot de passe par défaut
            'code' => rand(100000, 999999), // Générer un code aléatoire
        ]);
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
            'telephone' => $compte->client->telephone ?? null,
            'createdAt' => $compte->created_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/comptes/{compte}",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="✏️ Mettre à jour un compte",
     *     description="Modifie les informations d'un compte et/ou de son titulaire (Admin seulement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compte",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="type", type="string", enum={"epargne", "cheque"}, example="epargne"),
     *             @OA\Property(property="solde", type="number", format="float", example=75000),
     *             @OA\Property(property="devise", type="string", example="FCFA"),
     *             @OA\Property(property="statut", type="string", enum={"actif", "bloque", "ferme"}, example="actif"),
     *             @OA\Property(property="titulaire", type="string", example="Nouveau Nom du Titulaire"),
     *             @OA\Property(
     *                 property="informationsClient",
     *                 type="object",
     *                 @OA\Property(property="telephone", type="string", example="+221778765432"),
     *                 @OA\Property(property="email", type="string", format="email", example="nouveau@email.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="✅ Compte mis à jour"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé - Admin seulement"),
     *     @OA\Response(response=404, description="🔍 Compte non trouvé")
     * )
     */
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

            // Mettre à jour les informations du compte
            if ($request->has('type')) {
                $compte->type = $request->type;
            }
            if ($request->has('solde')) {
                $compte->solde = $request->solde;
            }
            if ($request->has('devise')) {
                $compte->devise = $request->devise;
            }
            if ($request->has('statut')) {
                $compte->statut = $request->statut;
            }

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
            $compte->save();

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");

            return response()->json([
                'success' => true,
                'message' => 'Compte mis à jour avec succès',
                'data' => $this->formatCompteData($compte->load('client:id,titulaire,telephone'))
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

    /**
     * @OA\Delete(
     *     path="/api/v1/comptes/{compte}",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="🗑️ Supprimer un compte",
     *     description="Supprime un compte (soft delete - Admin seulement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compte",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\Response(response=200, description="✅ Compte supprimé"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé - Admin seulement"),
     *     @OA\Response(response=404, description="🔍 Compte non trouvé")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/comptes/{compte}/bloquer",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="🚫 Bloquer un compte",
     *     description="Bloque un compte épargne pour une durée déterminée (Admin seulement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compte",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/BlocageRequest")
     *     ),
     *     @OA\Response(response=200, description="✅ Compte bloqué"),
     *     @OA\Response(response=400, description="❌ Impossible de bloquer"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé - Admin seulement")
     * )
     */
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
                'data' => $this->formatCompteData($compte->load('client:id,titulaire,telephone'))
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

    /**
     * @OA\Post(
     *     path="/api/v1/comptes/{compte}/debloquer",
     *     tags={"💳 Gestion des Comptes"},
     *     summary="✅ Débloquer un compte",
     *     description="Remet un compte bloqué en statut actif (Admin seulement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compte",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"motif"},
     *             @OA\Property(property="motif", type="string", example="Blocage levé après vérification")
     *         )
     *     ),
     *     @OA\Response(response=200, description="✅ Compte débloqué"),
     *     @OA\Response(response=400, description="❌ Impossible de débloquer"),
     *     @OA\Response(response=401, description="❌ Non authentifié"),
     *     @OA\Response(response=403, description="🚫 Accès refusé - Admin seulement")
     * )
     */
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
                'data' => $this->formatCompteData($compte->load('client:id,titulaire,telephone'))
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
