# MailHog mail catcher

[MailHog](https://github.com/mailhog/MailHog) for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
an SMTP server that accepts every email your sites send and keeps it, instead of delivering it.
Read what was sent in its web UI at http://localhost:8025, or list it from the CLI.

Written for: developers running sites in my-sites-ide who want to see the mail those sites send,
including ones moving over from the MailHog that used to ship inside the IDE.

## Contents

- [Installation](#installation)
- [Upgrading from the built-in MailHog](#upgrading-from-the-built-in-mailhog)
- [Architecture](#architecture)
- [Sending mail to it](#sending-mail-to-it)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section of the IDE's `composer.local.json`
(the IDE's `composer.local-example.json` already lists it):

```json
"yiendos/my-sites-ide-mail-mailhog": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide ide:build      # builds the ${NAMESPACE}_mailhog image, then sparks the IDE
```

Composer's `post-autoload-dump` hook registers the `mail:mailhog-*` commands and the `mailhog`
compose service. The service has `autostart: true`, so `ide:spark` starts it alongside `APP` - you
don't need to list `mailhog` in `APP`.

## Upgrading from the built-in MailHog

MailHog used to live in the IDE at `_dev/environment/mailcatchers/mailhog/`. The image, container
name, hostname (`mailhog`) and ports are unchanged, so sites already sending to `mailhog:1025` keep
working. The parts that moved:

| Before | Now |
|---|---|
| `_dev/environment/mailcatchers/mailhog/` | this package |
| `mailhog` listed in `APP` | started by `autostart` |
| the whole root `.env` passed into the container (`env_file`) | nothing passed in - MailHog read none of it, and it put secrets such as API keys and passwords in the container's environment |
| ports fixed at 8025 and 1025 | `MAILHOG_UI_PORT` / `MAILHOG_SMTP_PORT`, same defaults |

Recreate the container once from the plugin's compose file (this empties the inbox):

```
php my-sites-ide mail:mailhog-start
```

Your existing `.env` can keep `mailhog` in `APP` while the plugin is installed - it's
de-duplicated. Remove it if you uninstall the plugin, or `ide:spark` will ask compose for a service
that no longer exists.

### From `yiendos/my-sites-ide-servers-mailhog`

The plugin was first published under the `servers` category. MailHog catches mail and doesn't serve
sites, so it now sits under `mail`. In `composer.local.json`, swap
`yiendos/my-sites-ide-servers-mailhog` for `yiendos/my-sites-ide-mail-mailhog`, then run
`composer update`. The commands are now `mail:mailhog-*` instead of `servers:mailhog-*`. The
container, image and ports haven't changed.

## Architecture

```
host (my-sites-ide CLI)
  |- ide:spark / ide:restart           --> docker compose up / restart (mailhog included)
  |- mail:mailhog-start / -stop     --> docker compose up -d / stop mailhog
  |- mail:mailhog-messages / -clear --> MailHog's HTTP API on http://localhost:8025

site (fpm, cron, cli containers) --SMTP mailhog:1025--> mailhog container --> kept in memory
browser --http://localhost:8025--> mailhog web UI
minikube / anything outside the network --SMTP localhost:1025--> mailhog container
```

The container runs as `mailhog` (uid/gid 50148, set in the `Dockerfile`). The image builds MailHog
from source (`go install github.com/mailhog/MailHog@latest`) onto plain Alpine.

## Sending mail to it

Inside the IDE, a site reaches MailHog by its container name. For Laravel, in the site's `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_ENCRYPTION=null
```

No username or password - MailHog accepts anything. `mail:mailhog-start` prints these lines.

The SMTP port is also published on the host (1025), so something outside the `my-sites-ide`
network - a minikube deployment, a script on your Mac - can send to `localhost:1025`.

## Command reference

| Command | What it does |
|---|---|
| `mail:mailhog-start` | `docker compose up -d mailhog`, then prints the UI address and the `MAIL_*` settings. Also recreates a running container whose compose config has changed (e.g. new ports) |
| `mail:mailhog-stop` | `docker compose stop mailhog`, leaving the rest of the IDE running. The next `ide:spark` starts it again |
| `mail:mailhog-messages [--limit=10]` | List caught mail, newest first: received, from, to, subject |
| `mail:mailhog-clear` | Delete every caught email - handy before a test run |

`-messages` and `-clear` talk to MailHog's API from the host, on `MAILHOG_UI_PORT`.

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `MAILHOG_UI_PORT` | `8025` (this plugin's `.env`) | The host port for the web UI and API |
| `MAILHOG_SMTP_PORT` | `1025` (this plugin's `.env`) | The host port for SMTP. Containers on the IDE network always use `mailhog:1025`, whatever this is |

Set either in the IDE's root `.env`, which wins over the plugin's defaults, then run
`mail:mailhog-start` to recreate the container.
`php my-sites-ide ide:plugin-env yiendos/my-sites-ide-mail-mailhog` copies them in, commented out.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_mailhog` |
| the `my-sites-ide` network | sites reaching it as `mailhog` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | Discover's compose include |

## Troubleshooting

**The site says it sent mail, but nothing shows up.** Check the site's mail settings point at
`mailhog` port `1025` (not `localhost`, which inside the fpm container is fpm itself), and that the
site isn't using the `log` or `array` mailer. Laravel caches config, so run
`php artisan config:clear` after changing `.env`.

**`Connection refused` / `getaddrinfo for mailhog failed`.** MailHog isn't running:
`php my-sites-ide mail:mailhog-start`.

**`Can't reach MailHog at http://localhost:8025`.** The container is stopped, or `MAILHOG_UI_PORT`
changed without recreating it - run `mail:mailhog-start`.

**The inbox emptied.** MailHog keeps mail in memory, so any stop, restart or recreate clears it.

**Port 8025 or 1025 is already allocated.** Another container or host process has it.
`docker ps --filter publish=8025` shows which one, or move MailHog with `MAILHOG_UI_PORT` / `MAILHOG_SMTP_PORT`.

## Known gaps

- Mail is kept in memory only. MailHog can store to a maildir (`-storage=maildir`), which this
  plugin could keep in `storage/plugins/mailhog/` with `"storage": true`.
- MailHog itself is no longer maintained upstream (last release 2020). Mailpit is the usual
  drop-in successor, and would fit as a sibling plugin.
- The UI has no authentication. It's published on all host interfaces, so anyone on your network
  can read caught mail.
