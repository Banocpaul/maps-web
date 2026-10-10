# Prediction history and staff review

Open **Prediction History** in the sidebar, or use the link on Flood Prediction.
Each new 24-, 48-, or 72-hour forecast request creates one citywide history record.
It records the requesting staff member, the actual execution time, selected horizon,
the input and weather snapshot used for inference, and the complete returned response.
The saved result includes every barangay, A–D severity, confidence/probabilities,
forecast summary and any model metadata returned by the existing API.
The barangay results display confidence as the saved model probability of the
reported A–D code, preserving all decimal digits returned by the API without
display rounding (for example, 0.9996 displays as 99.96%, and only 1 displays as 100%). Missing or invalid
confidence is shown as Unavailable; combined legacy risk probabilities are not
used to invent per-code confidence.

Successful forecasts redirect to their saved result. Opening or refreshing this
page reads the stored snapshot and does not call ML or weather services again.
Later forecasts create separate runs and do not replace earlier results.

History supports search by run number or saved staff name, plus filtering by window, run type and status. A valid submitted request
starts as Running; successful results become Completed and errors become Failed.
Invalid forms are not prediction executions. If a worker terminates unexpectedly,
a Running record can remain without a completion timestamp; no result is fabricated.

Rainfall simulations are also saved, labeled Simulation, with their rainfall inputs.
The simulation screen links to its saved run. Simulation and forecast records are
not observed flood incidents and do not automatically publish incidents or send SMS.

Reading requires the existing `prediction.view` permission. Adding remarks also
requires `prediction.review`, assigned to Flood Analysts and Operations Officers by
the migration and role seeder. Administrators retain their existing permission bypass.
Read-only users cannot add remarks. Public residents have no access.

Remarks apply to the whole run or to a barangay present in that run's saved results.
They are appended with the author's name and timestamp. No result editing,
remark editing or deletion endpoints are provided. Name snapshots and history
remain when a staff account is removed; user foreign keys become null.
Displayed timestamps are in Asia/Manila and stored timestamps use the app's UTC timezone.

The existing per-barangay prediction storage is retained. If its legacy schema or
response conversion fails, the complete history snapshot remains saved and the
failure is logged. Existing old database records are preserved, but missing
historical A–D responses cannot be reconstructed: this complete history starts with
new runs after deployment.

## Deployment

Run `php artisan migrate --force` to add `prediction_executions`, `prediction_remarks`
and the review permission, then rebuild assets and refresh cached views/routes using
the deployment's existing workflow. No ML model, endpoint, predictor normalization,
forecast selection or weather-fetch schedule is changed.

## Verification

`PredictionHistoryTest` covers each horizon, distinct saved runs, exact response and
weather retention, legacy storage errors, ML/weather errors, simulation inputs,
review without inference, permissions, escaped remarks, append-only review,
filtering and preservation after staff account deletion. Tests use mocked external
services and an isolated SQLite database.
