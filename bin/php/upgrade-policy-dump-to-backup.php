#!/usr/bin/env php
<?php
/**
 * Renames the role policies git_manager/dump to git_manager/backup.
 *
 * Before 2.0.4 the backup view was git_manager/dump and its policy function was
 * called dump. The function is now backup; dump is still accepted, so nothing
 * breaks without this script, but a role shows the old name until it is run.
 * Limitations of each policy stay with it.
 *
 * Usage (from the installation root):
 *   php extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php [--dry-run]
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_upgrade-policy-dump-to-backup.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\UpgradePolicyDumpToBackup::main( __FILE__ );
