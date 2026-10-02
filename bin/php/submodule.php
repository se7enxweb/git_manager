#!/usr/bin/env php
<?php
/**
 * The submodules of the installation's git repository, from the command line:
 * the same checks as the dashboard (paths, addresses and branches validated,
 * git never waiting for a password). Adding, changing and removing stage the
 * change; commit it to keep it.
 *
 * Usage (from the installation root):
 *   php extension/git_manager/bin/php/submodule.php list
 *   php extension/git_manager/bin/php/submodule.php add <address> <path> [<branch>]
 *   php extension/git_manager/bin/php/submodule.php set-url <name> <address>
 *   php extension/git_manager/bin/php/submodule.php set-branch <name> [<branch>]   (none: follow no branch)
 *   php extension/git_manager/bin/php/submodule.php update [<name>]              (none: every submodule)
 *   php extension/git_manager/bin/php/submodule.php remove <name>
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package kernel
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

// The code is in extension/git_manager/classes/runnable/commands/php_submodule.php (#207); this file is the entry point.
\Exponential\Command\Extension\GitManager\Submodule::main( __FILE__ );
