<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class InvoicePermissionsSeeder extends Seeder
{
    public function run()
    {
        // إنشاء الصلاحيات
        $permissions = [
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',
            'view_payments',
            'create_payments',
            'edit_payments',
            'delete_payments',
            'view_financial_reports',
            'generate_invoices',
            'send_invoice_reminders',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // تخصيص الصلاحيات للأدوار
        $admin = Role::findByName('Admin');
        $admin->givePermissionTo($permissions);

        $manager = Role::findByName('Operations Manager');
        $manager->givePermissionTo([
            'view_invoices', 'view_payments', 'view_financial_reports'
        ]);
    }
}
