# Every OS

- Every developer tool works on Windows, macOS and Linux (ADR-0009).
- A developer script ships as a `.ps1` and `.sh` pair that do the same thing,
  or as one PHP command, which works the same everywhere (ADR-0009).
- Windows gets PowerShell (`.ps1`), which never needs bash or WSL. Linux and
  macOS get bash (`.sh`), written for macOS's stock bash 3.2 and its BSD tools
  (ADR-0009).
- Every repo carries a `.gitattributes` with `* text=auto eol=lf`, and
  `.ps1`, `.bat` and `.cmd` set to CRLF. Anything that runs on a Linux host
  must be LF: on Citadel, and on OpenShift, Coolify or raw Docker elsewhere
  (ADR-0009).
- Paths are built with the language's path functions, and subprocesses start
  with argument arrays, never shell strings (ADR-0009).
