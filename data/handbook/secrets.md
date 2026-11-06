# Secrets and debugging

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

## Credentials

- Credentials (database and service passwords, API keys, tokens) MUST NOT be committed to version control, because history keeps them forever. Commit `.env.example` with placeholders instead. A credential that was ever committed is treated as exposed and rotated (WSU developer standards, 2024).
- Credentials and sensitive data MUST live outside any folder the web server serves. Laravel's `public/` is the only served folder; raw PHP apps serve one `public/` folder the same way (WSU developer standards, 2024).
- An app uses its own dedicated application ID, never a person's employee or student AccessID, to reach another server or service, with the fewest permissions the task needs (WSU developer standards, 2024).
- Real values come from the environment at deploy time (the Citadel dashboard or CI variables), never from a file in the repo (WSU developer standards, 2024).

## Errors and debug output

- `APP_DEBUG` MUST be `false` in production (WSU developer standards, 2024).
- Public-facing code MUST NOT display default interpreter or framework error pages to the user; show a controlled message and log the detail (WSU developer standards, 2024).
- Logs and diagnostic output MUST NOT contain credentials or personally identifiable information such as Social Security numbers. That includes logged exceptions (WSU developer standards, 2024).

A leaked password is a bad encore: everyone remembers it, and nobody asked for a second showing.

## Not decided here

- The HELMET header rules and runtime refusals are written in `runtime-and-headers.md`.
