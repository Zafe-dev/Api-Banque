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

class ArchiveBlockedAccounts implements ShouldQueue
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
        Log::info('Starting ArchiveBlockedAccounts job');

        // Trouver tous les comptes épargne bloqués dont la date de début de blocage est atteinte
        $blockedAccounts = Compte::where('statut', 'bloque')
            ->where('type', 'epargne')
            ->where('date_debut_blocage', '<=', now())
            ->get();

        Log::info('Found ' . $blockedAccounts->count() . ' blocked accounts to archive');

        foreach ($blockedAccounts as $compte) {
            try {
                // Archiver dans la base Neon (simulé ici)
                // En production, vous connecteriez à la base Neon
                DB::connection('neon')->table('archived_comptes')->insert([
                    'id' => $compte->id,
                    'numero_compte' => $compte->numero_compte,
                    'client_id' => $compte->client_id,
                    'type' => $compte->type,
                    'solde' => $compte->solde,
                    'devise' => $compte->devise,
                    'statut' => $compte->statut,
                    'motif_blocage' => $compte->motif_blocage,
                    'date_debut_blocage' => $compte->date_debut_blocage,
                    'date_fin_blocage' => $compte->date_fin_blocage,
                    'archived_at' => now(),
                    'created_at' => $compte->created_at,
                    'updated_at' => $compte->updated_at,
                ]);

                // Supprimer de la base principale
                $compte->delete();

                Log::info('Archived account', ['compte_id' => $compte->id, 'numero_compte' => $compte->numero_compte]);

            } catch (\Exception $e) {
                Log::error('Failed to archive account', [
                    'compte_id' => $compte->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('ArchiveBlockedAccounts job completed');
    }
}
