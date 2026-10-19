<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = [
                [
                    'name' => 'Administrator',
                    'slug' => 'administrator',
                    'description' => 'Manages accounts, access, security, configuration, and backups. No operational access.',
                ],
                [
                    'name' => 'Operations Manager',
                    'slug' => 'operations-manager',
                    'description' => 'Manages citywide operational records, predictions, GIS data, communications, and controlled data exports.',
                ],
                [
                    'name' => 'Flood Analyst',
                    'slug' => 'flood-analyst',
                    'description' => 'Manages flood data, prediction analytics, and flood-related reports.',
                ],
                [
                    'name' => 'Fire Responder',
                    'slug' => 'fire-responder',
                    'description' => 'Manages fire incidents, hydrants, response information, and fire maps.',
                ],
                [
                    'name' => 'System Viewer',
                    'slug' => 'system-viewer',
                    'description' => 'Authenticated read-only access to approved internal information.',
                ],
            ];

            foreach ($roles as $roleData) {
                Role::updateOrCreate(
                    ['slug' => $roleData['slug']],
                    [
                        ...$roleData,
                        'is_active' => true,
                    ]
                );
            }

            $permissions = [
                [
                    'name' => 'View Dashboard',
                    'slug' => 'dashboard.view',
                    'module' => 'dashboard',
                    'description' => 'View the authenticated system dashboard.',
                ],
                [
                    'name' => 'View Flood Module',
                    'slug' => 'flood.view',
                    'module' => 'flood',
                    'description' => 'View flood records and information.',
                ],
                [
                    'name' => 'Create Flood Records',
                    'slug' => 'flood.create',
                    'module' => 'flood',
                    'description' => 'Create official flood records.',
                ],
                [
                    'name' => 'Edit Flood Records',
                    'slug' => 'flood.edit',
                    'module' => 'flood',
                    'description' => 'Edit official flood records.',
                ],
                [
                    'name' => 'Delete Flood Records',
                    'slug' => 'flood.delete',
                    'module' => 'flood',
                    'description' => 'Delete official flood records.',
                ],
                [
                    'name' => 'View Fire Module',
                    'slug' => 'fire.view',
                    'module' => 'fire',
                    'description' => 'View fire incidents and responder information.',
                ],
                [
                    'name' => 'Create Fire Records',
                    'slug' => 'fire.create',
                    'module' => 'fire',
                    'description' => 'Create official fire records.',
                ],
                [
                    'name' => 'Edit Fire Records',
                    'slug' => 'fire.edit',
                    'module' => 'fire',
                    'description' => 'Edit official fire and response records.',
                ],
                [
                    'name' => 'Delete Fire Records',
                    'slug' => 'fire.delete',
                    'module' => 'fire',
                    'description' => 'Delete official fire records.',
                ],
                [
                    'name' => 'Manage Fire Hydrants',
                    'slug' => 'hydrants.manage',
                    'module' => 'fire',
                    'description' => 'Create and update fire hydrant information.',
                ],
                [
                    'name' => 'View Prediction Dashboard',
                    'slug' => 'prediction.view',
                    'module' => 'prediction',
                    'description' => 'View flood prediction results.',
                ],
                [
                    'name' => 'Run Predictions',
                    'slug' => 'prediction.run',
                    'module' => 'prediction',
                    'description' => 'Run barangay and citywide flood predictions.',
                ],
                [
                    'name' => 'Review Prediction Results',
                    'slug' => 'prediction.review',
                    'module' => 'prediction',
                    'description' => 'Add remarks to saved flood prediction runs.',
                ],
                [
                    'name' => 'Manage Prediction Data',
                    'slug' => 'prediction.data.manage',
                    'module' => 'prediction',
                    'description' => 'Manage validated data prepared for analytics and model use.',
                ],
                [
                    'name' => 'View Operational Records',
                    'slug' => 'records.view',
                    'module' => 'records',
                    'description' => 'Search and review approved operational database records.',
                ],
                [
                    'name' => 'Export Operational Records',
                    'slug' => 'records.export',
                    'module' => 'records',
                    'description' => 'Export filtered operational records in CSV format.',
                ],
                [
                    'name' => 'View GIS',
                    'slug' => 'gis.view',
                    'module' => 'gis',
                    'description' => 'View GIS maps and approved map layers.',
                ],
                [
                    'name' => 'Manage GIS',
                    'slug' => 'gis.manage',
                    'module' => 'gis',
                    'description' => 'Manage internal GIS layers and geographic records.',
                ],
                [
                    'name' => 'View SMS Center',
                    'slug' => 'sms.view',
                    'module' => 'sms',
                    'description' => 'View the SMS Center and message history.',
                ],
                [
                    'name' => 'Send SMS Alerts',
                    'slug' => 'sms.send',
                    'module' => 'sms',
                    'description' => 'Send internal disaster-response SMS alerts.',
                ],
                [
                    'name' => 'Manage SMS Recipients',
                    'slug' => 'sms.recipients.manage',
                    'module' => 'sms',
                    'description' => 'Create, update, and remove SMS recipients.',
                ],
                [
                    'name' => 'Manage SMS Automation Rules',
                    'slug' => 'sms.automation.manage',
                    'module' => 'sms',
                    'description' => 'Create, update, enable, disable, and remove SMS automation rules.',
                ],
                [
                    'name' => 'View Reports',
                    'slug' => 'reports.view',
                    'module' => 'reports',
                    'description' => 'View approved system reports.',
                ],
                [
                    'name' => 'Export Reports',
                    'slug' => 'reports.export',
                    'module' => 'reports',
                    'description' => 'Export reports to supported file formats.',
                ],
                [
                    'name' => 'View Public Submissions',
                    'slug' => 'public-submissions.view',
                    'module' => 'public-submissions',
                    'description' => 'View public community submissions.',
                ],
                [
                    'name' => 'Review Public Submissions',
                    'slug' => 'public-submissions.review',
                    'module' => 'public-submissions',
                    'description' => 'Review and add notes to public submissions.',
                ],
                [
                    'name' => 'Approve Public Submissions',
                    'slug' => 'public-submissions.approve',
                    'module' => 'public-submissions',
                    'description' => 'Approve validated public submissions.',
                ],
                [
                    'name' => 'Reject Public Submissions',
                    'slug' => 'public-submissions.reject',
                    'module' => 'public-submissions',
                    'description' => 'Reject invalid or unverifiable public submissions.',
                ],
                [
                    'name' => 'Manage Users',
                    'slug' => 'users.manage',
                    'module' => 'administration',
                    'description' => 'Create, edit, activate, deactivate, and delete user accounts.',
                ],
                [
                    'name' => 'Manage Roles and Permissions',
                    'slug' => 'roles.manage',
                    'module' => 'administration',
                    'description' => 'Manage roles and their assigned permissions.',
                ],
                [
                    'name' => 'View Activity Logs',
                    'slug' => 'activity-logs.view',
                    'module' => 'administration',
                    'description' => 'View security and system activity records.',
                ],
                [
                    'name' => 'Manage System Settings',
                    'slug' => 'settings.manage',
                    'module' => 'administration',
                    'description' => 'Manage protected system configuration.',
                ],
            ];

            foreach ($permissions as $permissionData) {
                Permission::updateOrCreate(
                    ['slug' => $permissionData['slug']],
                    [
                        ...$permissionData,
                        'is_active' => true,
                    ]
                );
            }

            $rolePermissions = \App\Support\RoleAccess::matrix();
            unset($rolePermissions['public-resident']);

            foreach (array_unique(array_merge(...array_values($rolePermissions))) as $slug) {
                Permission::firstOrCreate(['slug' => $slug], [
                    'name' => str($slug)->replace('.', ' ')->headline()->toString(),
                    'module' => explode('.', $slug)[0], 'is_active' => true,
                ]);
            }

            if (Role::where('slug', 'operations-officer')->exists()) {
                $rolePermissions['operations-officer'] = $rolePermissions['operations-manager'];
            }

            foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
                $role = Role::where('slug', $roleSlug)->firstOrFail();

                $permissionIds = Permission::whereIn('slug', $permissionSlugs)
                    ->pluck('id');

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
