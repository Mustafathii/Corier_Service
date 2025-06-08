<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // Shipment permissions
            'view_all_shipments',
            'view_own_shipments',
            'create_shipments',
            'edit_shipments',
            'delete_shipments',
            'assign_shipments',
            'update_shipment_status',

            // Driver permissions
            'view_drivers',
            'create_drivers',
            'edit_drivers',
            'delete_drivers',

            // System permissions
            'access_admin_panel',
            'view_reports',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $admin = Role::create(['name' => 'Admin']);
        $manager = Role::create(['name' => 'Operations Manager']);
        $driver = Role::create(['name' => 'Driver']);
        $seller = Role::create(['name' => 'Seller']);

        // Assign permissions to roles
        $admin->givePermissionTo(Permission::all());

        $manager->givePermissionTo([
            'view_users',
            'view_all_shipments',
            'create_shipments',
            'edit_shipments',
            'assign_shipments',
            'view_drivers',
            'access_admin_panel',
            'view_reports',
        ]);

        $driver->givePermissionTo([
            'view_own_shipments',
            'update_shipment_status',
            'access_admin_panel',
        ]);

        $seller->givePermissionTo([
            'view_own_shipments',
            'create_shipments',
            'access_admin_panel',
        ]);

        // Create default admin user
        $adminUser = User::create([
            'name' => 'System Admin',
            'email' => 'admin@shipping.com',
            'password' => bcrypt('admin123'),
            'phone' => '+1234567890',
            'is_active' => true,
        ]);
        $adminUser->assignRole('Admin');

        // Create operations manager
        $managerUser = User::create([
            'name' => 'Operations Manager',
            'email' => 'manager@shipping.com',
            'password' => bcrypt('manager123'),
            'phone' => '+1234567891',
            'is_active' => true,
        ]);
        $managerUser->assignRole('Operations Manager');
    }
}
