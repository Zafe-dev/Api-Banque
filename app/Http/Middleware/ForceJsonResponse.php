<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Forcer les headers pour indiquer que nous attendons du JSON
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Content-Type', 'application/json');

        // Traiter la requête
        $response = $next($request);

        // Forcer le Content-Type de la réponse à JSON
        $response->header('Content-Type', 'application/json');

        return $response;
    }
}