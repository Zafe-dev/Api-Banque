<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Créer une nouvelle table temporaire avec UUID
        Schema::create('users_temp', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->rememberToken();
            $table->timestamps();
        });

        // Copier les données existantes avec de nouveaux UUIDs
        DB::statement('INSERT INTO users_temp (id, name, email, email_verified_at, password, role, remember_token, created_at, updated_at) SELECT gen_random_uuid(), name, email, email_verified_at, password, role, remember_token, created_at, updated_at FROM users');

        // Supprimer les contraintes de clés étrangères
        Schema::table('comptes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Supprimer l'ancienne table et renommer la nouvelle
        Schema::dropIfExists('users');
        Schema::rename('users_temp', 'users');

        // Recréer la clé étrangère
        Schema::table('comptes', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Supprimer la clé primaire UUID
            $table->dropPrimary();

            // Revenir au type bigint auto-increment
            $table->dropColumn('id');
            $table->bigIncrements('id');
        });
    }
};
