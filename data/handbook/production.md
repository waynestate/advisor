# Production

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## Environments

- Every environment more than one person can reach follows the whole handbook; only a developer's own machine is exempt (WSU developer standards, 2024).
- Keep at least a pre-production and a production environment, each with its own working copy of every piece, database and supporting services included (WSU developer standards, 2024).
- Each deployment SHOULD be isolated from the others (one container per app), so one compromise does not become many. This holds on Citadel, OpenShift, Coolify and plain Docker (WSU developer standards, 2024).
- Decide early whether the deployment is in disaster-recovery scope, and write the decision in the README (WSU developer standards, 2024).
- Back up anything kept outside source control, databases included. Say in the README how to restore one app (WSU developer standards, 2024).
- An app SHOULD be load tested in each environment where heavy use is expected (WSU developer standards, 2024).

## Running safely

- The app and its scripts MUST run as a non-root, non-administrator user with the least access they need (WSU developer standards, 2024).
- The web server MUST NOT list directory contents (WSU developer standards, 2024).
- File permissions MUST be set per path; write access for the web server is granted only to specific files or folders, and mode `777` MUST NOT be used (WSU developer standards, 2024).
  A PROCTOR check for this is proposed, not built: it flags mode `777` and web-writable paths outside the allowed list.
- Debugging and diagnostic files, scripts, dumps and backups MUST NOT sit in the served folder (WSU developer standards, 2024).
  A PROCTOR check for this is proposed, not built: a scan of the served folder for `phpinfo.php`, dumps, backups, `.env` and `.git`.
- On failure the app degrades predictably and fails closed: an authentication error withholds access (WSU developer standards, 2024).
- A person is told when something fails: a monitor calls `/up` on a schedule and alerts someone (WSU developer standards, 2024).
- Logs are rotated or removed at a set interval; temporary files are cleaned up; uploaded files have a retention rule. Citadel rotates container logs; elsewhere the platform or the app MUST (WSU developer standards, 2024).
- Where it is practical, an app SHOULD record an audit trail of changes to data and of successful and failed access to sensitive data (WSU developer standards, 2024).

Production is the encore. Keep the lights on and the doors locked, and the band plays on.

## Who can reach it

- Every app MUST set `robots.txt` to keep spiders out unless crawling is wanted (WSU developer standards, 2024).
- Every app MUST limit access by network address to the audience it serves, as a second lock beside sign-in and never instead of it (WSU developer standards, 2024).
- The one named exception: the stack's own public front pages are crawlable by design (the stack's agent-ready sites decision). It is a special case for those pages, not a general exemption for public pages.
