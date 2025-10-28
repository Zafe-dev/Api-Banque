<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WelcomeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/welcome",
     *     tags={"Public"},
     *     summary="Welcome endpoint",
     *     description="Returns a welcome message and logs the request metadata",
     *     @OA\Response(
     *         response=200,
     *         description="Welcome message",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Welcome to the Laravel API Service!")
     *         )
     *     )
     * )
     */
    public function welcome(Request $request): JsonResponse
    {
        // Log the request metadata
        Log::info("Request received: {$request->method()} {$request->path()}", [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Welcome to the Laravel API Service!'
        ]);
    }
}
