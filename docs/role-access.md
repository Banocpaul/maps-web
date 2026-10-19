# MAPS role responsibilities

The Operations Manager oversees fire and flood operations. Administrator handles system administration. `operations-officer` remains a supported alias of `operations-manager` for existing accounts.

| Role | Workspace and controls | Excluded controls |
| --- | --- | --- |
| Administrator | Accounts, role assignment, password requests, account security, system analytics, administrative audit logs, database backup and recovery | Internal incidents, predictions, GIS, SMS, public report review, advisories, operational exports and incident archives |
| Operations Manager / Operations Officer | Both hazard workflows, public report validation/publication, prediction runs and review, hydrants, GIS, SMS and automation, operational records/exports, incident archives/restoration, annual analytics and printing, incident SLA and reporting settings; may manage advisories published by other staff | User administration, security logs, system analytics, database backup/recovery |
| Fire Responder | Fire reports and incidents, response updates and assessment, hydrants, fire GIS/analytics, fire and shared advisories owned by the responder | Flood/prediction controls, SMS administration, citywide records/archive administration, permanent role/system administration, incident deletion |
| Flood Analyst | Flood reports and observations, lifecycle changes, predictions and review, flood GIS/analytics, flood/weather and shared advisories owned by the analyst | Fire/hydrant controls, SMS administration, citywide records/archive administration, system administration, incident deletion |
| System Viewer | Dashboard, fire/flood information, maps, prediction history and read-only analytics | Record mutations, prediction/simulation execution, forced weather refresh, staff report review, publishing, SMS and administration |
| Public Resident | Existing public portal, own submissions/account and alert preferences | All internal workspaces and staff actions |

## Enforcement

- `App\Support\RoleAccess` defines the role capability limits. A stale or accidentally broad permission grant cannot turn Admin, hazard specialists, residents or viewers into another role.
- Operational roles also require an active database permission assignment. Inactive/suspended users and inactive roles are rejected. Admin's built-in capabilities are limited to the explicit system administration list.
- Backend route permissions, hazard checks, advisory ownership/type checks, dashboard data queries and visible controls follow the same separation.
- Admin audit browsing, filters, counts, exports and system analytics use only administrative/security modules. Operational audit events are retained in the database.
- Database backups remain an infrastructure responsibility of Administrator; this does not grant access to incident pages or operational workflows.
- Public information remains public. The stateless `maps-ml-api` prediction service has no MAPS user accounts or role assignments; authenticated internal workflows are authorized in `maps-web`.

## Deployment

Run `php artisan migrate --force` when deploying. The existing Docker startup already runs it. Migration `2026_10_19_120000_separate_administration_and_operations` reconciles existing built-in role assignments without reactivating inactive roles or permissions. It is idempotent. Its rollback intentionally does not restore unrestricted Admin access.

Fresh installs use `RolePermissionSeeder`, including advisory permissions. No account recreation is required.

## Demo checks

1. Log in as Administrator. Open Dashboard, User Management, System Analytics, Activity Logs and Backup & Recovery. Operational menus must be absent; direct operational URLs must return 403.
2. Log in as Operations Manager. Open Incident Reports, Fire Management, Flood Management, GIS, SMS Center, Operational Records and Incident Archives. Generate/print annual archive analytics. User Management and Backup & Recovery must return 403.
3. Log in as Fire Responder and Flood Analyst. Each can review and act on its own hazard. Attempts to use the other department's controls must fail.
4. Log in as System Viewer. Read-only navigation must work; create/update, prediction-run, simulation and publication controls must be absent or forbidden.
5. Log in as a Public Resident. Own public submissions/account remain available; internal staff URLs must be forbidden.

The automated `RoleAccessSeparationTest` covers these role boundaries, legacy grants, migration behavior and advisory oversight. Existing workflow tests use the appropriate operational actors.
