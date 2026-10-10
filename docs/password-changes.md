# Password changes

All roles, including public residents and administrators, can open My Password by
clicking their profile in the staff navbar or Request a password change in the
public account page. They confirm their current password and submit a different
password of at least eight characters. One request may be pending per account.
The current password remains active until approval.

Administrators open User Management → Password Requests to approve or reject.
Only a password hash is stored while pending; no submitted password or hash is
displayed to admins or included in activity logs. Approval activates the hash,
clears it from the request, and invalidates prior sessions and remember tokens.
Rejection leaves the current password unchanged and permits another request.

Admin Reset Password generates a temporary password to share securely with the
account holder. It cancels pending requests and invalidates existing sessions.
The next login requires a different new password before any other page or action
can be used. Saving it activates it immediately without approval. This requirement
persists across logout and login until completed. Resetting your own administrator
account takes you directly to this required change screen.

The migration adds default-off reset flags and version counters, preserving
existing account passwords. Existing sessions remain valid until a password
change, reset, deactivation, or role deactivation invalidates access.
