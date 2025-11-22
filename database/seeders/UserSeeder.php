<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\CustomersFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an admin user using the User factory
        $admin = User::factory()->create([
            'name'  => 'Admin',
            'email' => 'admin@example.com',
        ]);

        // Assign the Admin role
        $admin->assignRole('Admin');
        // Remove the user from the default role
        $admin->removeRole('Pelanggan');

        // Create additional users as needed using the factory
        // Example: create a staff user
        // $staff = User::factory()->create([
        //     'name'  => 'Staff User',
        //     'email' => 'staff@example.com',
        // ]);
        // $staff->assignRole('Staff');
        // Remove the user from the default role
        // $admin->removeRole('Pelanggan');

        // Create a pelanggan user
        // $pelanggan = User::factory()
        //     ->create([
        //         'name'  => 'Pelanggan User',
        //         'email' => 'pelanggan@example.com',
        //     ]);
        // $pelanggan->assignRole('Pelanggan');
        // CustomersFactory::new()->create(['user_id' => $pelanggan->id]);

        // Create 10 more pelanggan users
        // for ($i = 1; $i <= 20; $i++) {
        //     $user = User::factory()
        //         ->create([
        //             'name'  => 'Pelanggan ' . $i,
        //             'email' => 'pelanggan' . $i . '@example.com',
        //         ]);
        //     $user->assignRole('Pelanggan');
        //     CustomersFactory::new()->create(['user_id' => $user->id]);
        // }
    }
}
