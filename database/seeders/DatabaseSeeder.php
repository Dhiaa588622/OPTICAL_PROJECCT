<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
        ]);

        $this->call(InventoryDemoSeeder::class);
        $this->call(SalesPosDemoSeeder::class);
        $this->call(PatientDemoSeeder::class);
        $this->call(OpticalOrderDemoSeeder::class);
        $this->call(AppointmentDemoSeeder::class);
        $this->call(WhatsAppDemoSeeder::class);
        $this->call(ErpFoundationSeeder::class);
    }
}
