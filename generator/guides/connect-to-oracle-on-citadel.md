# Connect to Oracle on Citadel

This page is for you if your app needs to read from an Oracle database and you have never set that up before. It names
the settings, shows where to click, and explains each message you may see. It never asks you to paste a password into
a file in git.

## What you need before you start

- The name of the Oracle service your app should use (the owner of the data tells you which one, and whether you need
  one for testing and another for production).
- A database username and password for that service, from the data owner.
- Your app deployed on Citadel (pre-production first), or the WSU Sail or Docker image with Oracle for local work.

You do not need to install anything for Oracle on Citadel. Every Citadel PHP image already carries Oracle's client and
PHP's `oci8` driver. Do not commit Oracle Instant Client files to your repository; they are large, and Citadel
already has them.

## Step 1: let your app reach Oracle (one click, once per environment)

Citadel keeps Oracle behind a firewall. Each app asks for access.

1. Open your app's page in the Citadel dashboard.
2. Under Services Available, tick the Oracle service for this environment.
3. Click Request Access Changes.
4. Wait a minute or two. Some services (the production one) need an approval first, and the dashboard shows the
   request as pending until it is approved.

Pre-production and production are separate apps, so do this in each. Until you do, connecting fails or waits.

## Step 2: fill in the settings

Settings go in your app's environment settings (the `.env` configuration file on Citadel), never in code and never
in a committed file. The names are the same in every WSU app that follows the stack:

| Setting | What it is |
|---|---|
| `ORACLE_USERNAME` | The database username. |
| `ORACLE_PASSWORD` | The database password. |
| `ORACLE_TNS` | The whole connection in one setting: `host:port/service`, or a full descriptor. Use this one or the three below, not both. |
| `ORACLE_HOST` | The database host, when you do not use `ORACLE_TNS`. |
| `ORACLE_PORT` | The port, when you do not use `ORACLE_TNS`. |
| `ORACLE_SERVICE_NAME` | The service name, when you do not use `ORACLE_TNS`. |

Pre-production and production each get their own values. Never switch between them by editing code.

An app made with Sourdough or WELD already has Oracle wired up and asleep: it does nothing until these settings are
filled in, and then it connects. A raw PHP app can use the bound-variables helper in the generated project. Always pass
values to queries as bound variables, never by joining them into the SQL text.

## Step 3: check the connection

On Citadel, a deploy that uses Oracle prints a short check in the build log. Locally, run `squire doctor oracle`. The check never changes anything, never prints a
setting's value, and never stops a deploy: it only tells you what to fix.

## What each message means

| Message starts with | What it means | What to do |
|---|---|---|
| `not configured` | No `ORACLE_*` settings are set, so the check skipped itself. | Nothing, if your app does not use Oracle. Otherwise fill in the settings. |
| `<NAME> is empty` | A required setting is missing or blank. The message names it. | Set it in the app's environment settings. |
| `the host ... does not resolve` | The computer name in your settings is spelled wrong, or you are not on the campus network or VPN. | Fix the spelling. Locally, connect to the VPN. |
| `Oracle did not answer ... within 3 seconds` | The address is right, but the door is closed. On Citadel this almost always means Step 1 has not been done or is still pending. | Tick the Oracle service under Services Available and click Request Access Changes. Locally, check the VPN. |
| `this PHP has no Oracle driver (oci8)` | The settings are filled in, but this PHP cannot talk to Oracle. | Deploy to Citadel, which has the driver, or use the WSU Sail or Docker image with Oracle. |
| `connected to ...` | It works. The message names the database it reached. | Check the name is the one you meant: pre-production should never say production. |
| `ORA-12154` | Oracle could not understand the connect name in `ORACLE_TNS`. | Use `host:port/service` or a full descriptor. |
| `ORA-12514` | The listener answered, but that service is not available. | Check `ORACLE_SERVICE_NAME`. The database may be in maintenance. |
| `ORA-12541` | Nothing is listening at that address. | Check the host and port. The database may be down. |
| `ORA-01017` | The username or password was refused. | Check `ORACLE_USERNAME` and `ORACLE_PASSWORD`. |
| `ORA-01031` or `ORA-00942` | The login worked, but this account may not see that table or view. | Ask the data owner for the grant. |
| Any other `ORA-` code | Oracle returned something the check does not explain. | Search the code, and recheck the settings. |

## Local work

If you run your app on your own computer, use the WSU Oracle-ready image: a Laravel Sail flavour that swaps in for
Sail's `build:` block, and a plain Docker flavour that matches Citadel's PHP. They already have the Oracle client and
`oci8`, so nobody compiles anything. Squire documents the image path. You still need the campus network or VPN to
reach the database, and the same access request from Step 1 applies to your Citadel copy of the app.

## Rules that keep you safe

- Credentials live in the environment settings, never in git.
- Tests use fakes or made-up data, never a real Oracle database full of people's records.
- Never log a password or paste one into a ticket. If one leaks, tell the data owner so it can be changed.
- One app at a time: asking for access for your app changes nothing for any other app.
