<?php

namespace App\Support;

/** Maximum capabilities for each MAPS role; database grants may narrow operational access. */
final class RoleAccess
{
    public static function canonical(string $slug): string
    {
        return $slug === 'operations-officer' ? 'operations-manager' : $slug;
    }

    public static function permissions(string $slug): array
    {
        return self::matrix()[self::canonical($slug)] ?? [];
    }

    public static function advisoryTypes(string $slug): array
    {
        return match (self::canonical($slug)) {
            'fire-responder' => ['fire', 'evacuation', 'general'],
            'flood-analyst' => ['flood', 'weather', 'evacuation', 'class-suspension', 'general'],
            'operations-manager' => ['flood', 'fire', 'weather', 'evacuation', 'class-suspension', 'general'],
            default => [],
        };
    }

    public static function matrix(): array
    {
        return [
            'administrator' => [
                'dashboard.view',
                'users.manage',
                'roles.manage',
                'activity-logs.view',
                'settings.manage',
            ],
            'operations-manager' => [
                'dashboard.view',
                'flood.view',
                'flood.create',
                'flood.edit',
                'flood.delete',
                'fire.view',
                'fire.create',
                'fire.edit',
                'fire.delete',
                'hydrants.manage',
                'prediction.view',
                'prediction.run',
                'prediction.review',
                'prediction.data.manage',
                'records.view',
                'records.export',
                'gis.view',
                'gis.manage',
                'sms.view',
                'sms.send',
                'sms.recipients.manage',
                'sms.automation.manage',
                'reports.view',
                'reports.export',
                'public-submissions.view',
                'public-submissions.review',
                'public-submissions.approve',
                'public-submissions.reject',
                'advisories.view',
                'advisories.create',
                'advisories.edit',
                'advisories.delete',
            ],
            'flood-analyst' => [
                'dashboard.view',
                'flood.view',
                'flood.create',
                'flood.edit',
                'prediction.view',
                'prediction.run',
                'prediction.review',
                'prediction.data.manage',
                'gis.view',
                'reports.view',
                'reports.export',
                'public-submissions.view',
                'public-submissions.review',
                'public-submissions.approve',
                'public-submissions.reject',
                'advisories.view',
                'advisories.create',
                'advisories.edit',
                'advisories.delete',
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
                'public-submissions.approve',
                'public-submissions.reject',
                'advisories.view',
                'advisories.create',
                'advisories.edit',
                'advisories.delete',
            ],
            'system-viewer' => [
                'dashboard.view',
                'flood.view',
                'fire.view',
                'prediction.view',
                'gis.view',
                'reports.view',
            ],
            'public-resident' => [
            ],
        ];
    }
}
