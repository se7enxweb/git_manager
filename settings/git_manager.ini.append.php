<?php /* #?ini charset="utf-8"?

[GitManagerSettings]
# Path where backups/captions will be stored
BackupPath=var/site/backups/captions

# MySQL/MariaDB database dump command
# Available placeholders: {host}, {port}, {user}, {password}, {database}, {output_file}
MySQLDumpCommand=mysqldump --host={host} --port={port} --user={user} --password={password} --databases {database} --single-transaction --quick --lock-tables=false --routines --triggers --events

# Directories to exclude from var backup
VarExcludeDirs[]
VarExcludeDirs[]=cache
VarExcludeDirs[]=log
VarExcludeDirs[]=site/backups
# The session files of signed-in users: a backup that has them is a way in.
VarExcludeDirs[]=site/sessions

# Maximum number of backups to keep (0 = unlimited)
MaxBackups=36

# Compress backups with gzip
CompressBackups=enabled

# Enable GPG encryption for backups
# When enabled, backups can be encrypted with a passphrase
EnableEncryption=enabled

# Delete unencrypted files after encryption (if encryption is used)
DeleteUnencryptedAfterEncryption=enabled

# The largest backup file the download sends through a persistent PHP
# server (Exponential Velocity), in megabytes. Such a server keeps the whole
# response in memory before it sends it, so a larger file is refused there
# with a message naming the size; download it through Apache or PHP-FPM, which
# stream it. 0 = no limit.
PersistentServerDownloadLimitMB=128

# How old the NEWEST backup may be before the Backup page warns. Only the
# newest backup counts: older ones next to it are no reason for a warning.
[BackupFreshnessSettings]
# Older than this (days, decimals allowed): "ageing", an orange warning.
WarnAfterDays=7
# Older than this (days): "stale", a red warning. At least WarnAfterDays.
StaleAfterDays=30
# A backup dated this many minutes or more ahead of the server clock does
# not count as the newest (a wrong clock or time zone must not hide that the
# real backups are old); the page says so.
FutureToleranceMinutes=5

# The dashboard's "Upstream" card: the checked out branch against its branch
# on a remote, the commits that are missing either way, and "Fetch now".
[UpstreamSettings]
# The remote to compare with and fetch.
Remote=origin
# The branch on that remote. Empty: the checked out branch's upstream when it
# is on Remote, else the branch of the same name there.
Branch=
# Seconds a fetch may take before it is stopped.
FetchTimeout=60
# When the remote's SSH address does not work for the user the site runs as
# (a host alias of another user's ~/.ssh/config, no key) and the repository
# is on GitHub: fetch it anonymously over HTTPS into the same remote-tracking
# branches (enabled/disabled). A private repository then still fails.
HttpsFallback=enabled
# The repository's address in a browser, for the links to the commits.
# Empty: derived from the remote's address (GitHub, GitLab and other hosts).
WebUrl=
# The most commits listed each way.
MaxCommits=100
# Seconds the computed status is kept at most. It is computed again at once
# when HEAD, the branch, the remote-tracking branch, FETCH_HEAD or the index
# change; this limit is for the counts of uncommitted files only.
StatusCacheTTL=60

*/ ?>