# Public resident accounts

Public maps, weather, advisories, and the portal remain accessible without authentication.
Residents create an account at `/public-portal/register`, then sign in at `/public-portal/login`.
Registration collects name, email, password, Philippine mobile number, and an active barangay.
Flood and fire SMS subscriptions are separate, optional checkboxes, off by default.

The public role receives no staff permissions. Registration ignores submitted role IDs,
approval flags, and account status. Residents manage their own profile, alert preferences,
password, and report history; staff continue to manage accounts through User Management.
Inactive accounts or roles cannot report or receive automatic alerts.

Each resident has one linked SMS recipient, with a unique normalized phone number.
Changing barangay or mobile number updates that recipient; opting out disables the relevant
alert type. Existing staff SMS recipients are preserved and cannot be claimed by registration.
Resident subscriptions cannot be changed through staff recipient endpoints or included in
untyped manual broadcasts.

Fire messages use the existing automatic incident sender. Active flood observations now
send barangay-specific flood messages when added by staff through Flood Operations or when
a validated public flood report is published. Pending or rejected reports do not trigger SMS.
Each incident/recipient alert is sent once, with gateway successes and failures recorded in
SMS logs. These alerts concern recorded incidents; forecast and simulation calculations remain
unchanged. SMS requires the existing configured Android gateway.

Public reports require an active resident account. Reports store the submitting user and a
snapshot of the reporter's barangay, with the submission event attributed to that account.
The reporter barangay describes the resident, not the incident pin; staff confirm the incident
barangay during publication. Reusing a form token is idempotent for its owner and rejected for
other accounts. Photos remain private staff attachments. My Reports displays only the current
resident's submissions, current status, and rejection reason; it does not expose internal staff notes.

## Review data for future analytics

The existing statuses and audit history remain authoritative:

- Pending: awaiting review.
- Validated: accepted by staff, awaiting publication details.
- Published: accepted and linked to an official incident.
- Rejected: declined with reviewer, review time, and reason.

Future acceptance/rejection charts should count each report's current status once; a report
validated and then published is one accepted report, and a validated report later rejected
belongs in the rejected count. Pending reports should be shown separately and the chart's
percentage denominator explicitly stated. Historical anonymous reports retain their references,
review history, photos, and incident links with null submitter ownership.

## Deployment and verification

Run `php artisan migrate --force` before serving the new code. The Render Docker startup already
runs this command. The migration adds nullable relationships and default-false subscription fields
without replacing existing records. Rebuild frontend assets with `npm ci && npm run build`.

Focused verification: `php vendor/phpunit/phpunit/phpunit -c phpunit-public-reports.xml`.
Also compile views with `php artisan view:cache` and check existing permission/workflow tests.
No real SMS messages are sent by the automated tests.
