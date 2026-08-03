<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\Permission;
use App\Infrastructure\Persistence\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'organizations.view' => 'View organizations',
            'organizations.manage' => 'Manage organizations',
            'projects.view' => 'View projects',
            'projects.manage' => 'Manage projects',
            'config.manage' => 'Manage configuration',
            'secrets.manage' => 'Manage secrets',
            'secrets.reveal' => 'Reveal secret values',
            'flags.manage' => 'Manage feature flags',
            'audit.view' => 'View audit log',
            'connectors.view' => 'View connectors',
            'connectors.manage' => 'Manage connectors',
            'jobs.view' => 'View and dispatch jobs',
            'diagnostics.view' => 'View diagnostics and metrics',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        $allPermissions = Permission::all();

        $orgRoles = [
            'owner' => $allPermissions->pluck('name')->all(),
            'admin' => [
                'organizations.view', 'organizations.manage',
                'projects.view', 'projects.manage',
                'config.manage', 'secrets.manage', 'secrets.reveal',
                'flags.manage', 'audit.view',
                'connectors.view', 'connectors.manage',
                'jobs.view', 'diagnostics.view',
            ],
            'member' => [
                'organizations.view', 'projects.view',
                'audit.view', 'connectors.view', 'jobs.view',
            ],
        ];

        foreach ($orgRoles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'scope' => RoleScope::Organization],
                ['description' => "Organization {$roleName} role"]
            );
            $role->permissions()->sync(
                Permission::whereIn('name', $permissionNames)->pluck('id')
            );
        }

        $projectRoles = [
            'admin' => [
                'projects.view', 'projects.manage',
                'config.manage', 'secrets.manage', 'secrets.reveal',
                'flags.manage', 'connectors.view', 'connectors.manage',
                'jobs.view',
            ],
            'editor' => [
                'projects.view', 'config.manage',
                'connectors.view', 'jobs.view',
            ],
            'viewer' => [
                'projects.view', 'connectors.view', 'jobs.view',
            ],
        ];

        foreach ($projectRoles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'scope' => RoleScope::Project],
                ['description' => "Project {$roleName} role"]
            );
            $role->permissions()->sync(
                Permission::whereIn('name', $permissionNames)->pluck('id')
            );
        }
    }
}
