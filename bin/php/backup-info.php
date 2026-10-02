#!/usr/bin/env php
<?php
/**
 * CLI Backup Info
 *
 * Usage:
 *   php backup-info.php <timestamp>
 *
 * Examples:
 *   php backup-info.php 2026-06-21_20-20-49
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname(__FILE__) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_backup-info.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\BackupInfo::main( __FILE__ );
