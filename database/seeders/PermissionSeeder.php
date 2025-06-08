<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Shipment permissions
            'view_shipments',
            'view_own_shipments',
            'create_shipments',
            'edit_shipments',
            'edit_own_shipments',
            'delete_shipments',
            'assign_shipments',
            'update_shipment_status',

            // Driver permissions
            'view_drivers',
            'create_drivers',
            'edit_drivers',
            'delete_drivers',
            'assign_drivers',

            // User permissions
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_roles',

            // Report permissions
            'view_reports',
            'view_dashboard',
            'export_data',

            // System permissions
            'access_admin_panel',
            'manage_settings',
            'view_system_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Admin Role - All permissions
        $adminRole = Role::create(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Operations Manager Role
        $operationsRole = Role::create(['name' => 'Operations Manager']);
        $operationsRole->givePermissionTo([
            'view_shipments',
            'create_shipments',
            'edit_shipments',
            'assign_shipments',
            'view_drivers',
            'assign_drivers',
            'view_reports',
            'view_dashboard',
            'access_admin_panel',
            'export_data',
        ]);

        // Driver Role
        $driverRole = Role::create(['name' => 'Driver']);
        $driverRole->givePermissionTo([
            'view_own_shipments',
            'update_shipment_status',
            'access_admin_panel',
        ]);

        // Seller Role
        $sellerRole = Role::create(['name' => 'Seller']);
        $sellerRole->givePermissionTo([
            'view_own_shipments',
            'create_shipments',
            'edit_own_shipments',
            'access_admin_panel',
        ]);

        // Create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@shipping.com'],
            [
                'name' => 'System Administrator',
                'password' => bcrypt('admin123'),
                'phone' => '+1234567890',
            ]
        );
        $admin->assignRole('Admin');

        // Create sample operations manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@shipping.com'],
            [
                'name' => 'Operations Manager',
                'password' => bcrypt('manager123'),
                'phone' => '+1234567891',
            ]
        );
        $manager->assignRole('Operations Manager');
    }
}
