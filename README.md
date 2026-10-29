Git Manager
===========

Git Manager is an Exponential extension that puts the installation's git checkout and its backups
into the administration. An administrator can switch branches, check out a commit, compare the
checked out branch with its branch on a remote, fetch, push, manage remotes and submodules, browse
the whole commit log, and make, encrypt, download and remove site backups, all from the browser.

It is meant for sites where git is installed on the server but nobody has (or should need) a shell:
shared hosting, managed servers, and teams that deploy by switching a branch rather than by typing
commands. Everything it does is also available from the command line, for those who do have one.

Contents
--------

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Using Git Manager](#using-git-manager)
- [Backups](#backup-types)
- [Permissions](#permissions)
- [Command line tools](#using-the-cli-backup-tools)
- [Upgrading](#upgrading)
- [Known limits](#known-limits)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)
- [Credits and history](#credits-and-history)

**Core use cases:**
- No shell/SSH access to the server: manage git branches and deployments in the browser
- Hosting where only web or FTP access is available
- Teams that need a simple page to switch branches without knowing git's command line
- Site backups made and downloaded from the browser, without server access
- Encrypted offsite backups through a browser download

Features
--------

**Git Management (Web UI)**
- Checkout local and remote branches — update your site to any git branch via browser
- Review the HEAD commit and the details of any commit
- Compare the checked out branch with its branch on a remote (the Upstream card) and fetch it with one button; each fetch is audited
- Browse the whole commit log page by page (10, 25, 50 or 100 per page), filtered by branch or tag, author, message text and date range; every page and filter is a link of its own
- Checkout specific commits by hash
- Manage remotes (add, change, remove, fetch) and push a branch to one, never forced
- Manage submodules (add, change, update, remove) and check out with submodules updated

**Backup & Caption Manager (Web UI)**
- Create timestamped backup captions (snapshots) of your site
- Four backup types (see below)
- Optional AES256 GPG encryption per backup
- **AGPL-compatible sanitized SQL dumps** — strips all private data for public sharing
- **GPL v2 + AGPL v3 license files** bundled in every caption archive for legal compliance
- Multi-select delete of old captions
- Age indicators (green/orange/red) showing how old each backup is
- **A status taken from the newest backup** — fresh, ageing (older than `WarnAfterDays`, 7), stale (older than `StaleAfterDays`, 30) or none, see "Backup Status and Age Warnings"
- Secure download of backup files via authenticated controller, streamed (archives of any size)
- Automatic cleanup of old captions based on `MaxBackups` setting

**CLI Tools** (requires shell access)
- `backup-create.php` — create backups from command line
- `backup-list.php` — list all captions with age indicators
- `backup-info.php` — show detail for a specific caption
- `backup-delete.php` — delete one or all captions
- `remote.php` — list, add, change, rename, remove, fetch and push remotes
- `submodule.php` — list, add, change, update and remove submodules
- `upgrade-policy-dump-to-backup.php` — rename the pre-2.0.4 `git_manager/dump` policies


Backup Types
------------

| Type | Creates | Use For |
|------|---------|---------|
| **Full Site Backup** | SQL dump + var/ archive + site files (extensions/, settings/, config.php) | Full site restoration from scratch |
| **DB + Files Caption** | SQL dump + var/ archive | Daily backup of database and uploaded files |
| **Database Only** | SQL dump only | Before database changes or migrations |
| **Files Only (var/)** | var/ archive only | Before bulk file changes |

> **Full Site Backup** is the most complete option — it produces 3 separate archives covering everything needed to rebuild the site on a new server, excluding the Exponential kernel files (which come from git).


AGPL-Compatible Public Release Dumps
-------------------------------------

When creating any SQL-based backup (Full Site, DB + Files, or Database Only), you can check the **🔓 AGPL Compatible Release** checkbox. This creates an **additional sanitized SQL dump** file alongside your normal backup, designed for public sharing or distribution to comply with AGPL/GPL license obligations.

**What gets sanitized:**
- User password hashes (bcrypt, MD5, SHA1/SHA256)
- Email addresses
- Google Analytics IDs (UA-, G-, GTM-, AW-)
- Stripe API keys (live/test secret/publishable)
- SendGrid and AWS API keys
- reCAPTCHA/hCaptcha site/secret keys
- Named settings (Password, ApiKey, AuthToken, WebhookSecret, SMTPPassword, etc.)
- Absolute disk paths (/var/www/, /home/, /srv/, etc.)
- Database credentials
- IP addresses in quoted strings
- SMTP credentials
- Complete removal of session, audit, pending actions, and notification tables

**Result:**
- Normal backup: `sql_2026-06-21_12-00-00.tar.gz` (full data, private)
- AGPL backup: `sql_agpl_2026-06-21_12-00-00.tar.gz` (sanitized, shareable)
- Marker file: `agpl_compatible.txt` (explains what was sanitized)
- Caption marked with: 🔓 **AGPL COMPATIBLE** badge in the UI

**License Files Included:**
Every caption directory and compressed archive includes a `licenses/` folder with:
- `LICENSE-GPL-2.0.txt` — GNU General Public License v2 (governs Exponential)
- `LICENSE-GPL-2.0.md` — Markdown-formatted GPL v2
- `LICENSE-AGPL-3.0.txt` — GNU Affero General Public License v3 (governs AGPL dumps)
- `LICENSE-AGPL-3.0.md` — Markdown-formatted AGPL v3
- `README.txt` — explains why these files are included

This ensures anyone receiving your backup archive has the full license terms as required by open source distribution obligations.


Backup Status and Age Warnings
------------------------------

The top of the Backup page says in one block how fresh the backups are. It is taken from the
**newest** backup only: an old backup next to a recent one is no reason for a warning. (Before
2.0.15 the page took the age of the *oldest* backup, so it kept warning about a backup from
months ago however recent the newest was.)

| State | When | Color |
|-------|------|--------|
| none | there is no backup | red |
| fresh | the newest backup is at most `WarnAfterDays` old | green |
| ageing | older than `WarnAfterDays`, at most `StaleAfterDays` | orange |
| stale | older than `StaleAfterDays` | red |
| future | every backup is dated more than `FutureToleranceMinutes` ahead of the server clock | orange |

The thresholds are in `git_manager.ini`:

```ini
[BackupFreshnessSettings]
WarnAfterDays=7
StaleAfterDays=30
FutureToleranceMinutes=5
```

How the age is found:

- A backup's time is its folder name, `Y-m-d_H-i-s`, read in the installation's time zone (the one
  `config.php` sets; the names are written in that zone too). The folder's mtime is used only for a
  folder whose name is not such a date (made or renamed by hand), and the list says so. Copying,
  restoring or adding a file to a backup does not change its age.
- The backups are listed newest first, by that time, then by name.
- The age is the number of seconds between that time and now, shown in the largest whole unit
  (1 day, 22 days, 3 months, 1 year), singular and plural each with its own translatable text.
- A backup dated in the future does not count as the newest: a wrong clock or time zone must not
  hide that the real backups are old. The page names such backups in a note.

The texts are chosen from the state alone (`GitManagerBackupMessages`), the state from the ages
(`GitManagerBackupFreshness`), and the backups are read from the disk by
`GitManagerBackupCatalogue`. `bin/php/backup-list.php` prints the same status line.

Nothing makes backups on its own: the warning stays until someone creates a backup (on the page or
with `bin/php/backup-create.php`, which a crontab can run; see below).

> **Best Practice:** Create a full caption weekly, and a database-only caption before any CMS upgrades or major content changes.


Using the Backup Manager (Web)
-------------------------------

1. Open **Setup → Backup** in the admin interface (`/git_manager/backup`; the address before 2.0.4, `/git_manager/dump`, redirects there)
2. Choose one of the four forms under **Create a backup**
3. Optionally add a description
4. Optionally check **Encrypt the archives** and enter a passphrase
5. Optionally check the **AGPL compatible database dump** to create a sanitized SQL dump for public sharing
6. Click the create button — the backup runs on the server; the page comes back with the result
7. In **Existing backups**, download each archive with its **Download** button (the AGPL compatible dumps too)
8. Remove a backup with its **Remove** button, or several with the check boxes and **Remove selected**

Every form is a POST with the form token of ezformtoken and ends in a redirect to the page, so
reloading it repeats nothing; the result is shown once.

**Downloads** are streamed in pieces with the session closed, so an archive of several gigabytes
needs no memory and does not block the user's other pages. A persistent PHP server (Exponential
Velocity) keeps a whole response in memory before sending it; there an archive larger than
`PersistentServerDownloadLimitMB` (git_manager.ini, default 128) is marked *Too large for this
server* and refused with a message. Download it through the address served by Apache or PHP-FPM,
or copy it from the server.

**Tests:** `php ../../vendor/bin/phpunit` in `extension/git_manager` (or `phpunit -c phpunit.xml.dist`
in a checkout) runs the unit tests of the catalogue, the freshness and the texts. They need no
database and make their throwaway backup folders under `tests/.tmp` (or `GIT_MANAGER_TEST_TMP`).
Run them as the site user as well: two tests about unreadable backups are skipped as root.

Access
------

The backup page and the downloads need the policy **git_manager/backup**. Before 2.0.4 this
function was called **git_manager/dump**; roles that grant the old name keep working, and

```bash
php extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php --dry-run
php extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php
```

renames them.


Using the CLI Backup Tools
---------------------------

Run from the Exponential root directory (the folder that holds `index.php` and `extension/`):

**List all backups:**
```bash
php extension/git_manager/bin/php/backup-list.php
php extension/git_manager/bin/php/backup-list.php -v -s
```

**Create backups:**
```bash
# Full site backup (DB + var + site files)
php extension/git_manager/bin/php/backup-create.php fullsite -d "Before upgrade"

# DB + files
php extension/git_manager/bin/php/backup-create.php full -d "Daily backup"

# Database only
php extension/git_manager/bin/php/backup-create.php db

# Files only
php extension/git_manager/bin/php/backup-create.php var

# With encryption
php extension/git_manager/bin/php/backup-create.php full -e -p "yourPassphrase" -d "Encrypted"
```

**Scheduled backups:** nothing in git_manager makes backups on its own. A crontab line of the
site's user makes them regularly, for example a database backup every night:
```bash
30 3 * * * cd /path/to/exponential && php extension/git_manager/bin/php/backup-create.php db -d "Nightly" >> var/log/git_manager_backup.log 2>&1
```
`MaxBackups` in git_manager.ini limits how many are kept (the oldest are removed after a full
backup).

**Inspect a caption:**
```bash
php extension/git_manager/bin/php/backup-info.php 2026-06-21_20-20-49
```

**Delete a caption:**
```bash
php extension/git_manager/bin/php/backup-delete.php 2026-06-21_20-20-49
php extension/git_manager/bin/php/backup-delete.php 2026-06-21_20-20-49 -f   # force, no prompt
php extension/git_manager/bin/php/backup-delete.php -a                        # delete all
```


**Remotes and submodules** (the same checks as the dashboard; git never waits for a password):
```bash
php extension/git_manager/bin/php/remote.php list
php extension/git_manager/bin/php/remote.php add deploy git@github.com:example/site.git
php extension/git_manager/bin/php/remote.php fetch deploy
php extension/git_manager/bin/php/remote.php push deploy production

php extension/git_manager/bin/php/submodule.php list
php extension/git_manager/bin/php/submodule.php update
```

Decrypting GPG-Encrypted Backup Files
--------------------------------------

Encrypted backup files end in `.tar.gz.gpg`. To decrypt and extract:

**Decrypt only (produces `.tar.gz`):**
```bash
gpg --batch --yes --passphrase "yourPassphrase" -o output.tar.gz -d backup_file.tar.gz.gpg
```

**Decrypt and extract in one step:**
```bash
gpg --batch --yes --passphrase "yourPassphrase" -d backup_file.tar.gz.gpg | tar -xzf -
```

**Test decryption (verify passphrase works without extracting):**
```bash
gpg --batch --passphrase "yourPassphrase" -d backup_file.tar.gz.gpg > /dev/null && echo "OK" || echo "FAILED"
```

> Only the person who created the backup knows the passphrase. There is no recovery mechanism — store your passphrase securely (e.g. in a password manager).


Requirements
------------

- Exponential 6.x (the audit event of "Fetch now" needs the audit log of Exponential 6.0.15 or later)
- PHP 8.0 or later (tested up to PHP 8.5), with `proc_open`, `exec` and `shell_exec` allowed
- The `git` binary, runnable by the user the web server runs PHP as, and a git checkout of the site
- For database backups, the dump tool of the engine: `mysqldump` (MySQL or MariaDB), `pg_dump`
  (PostgreSQL), `mongodump` (MongoDB); SQLite needs none (`VACUUM INTO`, SQLite 3.27 or later, else
  the file is copied)
- GnuPG (`gpg`) for encrypted backups only
- `tar` and `gzip` for compressed backups
- Optional: the PHP `posix` extension (the Upstream card names the user the site runs as and, when
  PHP runs as root, fetches as the owner of the repository) and `mbstring`

Installation
------------

### With Composer

From the root of the Exponential installation:

```bash
composer require se7enxweb/git_manager
```

The Exponential legacy installer places the package in `extension/git_manager`.

### By hand

1. Download the latest release from https://github.com/se7enxweb/git_manager/releases (or
   `git clone https://github.com/se7enxweb/git_manager.git`).
2. Copy or move the folder to `extension/git_manager` in the installation. The folder name must be
   `git_manager`.

### Activate it

Add the extension to `settings/override/site.ini.append.php`:

```ini
[ExtensionSettings]
ActiveExtensions[]=git_manager
```

Then regenerate the autoloads and clear the caches, from the installation root:

```bash
php bin/php/ezpgenerateautoloads.php -e
php bin/php/ezcache.php --clear-all
```

Sign in to the administration: the **Git** tab now appears in the top menu, and **Setup** lists
**Git Manager** and **Backup**. Give the roles that should use them the policies listed under
[Permissions](#permissions) (the Administrator role already has every function).

Configuration
-------------

All settings are in `extension/git_manager/settings/git_manager.ini.append.php`. Do not edit that
file: override what you need in `settings/override/git_manager.ini.append.php` (or in a
siteaccess). The defaults, with what each one does:

```ini
[GitManagerSettings]
# Where the backups (captions) are stored, relative to the installation root.
BackupPath=var/site/backups/captions
# The database dump command. Placeholders: {host}, {port}, {user}, {password}, {database},
# {output_file}. The password reaches mysqldump through an option file, never on the command line.
MySQLDumpCommand=mysqldump --host={host} --port={port} --user={user} --password={password} --databases {database} --single-transaction --quick --lock-tables=false --routines --triggers --events
# Folders of var/ left out of a file backup (the session files are a way in, so they are left out).
VarExcludeDirs[]
VarExcludeDirs[]=cache
VarExcludeDirs[]=log
VarExcludeDirs[]=site/backups
VarExcludeDirs[]=site/sessions
# How many backups are kept; the oldest are removed after a full backup (0 = no limit).
MaxBackups=36
# Compress the archives with gzip.
CompressBackups=enabled
# Offer GPG encryption on the Backup page.
EnableEncryption=enabled
# Remove the unencrypted archive once the encrypted one is written.
DeleteUnencryptedAfterEncryption=enabled
# The largest archive a persistent PHP server (Exponential Velocity) sends, in MB (0 = no limit).
PersistentServerDownloadLimitMB=128

[BackupFreshnessSettings]
# The newest backup older than this many days: "ageing" (orange).
WarnAfterDays=7
# Older than this many days: "stale" (red). At least WarnAfterDays.
StaleAfterDays=30
# A backup dated this many minutes or more ahead of the clock does not count as the newest.
FutureToleranceMinutes=5

[UpstreamSettings]
# The remote the Upstream card compares with and fetches.
Remote=origin
# The branch on that remote; empty: the checked out branch's upstream, else the same name.
Branch=
# Seconds a fetch may take.
FetchTimeout=60
# Fetch a public GitHub repository anonymously over HTTPS when its SSH address does not work
# for the user the site runs as.
HttpsFallback=enabled
# The repository's address in a browser, for links to commits; empty: derived from the remote.
WebUrl=
# The most commits listed each way.
MaxCommits=100
# Seconds the computed status is kept at most.
StatusCacheTTL=60
```

A typical override, for a site that deploys from the `production` branch of a remote called
`deploy` and keeps ten backups:

```ini
<?php /* #?ini charset="utf-8"?

[GitManagerSettings]
MaxBackups=10

[UpstreamSettings]
Remote=deploy
Branch=production

*/ ?>
```

The extension also adds itself to `menu.ini` (the Git tab and the Setup links), `module.ini`,
`design.ini` (its design and its stylesheet), `site.ini` (its translations) and `audit.ini`
(the audit event below). Nothing there needs changing.

Using Git Manager
-----------------

### The Git tab

The **Git** tab of the top menu opens the dashboard (`/git_manager/dashboard`). In the admin4
design the tab shows the git icon before its name. The same page is linked from **Setup → Git
Manager**.

The dashboard has these cards:

1. **HEAD**: the checked out branch, the last commit and its date.
2. **Upstream**: the checked out branch against its branch on the remote of `[UpstreamSettings]`:
   the commits missing on either side, uncommitted files, links to the commits on the remote's
   web page, and **Fetch now** (`git fetch --prune` of that remote, nothing else). Every fetch is
   recorded in the audit log as `system.git_manager.fetch`.
3. **Checkout**: choose a local branch, or a branch of a remote, and check it out. This is how a
   site is deployed: push the work to a branch, then check that branch out here.
4. **Commit**: check out one commit by its hash, for example to go back to a known good state.
5. **Remotes**: list, add, change and remove remotes, fetch one, and push a branch to one (never
   forced). Needs the `remotes` and `push` functions.
6. **Submodules**: list, add, change, update and remove submodules, and check out with submodules
   updated. Adding, changing and removing stage the change; commit it to keep it. Needs the
   `submodules` function.
7. **Log**: the commit history page by page (10, 25, 50 or 100 per page), filtered by branch or
   tag, author, message text and dates. Each commit links to its details
   (`/git_manager/commit_details/<hash>`).

The log's page and filters are view parameters, so a filtered view is a link you can share or
bookmark:

```
/git_manager/dashboard/(offset)/50/(limit)/25/(ref)/main/(author)/jane/(grep)/fix/(since)/2026-01-01
```

Every value reaches git as an argument of its own, without a shell, after a check: a branch or tag
must be one of the repository's refs, dates must be real days, texts are cut at 200 characters.

### The Backup page

**Setup → Backup** (`/git_manager/backup`) makes, lists, downloads and removes backups; see
[Using the Backup Manager (Web)](#using-the-backup-manager-web) above.

Permissions
-----------

The module `git_manager` has these functions. Give them in **User accounts → Roles and policies**.

| Function | Allows |
|----------|--------|
| `git_manager/git_manager` | The dashboard and the commit details: checkout of branches and commits, fetch, the log |
| `git_manager/push` | Pushing a branch to a remote |
| `git_manager/remotes` | Adding, changing and removing remotes |
| `git_manager/submodules` | Adding, changing, updating and removing submodules |
| `git_manager/backup` | The Backup page and the downloads |
| `git_manager/dump` | The name `backup` had before 2.0.4; still accepted, see [Access](#access) |

Checking out a branch changes the code the site runs. Give these functions only to people who
may deploy.

Upgrading
---------

1. Replace the extension's folder with the new release (`composer update se7enxweb/git_manager`,
   or copy the new files over the old ones).
2. Regenerate the autoloads and clear the caches:
   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-all
   ```
3. Read [doc/CHANGELOG.md](doc/CHANGELOG.md) for what changed. From a version before 2.0.4, run
   `bin/php/upgrade-policy-dump-to-backup.php` once (see [Access](#access)).

Known limits
------------

- Git Manager runs git as the user PHP runs as. That user needs read and write access to the
  checkout (including `.git`), and the SSH keys or credentials of the remotes. A remote that asks
  for a password fails rather than waits.
- Backups and fetches run inside the web request. A very large database or `var/` folder can take
  longer than the web server's timeout; use `bin/php/backup-create.php` from a shell or a crontab
  for those.
- Database backups support MySQL and MariaDB (`mysqldump`), PostgreSQL (`pg_dump --clean`), SQLite
  (a consistent copy with `VACUUM INTO`) and MongoDB (`mongodump`). Each caption says in `dump.json`
  which engine, archive and file it holds. Oracle is not dumped: export it with Data Pump (`expdp`)
  before an upgrade; the caption fails with that hint.
- A persistent PHP server keeps a whole download in memory, so large archives are refused there
  (`PersistentServerDownloadLimitMB`); Apache or PHP-FPM stream them.
- Git Manager does not pull, merge or rebase, and it never forces a push.

Troubleshooting
---------------

- **The Git tab is missing.** Check that `ActiveExtensions[]=git_manager` is set, clear the caches,
  and give your role `git_manager/git_manager`.
- **The tab has no icon.** Icons are drawn by the admin4 design; older admin designs show the name only.
- **"Fetch now" fails with a permission or host key error.** The user PHP runs as cannot reach the
  remote over SSH. Add a key for that user, set `HttpsFallback=enabled` for a public GitHub
  repository, or change the remote's address to HTTPS.
- **A checkout fails because of local changes.** Git refuses to overwrite uncommitted changes. The
  Upstream card shows how many there are; commit or remove them from a shell first.
- **The database backup is empty or fails.** Check that `mysqldump` is installed and in the PATH of
  the web server, and adapt `MySQLDumpCommand` if your server needs other options.
- **An encrypted backup cannot be opened.** See
  [Decrypting GPG-Encrypted Backup Files](#decrypting-gpg-encrypted-backup-files); without the
  passphrase there is no way back.
- **"Too large for this server" on a download.** Download through Apache or PHP-FPM, or raise
  `PersistentServerDownloadLimitMB`.

Questions and bug reports: https://github.com/se7enxweb/git_manager/issues

Contributing
------------

Pull requests are welcome at https://github.com/se7enxweb/git_manager. The unit tests need no
database:

```bash
cd extension/git_manager
php ../../vendor/bin/phpunit -c phpunit.xml.dist
```

Translations live in `translations/<locale>/translation.ts`; the extension ships English, German,
Spanish (Spain and Mexico), Croatian, Czech, Danish, Dutch, Hebrew, Italian, Latin, Norwegian,
Polish, Brazilian Portuguese, Swedish, Turkish and Klingon. New languages are welcome.

License
-------

Git Manager is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 2 of the
License or (at your option) any later version. See [LICENSE](LICENSE). It is distributed in the
hope that it will be useful, but WITHOUT ANY WARRANTY.

The backup archives carry copies of the GPL v2 and the AGPL v3 (see
[AGPL-Compatible Public Release Dumps](#agpl-compatible-public-release-dumps)); their texts are
in `doc/`.

Credits and history
-------------------

Serhey Dolgushev wrote Git Manager in 2013 as an extension for eZ Publish, the content management
system that preceded Exponential, to switch a site's branch from its administration. 7x has
maintained it since, for Exponential: backups and encryption, the AGPL compatible dumps, the
Upstream card, remotes and submodules, the paged log, the audit event, the admin4 design and the
translations.

- Copyright (C) 2013 - 2014 Serhey Dolgushev
- Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.

See [doc/CREDITS.md](doc/CREDITS.md) and [doc/COPYRIGHT.md](doc/COPYRIGHT.md).
