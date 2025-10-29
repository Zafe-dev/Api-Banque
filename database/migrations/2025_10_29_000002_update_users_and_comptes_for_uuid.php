<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer d'abord la clé étrangère de comptes
        Schema::table('comptes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // Modifier la table users
        Schema::table('users', function (Blueprint $table) {
            // Ajouter une colonne uuid temporaire
            $table->uuid('temp_id')->nullable();
        });

        // Générer des UUIDs pour les utilisateurs existants
        DB::table('users')->update([
            'temp_id' => DB::raw('gen_random_uuid()')
        ]);

        // Mettre à jour les références dans d'autres tables si nécessaire
        // ...

        // Supprimer l'ancienne colonne id
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // Renommer la colonne temporaire
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('temp_id', 'id');
            $table->primary('id');
        });

        // Recréer la colonne user_id dans comptes
        Schema::table('comptes', function (Blueprint $table) {
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        throw new \Exception('Cette migration n\'est pas réversible.');
    }
};