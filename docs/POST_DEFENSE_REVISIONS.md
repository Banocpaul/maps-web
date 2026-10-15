# M.A.P.S. post-defense system revisions

## Implemented

- One login page and role-based dashboard access were already present. Suspended residents are now blocked from login and existing sessions.
- Community verification defaults to five distinct residents reporting the same hazard and normalized street within the existing configurable radius and time window (100 metres and 120 minutes by default). Street-less reports still go to staff review. Public reports remain separate evidence records; automatic validation does not invent official fire details or flood severity.
- Community SMS alerts go to eligible public subscribers in the barangay containing the incident pin. Notifications identify community verification and pending official details. Each group/recipient alert is recorded once in SMS logs; failed delivery remains visible.
- Staff explicitly distinguish confirmed fraudulent reports from ordinary rejection. Three confirmed fraudulent reports suspend the resident and deactivate their SMS subscription. Duplicates and unconfirmed incidents do not automatically count as fraud. Administrators can reinstate a resident from Edit User with a recorded reason. Fraud history is retained.
- Residence type is editable in resident profiles and staff user review: Homeowner, Tenant, Boarder, Living with family, Other. Existing accounts may leave it unrecorded. Confirm these categories against the panel's intended meaning of residence type.
- Fire verification accepts a BFP alarm level and source/reference. Official incident publication retains those details. Fire situation updates record time, status, alarm, source, author and notes. They update the live response status and send event-specific SMS alerts. Fire-out updates move records to assessment. Existing edit and fire-out actions also append timeline entries.
- Injured and deceased counts are separate nullable fields. Blank means unknown, while zero means no casualties reported. Counts are accepted during public report validation and official incident processing, fire assessment and flood record forms.
- Operational Records provides Print / Save as PDF and Archive filtered report for flood and fire datasets. Archived reports preserve immutable data snapshots, filters, generation time and preparer. Archived Reports lists saved reports for authorized personnel. Print/archive requests are limited to 10,000 matching records; use filters for larger datasets.
- The fire GIS shows historical barangay incident counts to support mitigation priorities. These are historical patterns, not estimated future fire probabilities. Existing flood classification and prediction remain unchanged.
- Existing cross-module audit coverage includes new model and request actions. Insert audit writes are deferred until business IDs have been retrieved, fixing a generated-ID corruption bug. Rolled-back pending audit inserts are discarded.

## Deployment

Run `php artisan migrate --force` before serving the changed code, then clear/rebuild application and view caches. The migration changes the shared verification threshold to five. No production database, SMS gateway or live deployment was used during development.

SMS delivery uses the existing gateway and recipient opt-in settings. Check SMS logs after deployment. Failed community alerts are retained as failed and are not automatically resent on each new duplicate public report.

## Document-only revisions

Chapter updates require the current manuscript and survey tables: actual preprocessing steps, purposive sampling justification, respondent types, and the fire-mitigation/flood-prediction limitation. This change does not modify the manuscript or add a fire prediction model.


## Incident archives (three-year rule)

Operational Records → Incident archives is separate from generated report snapshots. Select Fire incidents, Flood incident records, or Flood field observations and choose Eligible to archive. Only Resolved fires and Subsided floods whose incident date is strictly before the Philippine calendar date three years ago qualify. No records are deleted. The owner-authorized one-time pass runs during deployment, followed by monthly automatic archiving.

Authorized personnel with records.view and the relevant fire.edit/flood.edit permission can archive or restore records, individually or in explicit batches of up to 100 IDs. Each action requires a reason and is audited. A mixed eligible/ineligible batch changes nothing. Archived incidents leave current operational lists, remain searchable and printable through Incident archives, and remain in historical analytics and existing training selections. Operational Records offers Current, Archived only and All filters for viewing, printing and CSV exports.

Deploy the new migration before serving this version (`php artisan migrate --force`). Existing records start unarchived. Restore records before modifying their operational details.


### Initial and monthly automation

`php artisan incidents:archive --initial` performs the owner-authorized initial archive of every eligible resolved fire and subsided flood (including field observations). The Docker startup runs it after migrations and fire data import. A durable database ledger makes this a one-time operation across restarts and redeployments. It also records the current month as complete.

`php artisan incidents:archive --monthly` performs at most one pass per Philippine calendar month. Startup and request termination provide catch-up checks when free hosting wakes; the existing daily public weather trigger also wakes the application. An hourly Laravel scheduled due check is registered for installations running the scheduler. On sleeping hosting, work runs at the first available startup/request of the month rather than a guaranteed exact minute. No additional paid service or secret is required.

Records are archived in bounded database batches, with exact affected IDs in the audit details and per-type totals in the ledger. One transaction covers each run, so a failed run rolls back both records and completion state and may retry. Manual restoration remains available; the system will not rearchive restored records again in the same completed month. They can become eligible again at the next month's pass.

The archive page displays initial completion, the last completed month and totals. Archiving changes metadata only; analytics, predictor values and approved training flags are retained. The one-time command is explicit and idempotent; it is not run by a schema migration or during a build.
