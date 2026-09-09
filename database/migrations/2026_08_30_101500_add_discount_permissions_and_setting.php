<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $approve = Permission::firstOrCreate([
            'name' => 'approve discounts',
            'guard_name' => 'web',
        ]);
        $override = Permission::firstOrCreate([
            'name' => 'override minimum margin',
            'guard_name' => 'web',
        ]);

        Role::where('guard_name', 'web')
            ->whereIn('name', ['ADMIN', 'SUPER ADMIN'])
            ->each(fn (Role $role) => $role->givePermissionTo($approve));
        Role::where('guard_name', 'web')
            ->where('name', 'SUPER ADMIN')
            ->each(fn (Role $role) => $role->givePermissionTo($override));

        Setting::firstOrCreate(
            ['key' => 'minimum_gross_margin'],
            [
                'value' => '30',
                'type' => 'decimal',
                'group' => 'pricing',
                'label' => 'Minimum Gross Margin',
            ],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Authorization records are deliberately retained on rollback. Removing a
     * permission that an administrator may already have assigned is a more
     * destructive rollback than leaving an unused permission in place.
     */
    public function down(): void
    {
        // No destructive data rollback.
    }
};
