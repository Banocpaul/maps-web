# Flood incident reporting

Operational Records → Flood Incident Records → Add flood record, or Flood GIS Map → Plot flood.

Staff select an active barangay, select A–D, and draw a LineString along the flooded stretch. The server generates the event ID, captures the current Philippine date/time, computes the line midpoint and length, and starts the incident as Active. Client dates, IDs, statuses, and manual predictor values cannot replace these values.

Barangay geography and profile values are filled from the selected barangay. Weather comes from the existing single daily Open-Meteo snapshot, including historical rainfall accumulation, temperature, humidity, wind speed converted from m/s to km/h, and wind direction. Elapsed temperature extrema exclude future hourly forecasts. Older cached snapshots without these extrema leave them unavailable until a suitable snapshot exists. Weather capture time is displayed; values are the daily snapshot, not a new weather measurement at every report submission. PAGASA storm signals are unavailable from Open-Meteo and remain null.

A missing weather feed or incomplete profile keeps the report and plotted line. Missing values display as Unavailable; the interface has no enrichment status, training-readiness badge, or manual retry action. Stale weather from another date is never assigned to today's report. Automatic data refreshes for older incidents only consult their original day's stored snapshot.

Authorized staff can raise the code to a higher A–D value while Active. Mark subsided records the current Philippine end time and computes duration, including incidents spanning midnight. The original report/start dates remain intact. Subsided incidents cannot be reopened, edited, or escalated. Row locks serialize edit, raise, and subsidence actions. Code/status transitions record their actor, previous/new code, and timestamp in flood_incident_updates.

Active lines appear on internal flood GIS, the public hazard map, and staff dashboard follow-up tasks. Subsidence or deletion removes them from active maps while retaining historical incident records. Imported spreadsheet records and their dates are preserved. Existing prediction and training-model behavior remains unchanged.

Deployment runs the 2026_10_10_120000 migration to add geometry, automatic-data metadata, and transition history. Flood analysts return to the internal flood map without needing access to the full Operational Records browser.
