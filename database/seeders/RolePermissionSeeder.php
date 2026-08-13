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
                    'description' => 'Full system administration and unrestricted system access.',
                ],
                [
                    'name' => 'Operations Officer',
                    'slug' => 'operations-officer',
                    'description' => 'Manages operational incidents, public submissions, GIS, and SMS alerts.',
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
                    'name' => 'Manage Prediction Data',
                    'slug' => 'prediction.data.manage',
                    'module' => 'prediction',
                    'description' => 'Manage validated data prepared for analytics and model use.',
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

            $rolePermissions = [
                'administrator' => Permission::pluck('slug')->all(),

                'operations-officer' => [
                    'dashboard.view',
                    'flood.view',
                    'flood.create',
                    'flood.edit',
                    'fire.view',
                    'fire.create',
                    'fire.edit',
                    'hydrants.manage',
                    'prediction.view',
                    'prediction.run',
                    'gis.view',
                    'gis.manage',
                    'sms.view',
                    'sms.send',
                    'sms.recipients.manage',
                    'reports.view',
                    'reports.export',
                    'public-submissions.view',
                    'public-submissions.review',
                    'public-submissions.approve',
                    'public-submissions.reject',
                ],

                'flood-analyst' => [
                    'dashboard.view',
                    'flood.view',
                    'flood.create',
                    'flood.edit',
                    'prediction.view',
                    'prediction.run',
                    'prediction.data.manage',
                    'gis.view',
                    'reports.view',
                    'reports.export',
                    'public-submissions.view',
                    'public-submissions.review',
                    'public-submissions.approve',
                    'public-submissions.reject',
                ],

                'fire-responder' => [
                    'dashboard.view',
                    'fire.view',
                    'fire.create',
                    'fire.edit',
                    'hydrants.manage',
                    'gis.view',
                    'reports.view',
                    'public-submissions.view',
                    'public-submissions.review',
                ],

                'system-viewer' => [
                    'dashboard.view',
                    'flood.view',
                    'fire.view',
                    'prediction.view',
                    'gis.view',
                    'reports.view',
                ],
            ];

            foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
                $role = Role::where('slug', $roleSlug)->firstOrFail();

                $permissionIds = Permission::whereIn('slug', $permissionSlugs)
                    ->pluck('id');

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}