Git Manager changelog
=====================

The changes of each release, newest first. Earlier releases are described in their GitHub
release notes (https://github.com/se7enxweb/git_manager/releases).

Unreleased
----------

- Added: Captions dump every database engine the kernel runs on, not only MySQL and MariaDB:
  PostgreSQL (`pg_dump --clean --if-exists`, the password in the environment), SQLite (a
  consistent copy with `VACUUM INTO` while the site runs, the file copied as a fallback) and
  MongoDB (`mongodump`, the address in a file only its owner reads). Oracle fails with the Data
  Pump export to run instead. Each dump is packed like the MySQL one (`sql_<caption>.tar.gz`).
- Added: `dump.json` in every caption names the engine, the archive and the dump inside it, so a
  restore (`./console exp:upgrade:rollback`) finds it; `BackupManager::supportedEngines()`,
  `databaseEngine()` and `databaseDumpHint()` for callers such as the kernel's `exp:upgrade`.

2.0.23 (2026-10-21)
-------------------

- Updated: The Git tab of the top menu shows the git icon in the admin4 design (`Icon=git` in
  [Topmenu_gitmanager] of menu.ini.append). Older admin designs ignore the setting.
- Updated: composer.json requires PHP 8.0 and the Exponential legacy installer, names the
  extension directory for both installer keys, links the issues and the source, and lists the
  original author and 7x as maintainer.
- Updated: ezinfo.php and extension.xml state the release, the same copyright, the license and
  the website.
- Updated: The README is rewritten for new users: installation with Composer and by hand, every
  setting with an example, the dashboard's cards, the policy functions, the command line tools,
  upgrading, known limits and troubleshooting.

2.0.22 (2026-10-19)
-------------------

- Added: Translations in Croatian, Czech, Danish, Dutch, Italian, Norwegian, Polish, Brazilian Portuguese, Swedish and Turkish.

2.0.21 (2026-10-17)
-------------------

- Added: The dashboard's commit log has pages, so the whole history can be browsed: first,
  previous, the page numbers (the administration's shared pager), next and last, with "Showing
  x–y of z commits" and a page size of 10, 25, 50 or 100, kept as the user preference
  admin_git_manager_log_limit (50 until one is chosen). A page is `git log --skip --max-count`
  and the total `git rev-list --count`, so a long history is never read as a whole.
- Added: The log filters by branch or tag, author and text in the message (fixed text, any case),
  besides the dates. The page, its size and the filters are view parameters
  (/(offset)/50/(limit)/25/(ref)/main/(author)/jane/(grep)/fix/(since)/2026-01-01/(until)/...),
  so a link reproduces the view; the form redirects to that address. Values are written into a
  link as ~XX for any character other than letters, digits, dot, dash, underscore and @.
- Updated: Every value reaches git as an argument of its own, without a shell
  (GitManager::git()), after a check (GitManagerLog): a branch or tag must be one of the
  repository's refs (for-each-ref), the revision stands between --end-of-options and --, dates
  must be real days, texts lose control characters and are cut at 200 characters.
- Updated: The filter no longer lives in the session; "Clear" is a link to the unfiltered log.
  "Last commit" in the HEAD card is HEAD's own date, whichever page is shown.
- Added: tests/unit/LogPagingTest.php (the paging maths, the view parameters and the git
  arguments, hostile values included). The new strings are translated in eng-US, ger-DE, esl-ES,
  esl-MX, heb-IL, lat-VA and tlh-QO.

2.0.20 (2026-10-17)
-------------------

- Updated: The dashboard, the commit details and the Backup page are built from the
  administration's shared components (cards, buttons, badges, notices, tables, filter bar, folds,
  facts, empty states) and its --a4-* tokens, so they follow admin4's dark mode: no more white
  cards and unreadable text on a dark page. What the components cannot do is
  design/standard/stylesheets/modules/git_manager.css (.a4-gm-* classes on the tokens only), loaded
  through design.ini [StylesheetSettings] BackendCSSFileList. The old git_manager.css is removed;
  a site that overrides one of the templates and uses its .gm-* classes keeps the markup but needs
  the new classes for the look.
- Updated: No inline script and no inline style is left in the templates. The behaviours are
  design/standard/javascript/git_manager.js (ezscript_require), with their texts as data-gm-*
  attributes of the page; the author initials take one of the chart colors by class.
- Added: The Git tab has its own left menu, menu.ini [Leftmenu_gitmanager] (Git Manager, Backup),
  on the dashboard and the commit details, with admin4's width switch like every other module
  (parts/gitmanager/menu.tpl). The Backup page stays under Setup.
- Updated: Every form, button name and confirmation is kept. New strings are translated in eng-US
  and ger-DE. tests/unit/AdminStylingTest.php checks the rules on the files.

2.0.19 (2026-10-17)
-------------------

- Fixed: The dashboard and the commit details page no longer write PHP 8.1 deprecations to the
  log: the output of a git command that prints nothing (null) and a missing Hash parameter are
  cast to a string before trim() and strlen().

Unreleased
----------

- Updated: The Git Manager dashboard and its commit details select the Git tab of the top menu,
  which the extension adds for them; they highlighted Setup. The left menu stays the Setup menu,
  where Git Manager and Backup are listed. The Backup pages stay under Setup.

2.0.17 (2026-10-08)
-------------------

- Updated: Backups, the mysqldump option file and the upstream status cache keep to the limits
  EZP_FILE_MODE_MAX and EZP_DIR_MODE_MAX of Exponential 6.0.15 (config.php). The backup folder
  (0700), the option file (0600), the status cache (0666) and the umask 077 of the backup command
  and the backup page go through the kernel helpers, which only narrow them: the private 0700 /
  0600 modes stay as they are, the status cache becomes 0660 under 0770 / 0660. Without the
  limits, or on a kernel without the helpers, every mode is that of before.

Unreleased (next release)
-------------------------

The Backup page, refactored: its warning follows the newest backup, the logic behind it is in
small tested classes, and the page has the look of the redesigned administration pages.

- Fixed: The backup warning is taken from the NEWEST backup. It took the oldest one, so a page
  with a backup of 22 days next to one of 3 months said "Your site backup captions are 3 months
  old old and outdated".
- Fixed: Ages are whole units with a singular: "1 month", not "1 months"; a year and a half is
  "1 year", not "18 months". The warning no longer says "old old".
- Fixed: A full site backup puts its site archive into the backup it has just made, by that
  backup's name. It took a second date(), so when the clock had moved on by a second the archive
  went into a folder of its own, or failed (web page and backup-create.php).
- Fixed: The AGPL compatible dumps (sql_agpl_*) can be downloaded; they were refused (404).
  agpl_compatible.txt is no longer offered as a download that did not work.
- Fixed: Downloads are streamed in pieces with the output buffers ended and the session closed,
  so an archive of several gigabytes no longer has to fit in memory and does not block the
  user's other pages while it is sent. On a persistent PHP server (Exponential Velocity), which
  keeps a whole response in memory, an archive above PersistentServerDownloadLimitMB (128) is
  refused with a message instead of exhausting the worker.
- Added: GitManagerBackupCatalogue (the backups on disk, newest first, path checks),
  GitManagerBackupFreshness (none / fresh / ageing / stale / future, from the newest backup)
  and GitManagerBackupMessages (the texts, chosen from the state), with PHPUnit tests
  (phpunit.xml.dist, tests/unit): no backups, one, several in any order, equal times, dates in
  the future, unreadable files, the threshold edges, time zones and the texts.
- Added: [BackupFreshnessSettings] in git_manager.ini: WarnAfterDays (7), StaleAfterDays (30),
  FutureToleranceMinutes (5). A backup dated in the future does not count as the newest.
- Updated: The Backup page is redesigned: one status block, four create forms, the backups as
  cards with a table of their archives; light and dark admin, narrow screens and 200 % zoom.
  Every text is translatable (German included). Every action redirects back to the page, so a
  reload repeats nothing. The template variables of 1.x are kept (oldest_warning now carries the
  newest backup's age).
- Updated: backup-list.php prints the status line of the page first.
