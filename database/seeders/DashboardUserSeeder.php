<?php

namespace Database\Seeders;

use App\Models\DashboardUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DashboardUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DashboardUser::create([
            'first_name' => 'Irakli',
            'last_name' => 'Shevardenidze',
            'email' => 'iraklig2@gmail.com',
            'password' => Hash::make('Lamera22!'),
            'super_admin' => true,
        ]);
    }
}
