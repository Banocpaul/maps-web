# Fire incident records

`database/data/fire-incident-records.json` is a lossless value extraction of **Fire Records(1).xlsx**, including its data dictionary and original workbook SHA-256. It contains 37 transcribed source records (`FIR-0001`–`FIR-0037`) and 80 modeled examples (`FIR-EX-0001`–`FIR-EX-0080`). The transcribed table has not been independently validated; modeled rows are not actual incidents.

## Deployment and import

Docker startup runs `php artisan migrate --force` and then `php artisan fire:import`. The importer uses a transaction, validates all input rows before writing, creates only missing canonical barangays, and uses unique incident numbers to preserve existing records, staff edits, and soft deletions across restarts. It makes no weather, SMS, or other network requests. Tests explicitly invoke the importer; migrations only create schema.

The first activation of `fire-records-117-v1` makes all 117 uploaded rows the current project dataset, including the 80 modeled rows. All previous fire incidents outside these uploaded IDs become **Superseded**, available through Previous dataset and excluded from current totals and active maps. Activation is claimed once in the same transaction as the import. Later restarts preserve new staff reports and do not repeat the replacement. Incomplete source files cannot activate a replacement. The old destructive `--replace` option is removed. `fire:import <path>` accepts this normalized JSON structure; it does not read arbitrary XLSX/legacy CSV files.

## Interpretation

- All uploaded rows have fire-out times and are imported as Resolved, with no response time invented. Missing report time is stored using occurrence as a database fallback and explicitly labeled unavailable in the record detail.
- Manila wall times are converted to UTC for storage and back to PHT for display, filtering, calendar year/month, and time-of-day analytics. Earlier fire-out clock times are interpreted as the next day, as instructed by the workbook dictionary. Duration is calculated from the timestamps; the original duration remains in the source row.
- Individuals affected and houses destroyed remain exact source counts. Unknown values in new reports may be left blank rather than entered as zero.
- Only `Alarm (reported)` becomes the reported alarm. `Alarm` and `Cause ` remain **unconfirmed reference** values. Confirmed cause, incident type, and severity are unspecified when absent from the source.
- Coordinates are approximate barangay reference points. History GIS uses amber circles and labels approximate coordinates; no nearest-hydrant incident button is provided for these points. Active and public maps do not display these closed historical records. Historical records and modeled examples never trigger fire alerts.
- Accent/dash and Hagdan/Hagdang variants resolve to existing canonical barangays. `Zañiga (unspecified)` keeps a null barangay assignment and its source name; it is not guessed as New or Old Zañiga, nor plotted without coordinates.
- FireIncident's default model scope includes Dataset rows and new Reported incidents. Lists and CSV exports offer Current fire records and Previous dataset filters. All 117 uploaded rows contribute to the current totals and analytics. `source_origin` and original source metadata retain Transcribed/Modeled provenance; promoting a modeled row into the selected project dataset does not certify it as an independently verified incident. Closed records remain read-only.

## Staff workflow

Create, edit, and verified public-report publication forms capture time occurred, fire out, impact counts, reported alarm, and confirmed cause. New pins are staff-verified. Fire out requires Resolved status, cannot precede occurrence/response, and synchronizes resolved time and duration. Resolved records stay locked. Legacy clients may omit occurrence (falls back to report time) and use `resolved_at` as the fire-out timestamp.

Operational Records retains exactly the seven existing sections. Its Fire Incidents section searches and exports the uploaded fields, provenance, reported alarm, unconfirmed references, and PHT timestamps. Incident Analytics calculates current-dataset fire totals/impact/duration plus existing monthly, severity, barangay, and time-of-day charts; absent severity is Unspecified.
