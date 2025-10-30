<?php

namespace App\Jobs;

use App\Models\Compte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreExpiredAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting RestoreExpiredAccounts job');

        // Récupérer tous les comptes archivés dans Neon dont la durée de blocage est expirée
        $expiredAccounts = DB::connection('neon')
            ->table('archived_comptes')
            ->where('date_fin_blocage', '<=', now())
            ->get();

        Log::info('Found ' . $expiredAccounts->count() . ' expired accounts to restore');

        foreach ($expiredAccounts as $archivedAccount) {
            try {
                // Restaurer dans la base principale
                Compte::create([
                    'id' => $archivedAccount->id,
                    'numero_compte' => $archivedAccount->numero_compte,
                    'client_id' => $archivedAccount->client_id,
                    'type' => $archivedAccount->type,
                    'solde' => $archivedAccount->solde,
                    'devise' => $archivedAccount->devise,
                    'statut' => 'actif', // Remettre en actif
                    'motif_blocage' => null,
                    'date_debut_blocage' => null,
                    'date_fin_blocage' => null,
                    'created_at' => $archivedAccount->created_at,
                    'updated_at' => now(),
                ]);

                // Supprimer de la base d'archivage
                DB::connection('neon')
                    ->table('archived_comptes')
                    ->where('id', $archivedAccount->id)
                    ->delete();

                Log::info('Restored account', [
                    'compte_id' => $archivedAccount->id,
                    'numero_compte' => $archivedAccount->numero_compte
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to restore account', [
                    'compte_id' => $archivedAccount->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('RestoreExpiredAccounts job completed');
    }
}
