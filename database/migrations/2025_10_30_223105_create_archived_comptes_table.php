<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('archived_comptes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero_compte');
            $table->uuid('client_id');
            $table->enum('type', ['epargne', 'cheque']);
            $table->decimal('solde', 15, 2);
            $table->string('devise');
            $table->enum('statut', ['actif', 'bloque', 'ferme']);
            $table->string('motif_blocage')->nullable();
            $table->timestamp('date_debut_blocage')->nullable();
            $table->timestamp('date_fin_blocage')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();

            // $table->index(['date_fin_blocage', 'type']);
            // $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_comptes');
    }
};
