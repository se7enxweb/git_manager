Git Manager changelog
=====================

The changes of each release, newest first. Earlier releases are described in their GitHub
release notes (https://github.com/se7enxweb/git_manager/releases).

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
