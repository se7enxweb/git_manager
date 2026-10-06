Git Manager
===================

General Description
-------------------

Git Manager provides a complete web-based git management and site backup toolset for Exponential / eZ Publish. It is designed for environments where **SSH access is not available** but git is installed on the server — enabling admins to manage deployments, branches, and site backups entirely through the web admin interface.

**Core use cases:**
- No shell/SSH access to the server — manage git branches and deployments via web UI
- Hosting environments where only web/FTP access is available
- Teams needing a simple web UI to switch branches without CLI knowledge
- Site backup and download from the browser, without needing server access
- Secure encrypted offsite backup via browser download

Features
--------

**Git Management (Web UI)**
- Checkout local and remote branches — update your site to any git branch via browser
- Review current HEAD commit and diff
- Browse commit log with author, date, and range filters
- Checkout specific commits by hash
- Submodule checkout support

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


Backup Types
------------

| Type | Creates | Use For |
|------|---------|---------|
| **Full Site Backup** | SQL dump + var/ archive + site files (extensions/, settings/, config.php) | Full site restoration from scratch |
| **DB + Files Caption** | SQL dump + var/ archive | Daily backup of database and uploaded files |
| **Database Only** | SQL dump only | Before database changes or migrations |
| **Files Only (var/)** | var/ archive only | Before bulk file changes |

> **Full Site Backup** is the most complete option — it produces 3 separate archives covering everything needed to rebuild the site on a new server, excluding the eZ Publish core files (which come from git).


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
- `LICENSE-GPL-2.0.txt` — GNU General Public License v2 (governs eZ Publish)
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

| State | When | Colour |
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

Run from the Exponential root directory (e.g. `/var/www/vhosts/site/doc/site/`):

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


Configuration (git_manager.ini)
---------------------------------

Settings in `extension/git_manager/settings/git_manager.ini.append.php`:

```ini
[GitManagerSettings]
BackupPath=var/site/backups/captions
MaxBackups=10
CompressBackups=enabled
EnableEncryption=enabled
DeleteUnencryptedAfterEncryption=enabled
```

- `MaxBackups` — automatically deletes oldest captions when limit is exceeded (0 = unlimited)
- `DeleteUnencryptedAfterEncryption` — when `enabled`, removes the unencrypted `.tar.gz` after encrypting, leaving only the `.gpg` file


Using Git Manager (Web)
------------------------

1. Go to **Git Manager → Dashboard** in the admin interface
2. **Switch branch:** Select a local branch and click Checkout, or enter a remote branch name
3. **Pull latest:** Use the pull/update controls to fetch and apply the latest commits
4. **Review commits:** Browse the commit log, filter by author or date range
5. **Checkout commit:** Enter a specific hash to roll back to a previous state


Why This Extension?
--------------------

**Problem:** Many shared hosting environments and managed servers do not provide SSH access to site admins. Git is installed on the server but can only be invoked by the web server user.

**Solution:** Git Manager exposes git operations through eZ Publish's authenticated admin interface. Any admin with the `git_manager` policy can:
- Deploy code changes by switching branches — no SSH needed
- Create and download site backups — no FTP or server access needed
- Review what changed between deployments
- Rollback to a previous commit via browser

**Security:** All operations require admin login. Backup downloads are served through an authenticated PHP controller — backup files are never directly web-accessible. Encryption uses AES256 via GnuPG.


Version
-------

- The current version of Git Manager is 2.0.1
- Last Major update: June 21, 2026

Requirements
------------

- eZ Publish 5.x or higher
- PHP 5.6 or higher (PHP 8.x supported)
- Git binary installed and accessible to the web server user
- GnuPG (`/usr/bin/gpg`) — required only for encrypted backups
- `mysqldump` — required for database backups

Copyright
---------

- Git Manager is copyright 2013 - 2014 Serhey Dolgushev and 1998 - 2026 7x
- See: doc/COPYRIGHT for more information on the terms of the copyright and license

License
-------

Git Manager is licensed under the GNU General Public License v2 or later.
See `doc/LICENSE` for full terms.


Version
-------

- The current version of Git Manager is 2.0.1
- Last Major update: June 21, 2026

Copyright
---------

- Git Manager is copyright 2013 - 2014 Serhey Dolgushev and 1998 - 2026 7x
- See: doc/COPYRIGHT for more information on the terms of the copyright and license

License
-------

Git Manager is licensed under the GNU General Public License.

The complete license agreement is included in the doc/LICENSE file.

Git Manager is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License or at your
option a later version.

Git Manager is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

The GNU GPL gives you the right to use, modify and redistribute
Git Manager under certain conditions. The GNU GPL license
is distributed with the software, see the file doc/LICENSE.

It is also available at http://www.gnu.org/licenses/gpl.txt

You should have received a copy of the GNU General Public License
along with Git Manager in doc/LICENSE.  If not, see http://www.gnu.org/licenses/.

Using Git Manager under the terms of the GNU GPL is free (as in freedom).

For more information or questions please contact
license@se7enx.com

Requirements
------------

The following requirements exists for using Git Manager extension:

eZ Publish version
- Make sure you use eZ Publish version 5.x (required) or higher.

PHP version
- Make sure you have PHP 5.x or higher.

Git binaries installed on server
- Make sure you have the git binary installed on server

Troubleshooting
---------------

Read the FAQ
- Some problems are more common than others. The most common ones are listed in the the doc/FAQ.

Use our support systems
- If you have find any questions not handled by this document or the FAQ you can post a message in the [Git Manager: Project Forums](http://projects.ez.no/git_manager/forum/general)
- If you find a bug or defect, please report it to the [Git Manager: Issue Tracker](https://github.com/brookinsconsulting/git_manager/issues)
