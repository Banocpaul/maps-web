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
