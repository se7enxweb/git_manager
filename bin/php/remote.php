#!/usr/bin/env php
<?php
/**
 * The remotes of the installation's git repository, from the command line:
 * the same checks as the dashboard (names and addresses validated, a push
 * never forced, git never waiting for a password).
 *
 * Usage (from the installation root):
 *   php extension/git_manager/bin/php/remote.php list
 *   php extension/git_manager/bin/php/remote.php add <name> <address>
 *   php extension/git_manager/bin/php/remote.php set-url <name> <address>
 *   php extension/git_manager/bin/php/remote.php rename <name> <new name>
 *   php extension/git_manager/bin/php/remote.php remove <name>
 *   php extension/git_manager/bin/php/remote.php fetch <name>
 *   php extension/git_manager/bin/php/remote.php push <name> [<branch>]   (default: the checked out branch)
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_remote.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\Remote::main( __FILE__ );
