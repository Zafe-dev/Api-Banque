<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client as OClient;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentification"},
     *     summary="Authentification utilisateur",
     *     description="Authentification d'un admin ou d'un client avec génération de token JWT. Les admins n'ont pas besoin de code, les clients oui pour la première connexion.",
     * @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@banque.com"),
     *             @OA\Property(property="password", type="string", example="admin123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connexion réussie"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="name", type="string", nullable=true),
     *                     @OA\Property(property="titulaire", type="string", nullable=true),
     *                     @OA\Property(property="telephone", type="string", nullable=true)
     *                 ),
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants invalides",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="INVALID_CREDENTIALS"),
     *                 @OA\Property(property="message", type="string", example="Email ou mot de passe incorrect")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Code de vérification requis",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="error",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="CODE_REQUIRED"),
     *                 @OA\Property(property="message", type="string", example="Code de vérification requis pour la première connexion")
     *             )
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Trouver l'utilisateur par email dans les admins (users table) d'abord
        $user = \App\Models\Admin::where('email', $request->email)->first();

        if (!$user) {
            // Si pas trouvé dans admins, chercher dans clients
            $user = Client::where('email', $request->email)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Email ou mot de passe incorrect'
                ]
            ], 401);
        }

        // Vérifier si c'est un client et si c'est la première connexion (code requis)
        if ($user instanceof Client && is_null($user->code_verified_at)) {
            if (!$request->has('code') || $request->code !== $user->code) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CODE_REQUIRED',
                        'message' => 'Code de vérification requis pour la première connexion'
                    ]
                ], 403);
            }

            // Marquer le code comme vérifié
            $user->code_verified_at = now();
            $user->save();
        }

        // Générer les tokens avec Passport
        $tokenResult = $user->createToken('API Token');
        $token = $tokenResult->accessToken;

        // Formater les données utilisateur selon le type
        $userData = [
            'id' => $user->id,
            'email' => $user->email,
        ];

        if ($user instanceof Client) {
            $userData['titulaire'] = $user->titulaire;
            $userData['telephone'] = $user->telephone;
        } elseif ($user instanceof \App\Models\Admin) {
            $userData['name'] = $user->name;
        }

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => $userData,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600 // 1 heure
            ]
        ]);
    }

    /**
     * Rafraîchir le token d'accès
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Token invalide'
                ]
            ], 401);
        }

        // Révoquer l'ancien token
        $request->user()->token()->revoke();

        // Créer un nouveau token
        $tokenResult = $user->createToken('API Token');
        $token = $tokenResult->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Token rafraîchi',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request): JsonResponse
    {
        // Révoquer le token actuel
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Authentification"},
     *     summary="Inscription utilisateur",
     *     description="Inscription d'un nouveau client",
     * @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"email", "password", "titulaire", "telephone"},
     *             @OA\Property(property="email", type="string", format="email", example="client@banque.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="titulaire", type="string", example="John Doe"),
     *             @OA\Property(property="telephone", type="string", example="0123456789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inscription réussie"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="titulaire", type="string"),
     *                     @OA\Property(property="telephone", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
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
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:clients,email',
            'password' => 'required|min:8',
            'titulaire' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
        ]);

        $client = Client::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'titulaire' => $request->titulaire,
            'telephone' => $request->telephone,
            'code' => rand(100000, 999999),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'data' => [
                'user' => [
                    'id' => $client->id,
                    'email' => $client->email,
                    'titulaire' => $client->titulaire,
                    'telephone' => $client->telephone,
                ]
            ]
        ], 201);
    }
}
