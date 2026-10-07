# M.A.P.S. audit repairs — 7 October 2026

These changes address the non-prediction findings from the live audit. They do not change the prediction controller, page, model services, weather snapshot service, API routes, observation enrichment, trained models, or datasets. No live records, account credentials, SMS recipients, or backups were modified during this repair work.

## Issue disposition

| Audit ID | Disposition | Change or remaining work |
| --- | --- | --- |
| MAPS-01 | Left unchanged to preserve prediction | Conflicting code/depth definitions need an agreed canonical definition. No depth mapping or historical data was rewritten. |
| MAPS-02 | Code fixed | User timestamps and fire forms/details use Manila time. Fire create/update normalize Manila form inputs to UTC; GIS dates include an explicit +08:00 offset; SMS shows Manila time. Existing database timestamps are not shifted. |
| MAPS-03 | Code fixed | Flood Analyst's Flood GIS shortcut opens the existing active flood map with the Flood filter selected. |
| MAPS-04 | Partially fixed; hosting/recovery needed | Report uploads support a configurable private persistent disk and record the disk per attachment. Legacy photos still resolve on local storage. Unavailable files have a visible explanation. An original photo already lost from storage cannot be recreated by this patch. |
| MAPS-05 | Code fixed | Dashboard 24-hour rainfall reads the actual service field, rainfall_24h_mm, including zero as a valid measurement. |
| MAPS-06 | Code fixed | Removed flood depth/duration presentation from the affected analytics cards, recent observations, and chart. Replaced the depth/rain chart with rainfall alone. Stored columns, training data, and prediction inputs remain intact. Fire duration analytics remain. |
| MAPS-07 | Left unchanged to preserve prediction | Prediction window anchoring and weather-fetch policy remain exactly as supplied. |
| MAPS-08 | Code fixed | Weather-day and public hazard buttons expose their selected state with aria-pressed. |
| MAPS-09 | Code fixed | Public fire and staff hydrant/fire markers have descriptive titles and explicit accessible labels. |
| MAPS-10 | Code fixed | SMS name, telephone, position, office, barangay and message labels are associated with their controls. |
| MAPS-11 | Code fixed | Flood record dialog gets focus after loading, traps Tab/Shift+Tab, closes on Escape, and restores the trigger's focus. |
| MAPS-12 | Placeholder removed | Removed the inactive notification bell and permanent red dot. A notification inbox has not been invented. |
| MAPS-13 | Code fixed | Correct UTF-8 arrow and dash on Public Advisories. |
| MAPS-14 | Code fixed | Shared header falls back to the module's actual page title. Explicit page-title overrides remain. |
| MAPS-15 | Code fixed | Public forecast displays the existing snapshot's Manila fetch time and a warning when the service marks it stale. Fetching behavior is unchanged. |

Logout now clears recent-online tracking. Other audit observations remain separate: geometrically suspicious barangay boundaries require authoritative GIS data; the unsuccessful Fire Responder login needs an account check; historical backup configuration/errors require hosting inspection; dataset provenance, all-A predictions and enrichment eligibility are outside this non-prediction repair.

## Verification

- Production frontend build passed; Laravel Blade view compilation passed.
- Public incident report CI suite: 36 tests passed, 218 assertions.
- Additional audit, dashboard, user, authorization, activity export and backup checks: 27 tests passed, 119 assertions.
- Full suite: 67 passed out of 73, 344 assertions. The six DailyWeatherSnapshotTest failures also reproduced on the unchanged main commit 3787227219ca1a5a4edd258e2129f9d5af89a130 (five weather-data errors, one refresh-command assertion). This patch does not change the weather service or those tests.
- Regression checks cover private attachment visibility, retained storage disk after configuration changes, role-based photo access, missing legacy files, Manila fire time display and create/edit round trip across midnight, GIS timestamp offset, and logout online status.
- Prediction-related source, API routes and repository datasets were checked against the base commit and are unchanged.

Code-level tests were run against an isolated SQLite database with test notification transports. These results are not a claim that the new code has already been deployed or retested in the live browser. Keyboard/marker interaction should receive a browser smoke check after deployment.

## Deployment and storage

The deployment must run `php artisan migrate --force` for the nullable attachment disk column, build frontend assets, and clear/rebuild Laravel config/view caches. The existing container startup already runs migrations.

On an ephemeral host such as Render, configure private persistent storage before accepting more photo uploads:

- Persistent local mount: keep INCIDENT_REPORT_FILESYSTEM_DISK=local and set PRIVATE_FILESYSTEM_ROOT to an existing writable persistent directory. Copy existing private attachments into the new root before switching it; do not discard the original storage.
- Private S3-compatible bucket: set INCIDENT_REPORT_FILESYSTEM_DISK=s3 and configure the existing AWS_* variables. Existing attachments remain associated with their original local disk; migrate them separately with verification before changing any attachment metadata.

The patch requests private visibility and serves attachment bytes only through the authorized staff route. Keep the bucket private; do not expose report images through public bucket URLs. Recover the original missing photo from a retained disk, backup, or uploader if available.

Existing fire timestamps created under the older inconsistent input behavior may be ambiguous. Review provenance before any historical correction; do not bulk shift all incidents by eight hours.

After deployment, verify the Flood shortcut/filter, rainfall value, Manila times and an unchanged fire edit, photo rendering and missing-photo message, forecast states/fetch time, SMS labels, marker names, dialog focus/Escape/return, and logout online status. Do not run a live prediction or change training records as part of this smoke check.
