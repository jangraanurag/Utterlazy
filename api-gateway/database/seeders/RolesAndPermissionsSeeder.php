<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles
        $superAdmin = Role::create(['name' => 'SuperAdmin']);
        $restaurantManager = Role::create(['name' => 'RestaurantManager']);
        $customer = Role::create(['name' => 'Customer']);
        $rider = Role::create(['name' => 'Rider']);

        // Define permissions (can expand later)
        $permissions = [
            'manage restaurants',
            'manage own catalog',
            'place orders',
            'accept deliveries'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions
        $superAdmin->givePermissionTo(Permission::all());
        $restaurantManager->givePermissionTo('manage own catalog');
        $customer->givePermissionTo('place orders');
        $rider->givePermissionTo('accept deliveries');
    }
}
