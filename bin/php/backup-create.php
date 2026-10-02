#!/usr/bin/env php
<?php
/**
 * CLI Backup Creator
 *
 * Usage:
 *   php backup-create.php [type] [options]
 *
 * Types:
 *   full     - Create full backup (DB + var)
 *   fullsite - Create complete site backup (DB + var + site files)
 *   db       - Create database backup only
 *   var      - Create var directory backup only
 *
 * Options:
 *   -d "description"  - Add description
 *   -e                - Encrypt backup
 *   -p "passphrase"   - Encryption passphrase (visible to other accounts in the process list; prefer below)
 *   --passphrase-file <file>  - Read the passphrase from the first line of a file
 *   GIT_MANAGER_BACKUP_PASSPHRASE - or from this environment variable
 *   (with -e and none of these on a terminal, the passphrase is asked for, not echoed)
 *
 * Examples:
 *   php backup-create.php full
 *   php backup-create.php fullsite -d "Complete backup"
 *   php backup-create.php db -d "Before update"
 *   php backup-create.php full -e -p "mySecretPass123" -d "Encrypted backup"
 *   php backup-create.php var -d "Files only"
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname(__FILE__) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_backup-create.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\BackupCreate::main( __FILE__ );
