#!/usr/bin/env php
<?php
/**
 * CLI Backup Deleter
 *
 * Usage:
 *   php backup-delete.php <timestamp> [options]
 *
 * Options:
 *   -f  - Force deletion without confirmation
 *   -a  - Delete all backups (use with caution!)
 *
 * Examples:
 *   php backup-delete.php 2026-06-21_20-20-49
 *   php backup-delete.php 2026-06-21_20-20-49 -f
 *   php backup-delete.php -a
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname(__FILE__) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_backup-delete.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\BackupDelete::main( __FILE__ );
