<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // list of tables to manage
        $tables = [
            'faqs',
            'additional_fees',
            'customers',
            'user',
            'bookings',
            'booking_fees',
            'testimonials',
            'permissions',
            'hotel_requests',
            'trips_prices',
            'trips_durations',
            'trips',
            'itineraries',
            'flight_schedule',
            'email_blasts',
            'detail_transactions',
            'surcharge',
            'boats',
            'cabins',
            'galleries',
            'transactions',
            'assets',
            'hotel_occupancies',
            'role',
            'blogs',
            'subscribers',
            'bank_accounts',
            'email_blast_recipients',
            'carousels',
        ];

        // create permissions for each table using "mengelola {table}"
        foreach ($tables as $table) {
            Permission::create(['name' => "mengelola {$table}"]);
        }

        // create additional permissions
        $additionalPermissions = [
            'melakukan transactions',
            'melihat hotel occupancy',
            'melakukan request hotel',
            'melakukan pembayaran',
            'melakukan booking',
        ];

        foreach ($additionalPermissions as $perm) {
            Permission::create(['name' => $perm]);
        }

        // create roles
        $admin = Role::create(['name' => 'Admin']);
        $staff = Role::create(['name' => 'Staff']);
        $pelanggan = Role::create(['name' => 'Pelanggan']);

        // assign all permissions to Admin
        $admin->givePermissionTo(Permission::all());

        // For Staff, assign all permissions except those for managing role, user, and permissions
        $staffPermissions = Permission::whereNotIn('name', [
            'mengelola role',
            'mengelola user',
            'mengelola permissions'
        ])->get();
        $staff->givePermissionTo($staffPermissions);

        // For Pelanggan, assign only specific additional permissions
        $pelanggan->givePermissionTo([
            'melakukan transactions',
            'melihat hotel occupancy',
            'melakukan request hotel',
            'melakukan pembayaran',
            'melakukan booking',
        ]);
    }
}
