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

# Maximum number of backups to keep (0 = unlimited)
MaxBackups=36

# Compress backups with gzip
CompressBackups=enabled

# Enable GPG encryption for backups
# When enabled, backups can be encrypted with a passphrase
EnableEncryption=enabled

# Delete unencrypted files after encryption (if encryption is used)
DeleteUnencryptedAfterEncryption=enabled

*/ ?>