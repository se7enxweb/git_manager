#!/usr/bin/env php
<?php
/**
 * CLI Backup Lister
 *
 * Usage:
 *   php backup-list.php [options]
 *
 * Options:
 *   -v  - Verbose (show file details)
 *   -s  - Show sizes in human readable format
 *
 * Examples:
 *   php backup-list.php
 *   php backup-list.php -v
 *   php backup-list.php -v -s
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname(__FILE__) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_backup-list.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\BackupList::main( __FILE__ );
