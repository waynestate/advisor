# Parity

**Run Citadel on your own computer.** Citadel's PHP image, Citadel's proxy and MySQL 5.6, on your laptop, so an app
that works here works on Citadel. "Works on my machine" finally means something, because your machine is now Citadel's
twin.

Parity is one program, `parity`, for Windows, macOS and Linux (x64 and arm64). It needs a container engine, Podman
Desktop (recommended, free) or Docker Desktop if you already have it, and nothing else: no PHP, no Composer, no files
in your app beyond one `parity.json`. `parity check` also knows the other engines people already run (see
[Docker or Podman](#docker-or-podman)).

> Parity is a mirror, not a portrait: it shows Citadel as Citadel is, including the parts you would rather it did not.

## Quick start

In your app's folder:

```text
parity check        tells you what to fix, one finding per line, with the fix under it
parity init         writes parity.json (your app's Citadel settings)
parity image get    downloads Citadel's image and checks it is Citadel's, byte for byte
parity up           builds and starts the app behind the proxy, then prints its address
parity test         smoke tests the running app through the proxy
parity down         stops it (your data stays)
```

Parity runs Citadel's own image and nothing else, because the whole point is to match Citadel. If Citadel has not
published a download for your app's image yet, Parity says so and tells you what to do next. It will not hand you a
look-alike and call it a twin; Citadel would never fall for that, and neither should you.

Rather click than type? The desktop app comes after 1.0. It is not released yet: it is one window over the same Go code
these commands run, and the [front page](https://parity.apps.wayne.edu/#commands) shows its screens next to each
command's printout ([the desktop app guide](https://parity.apps.wayne.edu/docs/guides/desktop-app.html) has more).

## Install

| OS | Install |
|---|---|
| Windows (PowerShell) | `irm https://parity.apps.wayne.edu/install.ps1 \| iex` |
| macOS, Linux | `curl -fsSL https://parity.apps.wayne.edu/install.sh \| sh` |
| Any | a download per OS and arch from `https://parity.apps.wayne.edu/downloads/`, with `checksums.txt` |

The installers fetch the newest release, check its sha256 against the release's `checksums.txt` before installing
anything, and need no administrator. That check catches a corrupted or partial download. It does not prove the
download is genuine: `checksums.txt` comes from the same site as the archive, so a site serving a bad archive could
serve a matching sum too. Signing comes before the desktop app, which follows 1.0.

- **Windows** puts `parity.exe` in `%LOCALAPPDATA%\Programs\parity` and adds that folder to your own (user) PATH,
  once: a second run updates `parity.exe` and says it is already on your PATH.
- **macOS and Linux** put `parity` in `~/.local/bin`. The script never edits your shell's profile. If `~/.local/bin`
  is not on your PATH it prints the one line to add, then you open a new terminal:

  ```text
  bash or zsh (~/.bashrc or ~/.zshrc):   export PATH="$HOME/.local/bin:$PATH"
  fish (~/.config/fish/config.fish):     fish_add_path "$HOME/.local/bin"
  ```

  A folder you name with `--dir` (or `PARITY_INSTALL_DIR`) wins. If you cannot write it (`/usr/local/bin`, say), sudo
  asks for your password for the copy, and only for the copy.

A version, a folder, or no PATH change (`-NoPath` on Windows; `PARITY_NO_PATH=1` on either, which on macOS and Linux
drops the hint):

```text
& ([scriptblock]::Create((irm https://parity.apps.wayne.edu/install.ps1))) -Version 0.1.0 -InstallDir C:\tools\parity -NoPath
curl -fsSL https://parity.apps.wayne.edu/install.sh | sh -s -- --version 0.1.0 --dir ~/bin
curl -fsSL https://parity.apps.wayne.edu/install.sh | PARITY_NO_PATH=1 sh
```

The files are `parity_<version>_<os>_<arch>.tar.gz` (`.zip` on Windows) for `windows`, `darwin` and `linux`, each
`amd64` and `arm64`, on Parity's site under `downloads/<version>/` (and `downloads/latest/` for the newest). Or build
it from this repository (below), or take a binary from a pipeline's `build` job.

0.1.0 ships unsigned, with checksums. Signed binaries come before the desktop app, which follows 1.0. Until then, a zip
downloaded in a browser may meet SmartScreen ("More info", then "Run anyway", or `Unblock-File parity.exe`), and on a
Mac an archive downloaded in a browser needs `xattr -d com.apple.quarantine parity`. The installers avoid both.

## Docker or Podman

Parity drives either, and recommends **Podman**: it is free (Docker Desktop needs a paid subscription at a university
this size), it runs rootless by default, and Podman Desktop gives Windows and macOS a window to run it from. Docker
Desktop works as well as ever, and so does any engine that answers the `docker` command (Rancher Desktop, OrbStack,
Colima, Docker Engine); Parity just stops suggesting them. Either way the bubble sits in the middle.

| OS | Install Podman |
|---|---|
| Windows | `winget install --id RedHat.Podman-Desktop -e`, then open Podman Desktop and let it set up Podman and its machine (WSL 2) |
| macOS | `brew install --cask podman-desktop`, then the same |
| Linux | your distribution's packages: `sudo dnf install podman docker-compose` (Fedora, RHEL) or `sudo apt-get install podman docker-compose-v2` (Ubuntu), then `systemctl --user enable --now podman.socket` |

- **Which one Parity uses:** the engine that is running. With both running it takes Docker, so an existing Docker
  setup keeps its databases, and `parity check` names the other. `parity engine podman` (or `docker`) saves a
  choice for every app and the desktop app; `PARITY_ENGINE` overrides it for one terminal; `auto` is the default.
- **Compose:** Parity drives Docker Compose v2 on both engines; on Podman, `podman compose` runs it. podman-compose
  (the Python program) is a different tool, and Parity refuses it rather than half-work with it: `parity check` says
  how to get Docker Compose. On Linux, Compose reaches Podman through Podman's API socket, which the `systemctl` line
  above starts. Builds on Podman use the classic builder, because BuildKit's builder container cannot see the images
  Podman holds; Parity sets that for you.
- **Rootless Podman and your files (mount mode):** rootless means the container's root is you, not the computer's
  root. Parity runs the app with `userns_mode: keep-id`, mapped to the image's `citadel` user, so inside the container
  your files belong to `citadel`, as the source owner on Citadel, and the container still starts as root, as on
  Citadel. As it starts, Parity gives the writable paths to Apache's group, group-writable, as Citadel's deploy does,
  so Apache (`prisoner`), the deployments (`citadel`) and the cron entries (`prisoner`) can all write them. Files
  Apache writes show on your side with a high user id from your subuid range; you can still delete them. Parity does
  not use Podman's `:U` mount option, which would hand your folder on your own disk to the container's ids.
- **Health checks:** Docker checks a starting container every 3 seconds (`start_interval`); Podman has no such
  setting and, after a failed first check, waits the full 5 minutes. So on Podman `parity up` asks for a check while
  it waits.
- **Windows:** Podman runs in its own WSL 2 machine. Your folders are mounted into it, so a Windows path works as it
  is, ports on `localhost` reach your browser, and edits show on the next request. A folder on the Windows disk is
  slow in mount mode, because every file read crosses into WSL 2; `parity check` warns about it (`mount-speed`).
  Use bake mode for `parity test`, or keep the app in the WSL filesystem. Inside the container, files on
  the Windows disk show as one owner with every permission (WSL's drvfs), so ownership questions do not come up. If
  `parity check` says the machine is running but Podman in it is not answering, it says what to do: on a PC that
  also runs Ubuntu with systemd in WSL, the two distros' user 1000 can collide (WSL distros share one cgroup tree).
- **Apple Silicon:** Citadel's images are amd64 only. Podman's machine runs them with Rosetta when Rosetta is on (Podman
  5.6 shipped with it off), else on slower QEMU emulation, which works. `parity check` gives the steps to turn Rosetta
  on (macOS 26 or later).

The Podman path is proven end to end on Linux (rootful and rootless, through `podman compose` and through `docker
compose` on Podman's socket) and on Windows (Podman Desktop's rootless machine and Docker Desktop, mount and bake,
on Citadel's real 8.5r3 image, 2026-11-10). macOS is the run still owed on the release checklist
([MAINTAINING.md](MAINTAINING.md), "Checklist runs").

## What Parity runs

| Piece | What it is | Like Citadel because |
|---|---|---|
| app | Citadel's own PHP image (for example `citadel/php-apache:8.5r3`), Citadel's `vhost.conf` and `php.ini`, your `build.sh` run the way Citadel runs it, your `deployments/` run once each by Citadel's entrypoint, your health check on Citadel's timing, your cron entries run in the app container as `prisoner` | It is Citadel's image and Citadel's files, not a lookalike |
| proxy | HAProxy in front, adding `X-Forwarded-For` and `X-Forwarded-Proto` and stripping nothing; with `"https": true` it also serves https for `server_name` (and `localhost`) on 127.0.0.1:8443 | Citadel's proxy does the same, so an app that trusts the wrong hop is caught here |
| mysql | MySQL 5.6.51, on a loopback port only | It is the version of Citadel's shared database server (no JSON columns, indexed strings stop at 191 characters) |

Two ways to put your code in the app container, set by `mode` in `parity.json`:

- **mount** (for developers): your folder is mounted at `/citadel/src`, as Citadel mounts it. Edit, reload.
- **bake** (for testers): your code is copied into the image, so a tester tests one exact commit, and Parity says
  which (with `-modified` when the folder has changes).

## Commands

Exit codes, as PROCTOR and Squire: **0** pass, **1** findings or a failed test, **2** cannot run (usage, no engine,
no config). `--json` on the read commands is the stable contract.

| Command | Status |
|---|---|
| `parity check [--json]` | works: engine (Podman, machine or native, rootless or rootful; Docker Desktop; anything that answers the `docker` command) and any other engine that is here, `PARITY_ENGINE`, Docker Compose v2 (and Podman's API socket on Linux), Linux containers mode, WSL 2, arm64 emulation (Rosetta, QEMU), Windows line endings in the files Citadel runs; with a `parity.json` and a running engine it ends with the same side-by-side as `parity status` |
| `parity init [--from-squire] [--print]` | works: writes `parity.json` from what is in the folder (with Laravel's scheduler as a cron entry when the app schedules anything); flags `--image`, `--public-dir`, `--mode`, `--mysql`, `--no-mysql`, `--sign-in`, `--health-check`, `--no-cron`, `--port`, `--name`, `--force` |
| `parity up [--verbose] [--wait 5m]` | works: Citadel's image (checked against the catalog), the build, MySQL 5.6.51, the app's deployments and health check, the proxy, then the address. Exit 1 when the app's own build, deployments or health check failed, 2 when Parity could not run it (no engine, no image, a port taken) |
| `parity status [--json]` | works: each piece and its health, then your machine and Citadel side by side (image, image id, PHP, platform, php.ini, MySQL, Node, PHP extensions). Exit 0 when all run and are healthy, 1 when not |
| `parity logs [app\|mysql\|proxy\|cron:<name>] [-f] [--tail 100] [--apache]` | works; `--apache` shows Apache's own logs from `/citadel/logs/apache`, where Citadel keeps them; `cron:<name>` shows a cron entry's stdout and stderr files from `/citadel/logs/cron-jobs` |
| `parity shell [--user citadel] [command...]` | works: a shell (or one command) in the app container, in `/citadel/src` |
| `parity down [--volumes]` | works: stops the app; the database stays unless `--volumes` |
| `parity image list [--json]` | works: the Citadel images Parity knows, and whether each is here and matches |
| `parity image get [tag] [--file x.tar] [--git] [--https] [--keep] [--force]` | works: downloads Citadel's image with your own git.wayne.edu access, checks its sha256 and image id, loads it |
| `parity image verify [tag] [--file x.tar]` | works: the image here (or a tar) against Citadel's. Exit 1 when it differs |
| `parity test [--json]` | works: the front page through the proxy, the app's health check, the requests in `parity.json`, and no PHP fatal error in Apache's log while it ran. Exit 1 when any fails |
| `parity trust [--remove]` | works: puts the app's local https certificate authority in this computer's certificate stores, so the browser stops warning about the app's https address on 127.0.0.1:8443; Windows and macOS work on your user store, Linux prints the sudo commands and updates Chrome's NSS database when it can; `--remove` takes it out again |
| `parity run [--user citadel] [--] command [args...]` | works: runs a tool (`composer ci`, `php artisan test`, `npm run build`) in the app container as user citadel and returns its exit code; in bake mode it says the files it writes vanish on the next `parity up` |
| `parity engine [podman\|docker\|auto] [--json]` | works: says which engine Parity uses and why, or saves the choice for this computer (the desktop app reads the same setting); `PARITY_ENGINE` still wins where it is set |
| `parity version [--json]` | works |

`parity check` prints one line per finding and, under each one that needs it, the one fix Parity recommends for your
OS. It changes nothing. On this Windows PC with Docker Desktop:

```text
  Parity check  C:\Users\you\Development\my-app

  ✓ Docker Desktop 29.9.2 is running (linux/amd64, context desktop-linux).
  ✓ Docker Compose 5.6.1 is here.
  ✓ Linux containers, on WSL 2.
  ✓ This machine runs Citadel's amd64 images natively.

  Ready. This computer can be Citadel's twin.
```

With a `parity.json`, `parity check` and `parity status` end with your machine and Citadel side by side, row for row
what the desktop app shows. Node and the PHP extensions come from `php -m` and `node -v` in the running app container
(or else the app image), compared with the catalog: Node is Close when only the minor differs, an extension that is
missing Differs and one Citadel lacks is Close. The `--json` output does not change.

```text
  Your machine and Citadel  citadel/php-apache:8.5r3
                     YOUR MACHINE                 CITADEL
  ✓ Image            citadel/php-apache:8.5r3     citadel/php-apache:8.5r3     match
  ✓ Image id         sha256:950dcf67937a          sha256:950dcf67937a          match
  ✓ PHP              8.5.12                       8.5.12                       match
  ✓ Platform         linux/amd64                  linux/amd64                  match
  ✓ php.ini          Citadel's php8.ini           Citadel's php8.ini           match
  ✓ MySQL            5.6.51                       5.6.51                       match
  ✓ Node             22.23.4                      22.23.4                      match
  ✓ PHP extensions   50 loaded                    50 loaded                    match
    Node and PHP extensions read from the app image.

  Matches Citadel.
```

`parity up` brings each piece to life in turn and says what it did. On the same PC, `testdata/hello` with Citadel's
own image (a terminal shows a spinner on the step in progress; times show for steps over 2 seconds):

```text
  Parity up  hello on citadel/php-apache:8.5r3

  ✓ Image  citadel/php-apache:8.5r3 matches Citadel (950dcf67937a)
  ✓ Build  app image built; build.sh ran as root with /bin/sh 4s
  ✓ MySQL  5.6.51 is ready on 127.0.0.1:3307, database hello 10s
  ✓ App    deployments ran, and healthy: php health.php said yes 4s
  ✓ Proxy  HAProxy answers, adding X-Forwarded-For and X-Forwarded-Proto as Citadel's does

  hello is up at http://localhost:8080
  Citadel's twin is awake. Edits in this folder show on the next request.

  MySQL   127.0.0.1:3307, database hello, user app (password in .parity/app.env)
  Logs    parity logs -f
  Stop    parity down
```

When the image is not on this computer, `parity up` stops at the Image line with exit 2. The hint is
`parity image get`, or, when Citadel has not published a download for that tag yet, a line that says so: pick an
image Parity can get, or ask the Citadel team to publish it.

### Getting Citadel's image

Citadel's images are `docker save` tars in git LFS in `enterprise-apps/images` on git.wayne.edu, so getting one
takes your own access there. `parity image get` tries, in order: a tar you name with `--file` (downloaded from the
project's page in your browser), a personal access token in `PARITY_GITLAB_TOKEN` (`read_repository` is enough),
then git LFS over SSH with your own key (`--https` for git's credential manager instead). The git route needs git
LFS set up once: install git-lfs (it ships with Git for Windows and Homebrew has `git-lfs`), then run
`git lfs install`; without it git fetches a small pointer file and Parity says so. Only the one tar is fetched,
never the whole repository. Whatever the route, the tar's sha256 and the loaded image's id must match the catalog
inside Parity before Parity calls it Citadel's:

```text
  Parity image get  citadel/php-apache:8.5r3

  ✓ Fetch  379 MB with git LFS from git.wayne.edu (enterprise-apps/images) 30s
  ✓ sha256 61cbd26f1912 matches Citadel's tar
  ✓ Load   loaded into docker 28s
  ✓ Id     950dcf67937a matches Citadel

  citadel/php-apache:8.5r3 is Citadel's own, byte for byte. Next: parity up
```

The download goes to your cache folder and is deleted once loaded (`--keep` keeps it). A token is read from the
environment only and never printed.

### Testing it

`parity test` asks the running app what Citadel's users would. On `testdata/hello`:

```text
  Parity test  hello at http://localhost:8080

  ✓ GET /          200 in 8ms
  ✓ Health check   php health.php said yes
  ✓ GET /          200, has "Citadel's twin"
  ✓ GET /          200, has "X-Forwarded-Proto: http"
  ✓ GET /          200, has "MySQL 5.6.51"
  ✓ PHP errors     no fatal errors while testing

  All 6 passed. Citadel would not notice the difference.
```

A request in `test.requests` passes on any answer below 500 unless it names a `status`, and must contain
`contains` when it names one. Redirects are not followed, so a redirect is checked as the redirect.

### What `parity up` writes

Everything goes into `.parity/` in the app, which ignores itself in git and is rewritten on every `parity up`:
`compose.yaml`, `app.Dockerfile` (the Dockerfile Citadel's generator would write for this app, step for step),
Citadel's own `vhost.conf` and `php.ini` (copied unchanged from Citadel's repository, MIT), the proxy's
`haproxy.cfg`, `up.log` (the build and compose output), the local MySQL passwords in `mysql.env` (made once and kept
so the database keeps opening), `app.env` (the app container's environment: Laravel's `DB_*` names for the local
MySQL, then SESAME's names for `sign_in`), and `cron/` (the cron loop and its table) when the app has cron entries.
`app.env` beats the app's own `.env`, which Parity never touches; on Citadel the app reads its own configuration file
instead. The containers are the compose project `parity-<name>`, so two apps never collide (give them different
ports).

### Output

On a terminal Parity uses color, symbols and live spinners. It drops them on its own when the output is a pipe, a
file or a CI log (plain `[ OK ]`, `[WARN]`, `[FAIL]` tags), when `NO_COLOR` is set (no color), and with `--plain`
anywhere on the line (or `PARITY_PLAIN=1`). `PARITY_STYLE=fancy` keeps symbols and color in a log. In the classic
Windows console Parity turns on its color mode and uses symbols the console's fonts have.

## parity.json

The one file Parity adds to your app. Its fields mirror Citadel's app settings page, so filling it in is copying
the dashboard. `parity init` writes it from what is in the folder; everything has a default but `name`.

| Field | Citadel's setting | Default |
|---|---|---|
| `name` | the app (names the containers) | the folder's name |
| `image` | base image | `citadel/php-apache:8.5r3` |
| `public_dir` | public directory, relative to the app | `public` when there is one, else `.` |
| `mode` | (Parity's) `mount` or `bake` | `mount` |
| `build_script` | build script | `build.sh` when there is one |
| `deployments` | deployments folder | `deployments/` when there is one |
| `health_check` | health check command, run from the app folder | `php health.php` or `./health-check` when there is one; for Laravel, the `health:` route in `bootstrap/app.php` (`/up`); else Apache must answer `/` |
| `cron` | cron entries: `name` (short name), `schedule` (five fields), `command` (see [Cron entries](#cron-entries)) | Laravel's scheduler when the app schedules anything, else none |
| `writable` | writable paths: Apache's group, group-writable, as Citadel's deploy leaves them (baked into the image in bake mode; set as the container starts in mount mode) | Laravel's `storage` and `bootstrap/cache` |
| `config_files` | configuration files: `path` and `required_keys` (ini `section.key`, env `NAME`, json `a.b`) | none |
| `environment`, `server_name`, `time_zone` | environment, host name, time zone | `local`, `<name>.apps.wayne.test`, `America/Detroit` |
| `http_port` | (Parity's) the proxy's port on 127.0.0.1 | `8080` |
| `database` | MySQL: `mysql` yes or no, `name`, local `port` | no MySQL; when yes, `app` on `3307` |
| `sign_in` | (Parity's) `none`, `fake` (SESAME's fake driver) or `cas` (see [Sign-in](#sign-in)) | `none` |
| `fake_user` | (Parity's) who the fake driver signs in | `jdoe` |
| `test.requests` | (Parity's) extra requests for `parity test`: `path`, `status`, `contains` | none |

It holds settings, never secrets. Configuration files are checked for their required keys by name; Parity never
prints a value. Unknown fields are an error, so a typo is caught rather than ignored.

### Sign-in

`sign_in` puts SESAME's own settings in the app container's environment (`.parity/app.env`). A real environment
variable beats `.env` in Laravel and in SESAME's raw PHP settings, so your `.env` keeps whatever it says and Parity
never writes to it.

| `sign_in` | The app gets | Needs |
|---|---|---|
| `none` | nothing; the app's own settings decide | nothing |
| `fake` | `SESAME_DRIVER=fake`, `SESAME_FAKE_USERNAME` (`fake_user`, default `jdoe`) | `environment` of `local`, `development`, `dev`, `testing` or `test`, because SESAME's fake driver refuses to boot anywhere else |
| `cas` | `SESAME_DRIVER=cas`, `SESAME_CAS_SERVICE_URL=https://<server_name>/login` (SESAME's Laravel `/login` route) | `server_name` on wayne.edu, pointed at 127.0.0.1 in your hosts file, and `"https": true`, so Parity serves https for `server_name` on 127.0.0.1:8443: CAS sends its ticket back only to an https address on a name it knows |

`SESAME_CAS_BASE_URL` is left to the app's settings or the playbook, which carry the university's CAS server. For
`cas`, set `"https": true` in `parity.json`: Parity then serves https for `server_name` on 127.0.0.1:8443, and one run
of `parity trust` makes your browser trust the app's local CA. The hosts-file step stays, because CAS must see a name
it knows. `fake` is still the everyday local choice.

### Cron entries

Citadel offers apps no queue worker and no long-running services: it offers cron entries, each a short name, a
schedule and a command. Citadel's host runs each one as `docker exec -u prisoner <your app container> <command>` and
keeps its output in `logs/cron-jobs/<name>.stdout.log` and `<name>.stderr.log`. Parity does the same, inside the app
container, as `prisoner`, with the container's environment, from `/citadel/src`:

```json
"cron": [
  { "name": "schedule", "schedule": "* * * * *", "command": "php artisan schedule:run" }
]
```

- **The schedule takes what Citadel's dashboard takes:** minute and hour as `*`, `*/N` or a list (`0,30`); day of
  month, month and day of week as `*` or a list (Sunday is `0` or `7`). Citadel's schedules are level-headed: no
  ranges, no `MON`, no `@hourly`. If your crontab has a range, it now has a list, and a moment of quiet reflection.
- **The command is one simple command.** Citadel writes it into the host's crontab after `docker exec`, so the
  host's shell reads the line: a `;`, `&&`, `|`, `>` or `$` there would act on the host, outside your app. Parity
  refuses those; put the steps in a script in the app and name it (`sh bin/nightly.sh`).
- **Output:** `parity logs cron:<name>` shows the two files, as Citadel's dashboard does. `parity logs app` shows each
  run as it happens: `cron:<name> >` the command started, `|` a line of its output, `!` a line of its errors, `<` how
  it ended.
- **How Parity runs them:** a small loop (`.parity/cron/cron.php`, in PHP because every Citadel image has PHP)
  starts as `prisoner` beside Apache once Citadel's entrypoint has run the deployments, and starts each due entry with
  `/bin/sh` at the top of the minute, as cron does. Parity could have copied Citadel's mechanism exactly, with a
  sidecar that runs `docker exec`, but that needs the container engine's socket inside a container (root on your
  computer) and a different socket under rootless Podman. The loop gives the command the same container, user,
  environment and folder without it.
- **Queues:** there is no worker service, on Citadel or here. A Laravel app drains its queue from the scheduler, which
  is what Citrus does in `routes/console.php`:

  ```php
  Schedule::command('queue:work --stop-when-empty --tries=5 --timeout=600')
      ->everyMinute()
      ->withoutOverlapping(15)
      ->runInBackground();
  ```

  `parity init` (and `--from-squire`) proposes the `schedule` entry above when `routes/console.php`,
  `bootstrap/app.php` or `app/Console/Kernel.php` schedules anything, or the Squire kit has a scheduler service;
  `--no-cron` leaves it out.

### Node and Vite

Citadel's images carry Node (22 in `8.5r3`), so there is no Node service: build the front end in the app container,
as the user who owns the code, the way a deployment script would on Citadel:

```text
parity shell --user citadel sh -c 'npm ci && npm run build'
```

The built files land in your folder (mount mode) and Apache serves them on the next request. Vite's dev server and hot
reload are not part of Citadel, so run `npm run dev` on your own computer if you want them.

### Road to 1.0

The 1.0 command set: `check`, `init` (with `--from-squire`), `up`, `down`, `status`, `logs`, `shell`, `test`,
`trust`, `image list|get|verify`, `engine` and `version`.

Before the tag:

- One real `parity up` and `parity test` on an Apple Silicon Mac, with Podman and with Docker. Windows is done
  (2026-11-10, Podman and Docker Desktop); that run found three bugs, all fixed before 1.0 (see the CHANGELOG).
  `go run ./tools/checklist -engine podman` (then `-engine docker`) runs the whole check on that Mac and prints the
  row for MAINTAINING's table.
- 1.0 ships unsigned, with checksums, like 0.1.0.
- Citadel's 8.4r5 image is the one outside dependency: until its tar exists, 1.0 offers 8.5r3 only.

### Local HTTPS

With `"https": true` in `parity.json`, Parity serves https for `server_name` (and `localhost`) on 127.0.0.1:8443, a
fixed port, with a leaf from the app's own local CA: one CA per app, name-constrained to the app's names, kept in
your config folder, its key never leaving it. Run `parity trust` once per computer and the browser stops warning;
`parity trust --remove` takes it out again. In CI nobody has run `parity trust`, and that is fine: `parity test`
loads the home page over https pinned to the app's CA, and `parity check`'s `https.trust` finding warns but never
fails, so an untrusted CA keeps CI green.

### After 1.0

- **Commands:** `reset`, `eject`, `image pin` and `image build`.
- **The desktop app**, one window over the same Go code. Signing (Authenticode, Apple Developer ID) comes before it.
- **Packages:** winget, Homebrew and deb/rpm.

A second database is not on the list: Parity runs one MySQL 5.6, as Citadel gives one database per app. An app that
reads a second database (a one-time import from an older app, say) points that connection at a test copy its data
owner approves, in its own settings.

### Coming from Squire's parity kit

`parity init --from-squire`, in the folder with `docker-compose.squire.yml`, carries the kit's settings over (base
image, public folder, ports, database name, time zone, deployments) from the compose file, `.env.squire.example`
and `.env.squire`. Passwords and every other variable are left behind by name: Parity makes its own local passwords.
It lists the kit's files and deletes none of them. Two differences to expect: Parity runs Citadel's own `php.ini`,
not the kit's hardened one, because that is what Citadel runs; and the kit's portable `scheduler` and `worker`
services become one cron entry, `php artisan schedule:run` every minute, because Citadel has cron and no services.

## Fake data only

Parity ships no data connectors, no credentials and no data. `parity test` runs on your app's own fake fixtures. A
local copy may point only at a test database its data owner approves, never production, and never real student or
employee data copied to a laptop (FERPA). Parity never prints a configuration value and never bakes a configuration
file into an image.

## Where it fits in WELD

WELD is the Wayne State application stack. Parity is its "run Citadel locally" layer.

| Part | Job |
|---|---|
| WayneUI (project 0) | The interface layer every app renders |
| CREED | The version policy |
| PROCTOR | Measures an app against that policy |
| Preflight | The CI templates |
| SESAME | Sign-in; its fake driver is how a local copy signs in without CAS |
| HELMET | The baseline runtime |
| Pinstripe | The coding standard |
| Sourdough | The starters (each app's README tells you to run `parity init --from-squire`, which writes `parity.json`) |
| Booster | Moves apps forward; a PHP upgrade is exactly when Citadel's real image matters |
| Squire | Gets the app onto Citadel (`build.sh`, `deployments/`, `release.sh`); Parity runs those files the way Citadel does |
| Parity | This project: Citadel on your computer |

Parity depends on nothing in the stack. Squire's docs point at Parity; nothing calls it.

## Why it is built this way

Recorded in the stack's an ADR (accepted 2026-11-09):

- Parity is its own project because people will use it without Squire: Squire gets the app onto Citadel, Parity runs
  Citadel on your computer.
- The command line is Go, one static binary per OS and arch, because it must run where there is no PHP, drive the
  container engine and check OS settings. That is where PHP would be silly. Standard library first.
- Configuration is `parity.json`, mirroring Citadel's app settings, so filling it in is copying the dashboard.
- Templates are embedded in the binary and rendered into a gitignored `.parity/` folder, so upgrading Parity upgrades
  every app's stack and nothing drifts in your repository.
- Citadel's images come from the Git LFS tars in `enterprise-apps/images` on git.wayne.edu, checked by sha256 and
  image id from a catalog inside the binary.
- CI is split: Linux on git.wayne.edu on every push; Windows and macOS on a private GitHub mirror on tags; a real
  Mac and this Windows PC prove `up` before each release.

## Build it

With Go 1.26 or newer:

```text
go test ./...
go build -o parity ./cmd/parity          (parity.exe on Windows)
go run ./tools/build                     all six binaries into dist/, with checksums.txt
go run ./tools/checklist                 the release checklist's up, test and down on this computer (MAINTAINING.md)
```

Or with Docker only, the same image CI uses:

```text
docker run --rm -v "${PWD}:/src" -w /src -v stack-go-cache:/go -e GOCACHE=/go/build-cache golang:1.26.11-trixie@sha256:fa340cb59ebeb1d53ac1cccfc334201d938fe5f3458003cccb767096d8e032b8 go run ./tools/build
```

## Where the code lives

The canonical repository is [enterprise-apps/parity](https://git.wayne.edu/enterprise-apps/parity) on git.wayne.edu:
issues, merge requests and tags happen there. A private GitHub repository receives a push mirror for Windows and
macOS CI only; nobody pushes to it.

The website and docs, https://parity.apps.wayne.edu/, are built from `site/` and `docs/` in this repository
(MAINTAINING.md, "The site").

Maintaining, releasing and the release checklist: [MAINTAINING.md](MAINTAINING.md). Changes: [CHANGELOG.md](CHANGELOG.md).
License: MIT.

<sub>Parity: because "it worked locally" should be a promise, not an alibi.</sub>
