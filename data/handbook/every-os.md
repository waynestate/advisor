# Every OS

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY in this chapter are to be interpreted as described in RFC 2119.

- Every developer tool MUST work on Windows, macOS and Linux (the stack's every-OS design). No check exists yet.
- A developer script MUST ship as a `.ps1` and `.sh` pair that do the same
  thing, or as one PHP command, which works the same everywhere (the stack's
  every-OS design). PROCTOR check: `script-pair`.
- A script for Windows MUST be native PowerShell (`.ps1`), which never needs
  bash or WSL, and a script for Linux or macOS MUST be bash (`.sh`), written
  for macOS's stock bash 3.2 and its BSD tools (the stack's every-OS design).
  No check exists yet.
- Every repo MUST carry a `.gitattributes` with `* text=auto eol=lf`, and
  `.ps1`, `.bat` and `.cmd` set to CRLF. Anything that runs on a Linux host
  MUST be LF: on Citadel, and on OpenShift, Coolify or raw Docker elsewhere
  (the stack's every-OS design). PROCTOR checks: `crlf-deploy-script`, for
  deploy scripts only: it fails on a CRLF deploy script and warns when
  `.gitattributes` does not pin `*.sh` to LF; and `gitattributes-eol` (since
  PROCTOR 0.11.0), which warns when `.gitattributes` does
  not pin the repo's `*.sh`, `*.conf`, `Dockerfile` and `*.Dockerfile` files
  to `eol=lf`, and on a CRLF Dockerfile or `.conf` under `deploy/` or
  `.squire/`.
- Two paths in a repo MUST NOT differ only by letter case (`Docs/Guide.md`
  and `docs/guide.md`): Windows and macOS ignore case, so one checkout
  silently overwrites the other, and a Linux host then loads a file nobody
  edited (BEST_PRACTICES practice 34). PROCTOR check: `case-clash`, since
  PROCTOR 0.9.0.
- Paths MUST be built with the language's path functions, and subprocesses
  MUST start with argument arrays, never shell strings (the stack's every-OS
  design). PROCTOR check: `portability`.
