<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::firstOrCreate([
            'email' => 'admin@banque.com'
        ], [
            'name' => 'Administrateur',
            'email' => 'admin@banque.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        Admin::firstOrCreate([
            'email' => 'admin1@banque.com'
        ], [
            'name' => 'Administrateur 1',
            'email' => 'admin1@banque.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);
    }
}
