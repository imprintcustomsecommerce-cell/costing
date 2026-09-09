<?php

namespace Database\Seeders;

use App\Models\MaterialCategory;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The access system and the two lists a product needs before it can exist.
     * No materials or products: those are the user's own data.
     */
    public function run(): void
    {
        $permissions = [
            'manage users', 'manage products', 'manage materials',
            'manage customers', 'view internal costs', 'view audit logs',
        ];
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'SUPER ADMIN' => $permissions,
            'ADMIN' => array_values(array_diff($permissions, ['manage users'])),
        ];
        foreach ($roles as $name => $grants) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($grants);
        }

        $admin = User::updateOrCreate(['email' => 'admin@imprintcustoms.ph'], [
            'name' => 'Imprint Customs Admin', 'password' => 'imprint123', 'is_active' => true,
        ]);
        $admin->syncRoles(['SUPER ADMIN']);

        // Neither list has an admin screen, so a product or material could not
        // be created at all without at least one of each.
        foreach (['Apparel', 'Accessories'] as $name) {
            ProductCategory::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }
        foreach (['Fabric', 'Vinyl & Media', 'Thread', 'Ink', 'Packaging', 'Accessories'] as $name) {
            MaterialCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach (['company_name' => 'Imprint Customs', 'currency' => 'PHP'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], [
                'value' => $value, 'type' => is_numeric($value) ? 'decimal' : 'string',
                'group' => 'company', 'label' => ucwords(str_replace('_', ' ', $key)),
            ]);
        }
    }
}
