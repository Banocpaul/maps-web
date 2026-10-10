# Profile and login changes

Click your name or picture in the staff navbar, or My Profile in the public
account header, to open the centered profile popup. It shows your name, role,
assigned barangay (or Not assigned), and picture. Close with ×, Escape, or the
backdrop. A standalone profile page remains available at /account/password.

First and last names and profile pictures save immediately. They do not change
roles, barangay assignments, usernames, or passwords. Public recipient names
stay synchronized. JPEG, PNG, and WebP pictures up to 2 MB are stored in a
separate database table so they survive deployments; only the signed-in owner
can retrieve their picture. Image contents are excluded from activity logs.

Username means the email used to sign in. All roles, including residents and
administrators, can request a new username email, a new password, or both after
confirming their current password. One request may be pending per account.
The current login remains active until approval.

Administrators open User Management → Account Change Requests to approve or
reject. Requested emails are visible; submitted passwords are hashed immediately
and neither passwords nor hashes appear in review screens or activity logs.
Approval applies both requested changes atomically, clears the pending password
hash, and invalidates prior sessions and remember tokens. Rejection leaves the
current login unchanged and permits another request. Email uniqueness is checked
at submission and approval. An intervening admin email edit prevents a stale
request from overwriting the newer username.

Admin Reset Password generates a temporary password to share securely. It
cancels pending login requests and invalidates existing sessions. The next login
requires a different new password before other pages or actions can be used.
Saving it activates it immediately without approval. This requirement persists
across logout and login until completed. Admin self-reset opens that screen.

Migrations preserve existing passwords and pending password-only requests.
Existing accounts do not require a password reset unless the admin resets them.
