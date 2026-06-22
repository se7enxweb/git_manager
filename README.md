Git Manager
===================

General Description
-------------------

Git Manager provides a complete web-based git management and site backup toolset for eZ Publish. It is designed for environments where **SSH access is not available** but git is installed on the server — enabling admins to manage deployments, branches, and site backups entirely through the web admin interface.

**Core use cases:**
- No shell/SSH access to the server — manage git branches and deployments via web UI
- Hosting environments where only web/FTP access is available
- Teams needing a simple web UI to switch branches without CLI knowledge
- Automated site backup and download without needing server access
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
- **Prominent backup age warnings** — visual alerts when backups are outdated (>7 days)
- **Critical "No Backups" warning** — red alert box when zero captions exist
- Secure download of backup files via authenticated controller
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


Backup Age Warnings
-------------------

The Backup Manager dashboard displays prominent visual warnings to remind you when backups need attention:

**🚨 Critical Warning (Red Box)** — Shown when **zero captions exist**:
- Red gradient background with pulsing red border
- Animated 🚨 icon with urgent shake
- Message: "Your website has ZERO backup captions. Take a backup caption right now..."
- Purpose: Prevents leaving the site completely unprotected

**⚠️ Outdated Warning (Orange Box)** — Shown when **newest caption is >7 days old**:
- Amber/yellow gradient background with pulsing orange border
- Animated ⚠️ icon with periodic shake
- Shows exact age: "Your site backup captions are 2 weeks old and outdated..."
- Purpose: Encourages regular backup refresh to minimize data loss risk

Both warnings are:
- Positioned directly below the page heading for maximum visibility
- Responsive (adjust padding/sizing on mobile)
- Dismissible by creating a new backup

> **Best Practice:** Create a full caption weekly, and a database-only caption before any CMS upgrades or major content changes.


Using the Backup Manager (Web)
-------------------------------

1. Navigate to **Git Manager → Backup Manager** in the admin interface
2. Choose a backup type card
3. Optionally add a description
4. Optionally check **Encrypt backup files** and enter a passphrase
5. Optionally check **🔓 AGPL Compatible Release** to create a sanitized SQL dump for public sharing
6. Click the create button — backup runs server-side, page reloads on completion
7. In **Existing Captions**, download individual archive files using the green Download buttons
8. Captions marked with 🔓 **AGPL COMPATIBLE** badge contain both private and public-shareable SQL dumps
9. Delete outdated captions using the red Delete button (right-aligned) or use **Select All + Delete Selected** for bulk removal

> ⚠️ The age indicator (⏱) next to each caption timestamp shows how old it is:
> - 🟢 Green = fresh (< 7 days)
> - 🟠 Orange = aging (7–90 days)
> - 🔴 Red = outdated (90+ days or 1+ year)


Using the CLI Backup Tools
---------------------------

Run from the eZ Publish root directory (e.g. `/var/www/vhosts/site/doc/site/`):

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
