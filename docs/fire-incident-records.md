# Fire incident records

`database/data/fire-incident-records.json` is a lossless value extraction of **Fire Records(1).xlsx**, including its data dictionary and original workbook SHA-256. It contains 37 transcribed source records (`FIR-0001`–`FIR-0037`) and 80 modeled examples (`FIR-EX-0001`–`FIR-EX-0080`). The transcribed table has not been independently validated; modeled rows are not actual incidents.

## Deployment and import

Docker startup runs `php artisan migrate --force` and then `php artisan fire:import`. The importer uses a transaction, validates all input rows before writing, creates only missing canonical barangays, and uses unique incident numbers to preserve existing records, staff edits, and soft deletions across restarts. It makes no weather, SMS, or other network requests. Tests explicitly invoke the importer; migrations only create schema.

Previous rows marked `Historical FireData.xlsx` are retained as **Superseded**, available through the Previous dataset filter, and excluded from current totals. Other operational incidents are preserved and continue to count. The old destructive `--replace` option is removed. `fire:import <path>` accepts this normalized JSON structure; it does not read arbitrary XLSX/legacy CSV files.

## Interpretation

- All uploaded rows have fire-out times and are imported as Resolved, with no response time invented. Missing report time is stored using occurrence as a database fallback and explicitly labeled unavailable in the record detail.
- Manila wall times are converted to UTC for storage and back to PHT for display, filtering, calendar year/month, and time-of-day analytics. Earlier fire-out clock times are interpreted as the next day, as instructed by the workbook dictionary. Duration is calculated from the timestamps; the original duration remains in the source row.
- Individuals affected and houses destroyed remain exact source counts. Unknown values in new reports may be left blank rather than entered as zero.
- Only `Alarm (reported)` becomes the reported alarm. `Alarm` and `Cause ` remain **unconfirmed reference** values. Confirmed cause, incident type, and severity are unspecified when absent from the source.
- Coordinates are approximate barangay reference points. History GIS uses amber circles and labels approximate coordinates; no nearest-hydrant incident button is provided for these points. Active and public maps do not display these closed historical records. Historical records and modeled examples never trigger fire alerts.
- Accent/dash and Hagdan/Hagdang variants resolve to existing canonical barangays. `Zañiga (unspecified)` keeps a null barangay assignment and its source name; it is not guessed as New or Old Zañiga, nor plotted without coordinates.
- FireIncident's default model scope includes Reported records only. Lists and CSV exports offer separate Reported, Example, and Superseded filters. Analytics, dashboard queues, counts, and barangay relations exclude examples and superseded rows by default. Source values remain accessible on authenticated fire detail pages; examples are read-only.

## Staff workflow

Create, edit, and verified public-report publication forms capture time occurred, fire out, impact counts, reported alarm, and confirmed cause. New pins are staff-verified. Fire out requires Resolved status, cannot precede occurrence/response, and synchronizes resolved time and duration. Resolved records stay locked. Legacy clients may omit occurrence (falls back to report time) and use `resolved_at` as the fire-out timestamp.

Operational Records retains exactly the seven existing sections. Its Fire Incidents section searches and exports the uploaded fields, provenance, reported alarm, unconfirmed references, and PHT timestamps. Incident Analytics calculates source-only fire totals/impact/duration plus existing monthly, severity, barangay, and time-of-day charts; absent severity is Unspecified.
