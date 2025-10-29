<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Compte;
use App\Http\Requests\StoreCompteRequest;
use App\Http\Requests\IndexComptesRequest;
use App\Http\Requests\UpdateCompteRequest;
use App\Http\Requests\BloquerCompteRequest;
use App\Http\Requests\DebloquerCompteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Info(
 *     title="API Banque - Documentation",
 *     version="1.0.0",
 *     description="API RESTful pour la gestion des comptes bancaires"
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Serveur local"
 * ),
 * @OA\Server(
 *     url="http://api.moustapha.seck.com",
 *     description="Serveur de production"
 * ),
 * @OA\Server(
 *     url="https://moustapha-seck.onrender.com",
 *     description="Serveur de production"
 * )
 *
 * @OA\Schema(
 *     schema="Compte",
 *     type="object",
 *     @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="numeroCompte", type="string", example="C00123456"),
 *     @OA\Property(property="titulaire", type="string", example="Amadou Diallo"),
 *     @OA\Property(property="type", type="string", enum={"epargne", "cheque"}),
 *     @OA\Property(property="solde", type="number", example=1250000),
 *     @OA\Property(property="devise", type="string", example="FCFA"),
 *     @OA\Property(property="dateCreation", type="string", format="date-time"),
 *     @OA\Property(property="statut", type="string", enum={"actif", "bloque", "ferme"}),
 *     @OA\Property(property="motifBlocage", type="string", nullable=true),
 *     @OA\Property(
 *         property="metadata",
 *         type="object",
 *         @OA\Property(property="derniereModification", type="string", format="date-time"),
 *         @OA\Property(property="version", type="integer", example=1)
 *     )
 * )
 */

class CompteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/comptes",
     *     tags={"Comptes"},
     *     summary="Lister tous les comptes",
     *     description="Admin peut récupérer la liste de tous les comptes, Client peut récupérer la liste de ses comptes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"epargne", "cheque"})
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"actif", "bloque", "ferme"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par titulaire ou numéro",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des comptes récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Compte")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="currentPage", type="integer"),
     *                 @OA\Property(property="totalPages", type="integer"),
     *                 @OA\Property(property="totalItems", type="integer"),
     *                 @OA\Property(property="itemsPerPage", type="integer"),
     *                 @OA\Property(property="hasNext", type="boolean"),
     *                 @OA\Property(property="hasPrevious", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé"
     *     )
     * )
     */
    public function index(IndexComptesRequest $request): JsonResponse
    {
        try {
            // Test de connexion DB simple
            $dbTest = DB::select('SELECT 1 as test');
            if (!$dbTest) {
                throw new \Exception('Connexion base de données échouée');
            }

            // Test simple : compter les comptes
            $totalComptes = Compte::count();

            // Requête simplifiée pour éviter tous les problèmes
            $query = Compte::select([
                'id',
                'numero_compte',
                'client_id',
                'type',
                'solde',
                'devise',
                'statut',
                'created_at'
            ]);

            // Filtres selon le rôle de l'utilisateur
            $user = auth()->user();
            if ($user instanceof Client) {
                $query->where('client_id', $user->id);
            }

            // IMPORTANT: Ne pas montrer les comptes bloqués ou supprimés
            $query->whereNotIn('statut', ['bloque', 'ferme']);

            // Tri simple
            $query->orderBy('created_at', 'desc');

            // Pagination simple
            $comptes = $query->paginate(10);

            // Formatage simplifié SANS jointure pour éviter les problèmes
            $data = $comptes->getCollection()->map(function ($compte) {
                // Récupération du client séparément pour éviter les erreurs de jointure
                $client = null;
                try {
                    $client = \App\Models\Client::where('id', $compte->client_id)->first();
                } catch (\Exception $e) {
                    Log::warning('Erreur récupération client', [
                        'compte_id' => $compte->id,
                        'client_id' => $compte->client_id,
                        'error' => $e->getMessage()
                    ]);
                }

                return [
                    'id' => $compte->id,
                    'numeroCompte' => $compte->numero_compte,
                    'titulaire' => $client ? $client->titulaire : 'Client inconnu',
                    'type' => $compte->type,
                    'solde' => $compte->solde,
                    'devise' => $compte->devise,
                    'dateCreation' => $compte->created_at,
                    'statut' => $compte->statut
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'currentPage' => $comptes->currentPage(),
                    'totalPages' => $comptes->lastPage(),
                    'totalItems' => $comptes->total(),
                    'itemsPerPage' => $comptes->perPage()
                ],
                'debug' => [
                    'db_connection' => 'OK',
                    'total_comptes_in_db' => $totalComptes,
                    'comptes_returned' => count($data)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur dans CompteController@index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
                'db_config' => [
                    'host' => config('database.connections.pgsql.host'),
                    'database' => config('database.connections.pgsql.database'),
                    'username' => config('database.connections.pgsql.username')
                ]
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Erreur: ' . $e->getMessage(),
                    'file' => basename($e->getFile()) . ':' . $e->getLine(),
                    'db_test' => DB::select('SELECT 1 as test') ? 'OK' : 'FAILED'
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/comptes/{compteId}",
     *     tags={"Comptes"},
     *     summary="Récupérer un compte spécifique",
     *     description="Admin peut récupérer un compte par ID, Client peut récupérer un de ses comptes par ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du compte récupérés avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Compte")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Compte non trouvé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="COMPTE_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Le compte avec l'ID spécifié n'existe pas")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé"
     *     )
     * )
     */
    public function show(Compte $compte): JsonResponse
    {
        // Vérifier les permissions
        $user = auth()->user();

        if ($user instanceof Client && $compte->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCESS_DENIED',
                    'message' => 'Vous n\'avez pas accès à ce compte'
                ]
            ], 403);
        }

        // Cache individuel du compte
        $cacheKey = "compte_{$compte->id}";
        $compteData = Cache::remember($cacheKey, 1800, function () use ($compte) {
            return $compte->load('client:id,titulaire');
        });

        return response()->json([
            'success' => true,
            'data' => $this->formatCompteData($compteData)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/comptes",
     *     tags={"Comptes"},
     *     summary="Créer un nouveau compte",
     *     description="Créer un nouveau compte bancaire avec génération automatique de numéro et client",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"type", "soldeInitial", "devise", "client"},
     *             @OA\Property(property="type", type="string", enum={"cheque", "epargne"}),
     *             @OA\Property(property="soldeInitial", type="number", minimum=10000),
     *             @OA\Property(property="devise", type="string", example="FCFA"),
     *             @OA\Property(
     *                 property="client",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", nullable=true),
     *                 @OA\Property(property="titulaire", type="string"),
     *                 @OA\Property(property="nci", type="string", nullable=true),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="telephone", type="string"),
     *                 @OA\Property(property="adresse", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compte créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte créé avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Compte")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="Les données fournies sont invalides")
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreCompteRequest $request): JsonResponse
    {
        try {
            // Vérifier/créer le client
            $client = $this->getOrCreateClient($request->client);

            // Créer le compte
            $compte = new Compte();
            $compte->client_id = $client->id;
            $compte->type = $request->type;
            $compte->solde = $request->soldeInitial;
            $compte->devise = $request->devise;
            $compte->save();

            // Invalider le cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès',
                'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATION_FAILED',
                    'message' => 'Erreur lors de la création du compte',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/comptes/{compteId}",
     *     tags={"Comptes"},
     *     summary="Mettre à jour les informations du client",
     *     description="Mettre à jour les informations du client associé au compte",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="titulaire", type="string", nullable=true),
     *             @OA\Property(
     *                 property="informationsClient",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="telephone", type="string", nullable=true),
     *                 @OA\Property(property="email", type="string", format="email", nullable=true),
     *                 @OA\Property(property="password", type="string", nullable=true),
     *                 @OA\Property(property="nci", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Informations mises à jour avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte mis à jour avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Compte")
     *         )
     *     )
     * )
     */
    public function update(UpdateCompteRequest $request, Compte $compte): JsonResponse
    {

        try {
            // Mise à jour du client si nécessaire
            if ($request->has('titulaire') || $request->has('informationsClient')) {
                $clientData = [];

                if ($request->has('titulaire')) {
                    $clientData['titulaire'] = $request->titulaire;
                }

                if ($request->has('informationsClient')) {
                    $clientData = array_merge($clientData, $request->informationsClient);
                }

                $compte->client->update($clientData);
            }

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Compte mis à jour avec succès',
                'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Erreur lors de la mise à jour du compte'
                ]
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/comptes/{compteId}",
     *     tags={"Comptes"},
     *     summary="Supprimer un compte",
     *     description="Supprimer un compte (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte supprimé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte supprimé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="numeroCompte", type="string"),
     *                 @OA\Property(property="statut", type="string", example="ferme"),
     *                 @OA\Property(property="dateFermeture", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(Compte $compte): JsonResponse
    {
        try {
            $compte->delete(); // Soft delete

            // Invalider le cache
            Cache::forget("compte_{$compte->id}");
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès',
                'data' => [
                    'id' => $compte->id,
                    'numeroCompte' => $compte->numero_compte,
                    'statut' => 'ferme',
                    'dateFermeture' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => 'Erreur lors de la suppression du compte'
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/comptes/{compteId}/bloquer",
     *     tags={"Comptes"},
     *     summary="Bloquer un compte",
     *     description="Bloquer un compte épargne actif",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"motif", "duree", "unite"},
     *             @OA\Property(property="motif", type="string", maxLength=500),
     *             @OA\Property(property="duree", type="integer", minimum=1, maximum=365),
     *             @OA\Property(property="unite", type="string", enum={"jours", "mois"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte bloqué avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte bloqué avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Compte")
     *         )
     *     )
     * )
     */
    public function bloquer(BloquerCompteRequest $request, Compte $compte): JsonResponse
    {
        // Vérifier que c'est un compte épargne (selon les spécifications)
        if ($compte->type !== 'epargne') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Seuls les comptes épargne peuvent être bloqués'
                ]
            ], 400);
        }

        if (!$compte->peutEtreBloque()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Ce compte ne peut pas être bloqué'
                ]
            ], 400);
        }

        $result = $compte->bloquer($request->motif, $request->duree, $request->unite);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BLOCK_FAILED',
                    'message' => 'Échec du blocage du compte'
                ]
            ], 500);
        }

        // Invalider le cache
        Cache::forget("compte_{$compte->id}");
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Compte bloqué avec succès',
            'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/comptes/{compteId}/debloquer",
     *     tags={"Comptes"},
     *     summary="Débloquer un compte",
     *     description="Débloquer un compte bloqué",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="compteId",
     *         in="path",
     *         required=true,
     *         description="ID du compte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"motif"},
     *             @OA\Property(property="motif", type="string", maxLength=500)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte débloqué avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compte débloqué avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Compte")
     *         )
     *     )
     * )
     */
    public function debloquer(DebloquerCompteRequest $request, Compte $compte): JsonResponse
    {

        if (!$compte->peutEtreDebloque()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'Ce compte ne peut pas être débloqué'
                ]
            ], 400);
        }

        $result = $compte->debloquer($request->motif);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNBLOCK_FAILED',
                    'message' => 'Échec du déblocage du compte'
                ]
            ], 500);
        }

        // Invalider le cache
        Cache::forget("compte_{$compte->id}");
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Compte débloqué avec succès',
            'data' => $this->formatCompteData($compte->load('client:id,titulaire'))
        ]);
    }

    /**
     * Récupérer ou créer un client
     */
    private function getOrCreateClient(array $clientData): Client
    {
        if (isset($clientData['id']) && $clientData['id']) {
            return Client::findOrFail($clientData['id']);
        }

        // Créer un nouveau client
        $client = new Client();
        $client->titulaire = $clientData['titulaire'];
        $client->email = $clientData['email'];
        $client->telephone = $clientData['telephone'];
        $client->adresse = $clientData['adresse'] ?? null;
        $client->nci = $clientData['nci'] ?? null;
        $client->password = bcrypt('password123'); // Mot de passe temporaire
        $client->code = Client::generateCode();
        $client->save();

        return $client;
    }

    /**
     * Formater les données d'un compte pour la réponse
     */
    private function formatCompteData(Compte $compte): array
    {
        return [
            'id' => $compte->id,
            'numeroCompte' => $compte->numero_compte,
            'titulaire' => $compte->client->titulaire,
            'type' => $compte->type,
            'solde' => $compte->solde,
            'devise' => $compte->devise,
            'dateCreation' => $compte->created_at,
            'statut' => $compte->statut,
            'motifBlocage' => $compte->motif_blocage,
            'dateDebutBlocage' => $compte->date_debut_blocage,
            'dateFinBlocage' => $compte->date_fin_blocage,
            'metadata' => [
                'derniereModification' => $compte->derniere_modification,
                'version' => $compte->version
            ]
        ];
    }

    /**
     * Formater la réponse paginée
     */
    private function formatPaginatedResponse($paginatedData): array
    {
        return [
            'success' => true,
            'data' => $paginatedData->getCollection()->map(function ($compte) {
                return $this->formatCompteData($compte);
            }),
            'pagination' => [
                'currentPage' => $paginatedData->currentPage(),
                'totalPages' => $paginatedData->lastPage(),
                'totalItems' => $paginatedData->total(),
                'itemsPerPage' => $paginatedData->perPage(),
                'hasNext' => $paginatedData->hasMorePages(),
                'hasPrevious' => $paginatedData->currentPage() > 1
            ],
            'links' => [
                'self' => $paginatedData->url($paginatedData->currentPage()),
                'first' => $paginatedData->url(1),
                'last' => $paginatedData->url($paginatedData->lastPage()),
                'next' => $paginatedData->nextPageUrl(),
                'prev' => $paginatedData->previousPageUrl()
            ]
        ];
    }
}
