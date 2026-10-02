<?php
/**
 * The code of extension/git_manager/bin/php/backup-delete.php, moved into a class (#207 stage 1). The file extension/git_manager/bin/php/backup-delete.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/bin/php/backup-delete.php:
 *
 *
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
 */
/*
 * Further original header of extension/git_manager/bin/php/backup-delete.php:
 *
 * Bootstrap eZ Publish
 */

namespace Exponential\Command\Extension\GitManager
{

class BackupDelete extends \Exponential\Runnable\Command
{
    public function run()
    {
        // the script's variables were globals; functions of the script read them with "global"
        foreach ( array( 'args', 'backup', 'caption', 'captionInfo', 'captions', 'cli', 'confirmation', 'deleteAll', 'deleted', 'failed', 'force', 'found', 'handle', 'result', 'script', 'timestamp' ) as $__name )
            ${$__name} = &$GLOBALS[$__name];
        unset( $__name );

        $cli = $this->cli();
        $script = $this->script([
            'description' => 'Delete backup captions',
            'use-session' => false,
            'use-modules' => true,
            'use-extensions' => true
        ]);
        $script->startup();
        $script->initialize();

        // Parse arguments
        $args = $_SERVER['argv'];
        array_shift($args); // Remove script name

        $force = in_array('-f', $args);
        $deleteAll = in_array('-a', $args);

        // Remove flags from args
        $args = array_filter($args, function($arg) {
            return !in_array($arg, ['-f', '-a']);
        });
        $args = array_values($args);

        $backup = new \BackupManager();

        // Delete all backups
        if ($deleteAll) {
            $captions = $backup->listCaptions();
            
            if (empty($captions)) {
                $cli->warning("No backups to delete");
                $script->shutdown(0);
            }
            
            $cli->warning("WARNING: About to delete ALL " . count($captions) . " backup(s)!");
            
            if (!$force) {
                $cli->output("\nBackups to be deleted:");
                foreach ($captions as $caption) {
                    $cli->output("  - {$caption['timestamp']} ({$caption['total_size_formatted']})");
                }
                
                $cli->output("\nType 'DELETE ALL' to confirm:");
                $handle = fopen("php://stdin", "r");
                $confirmation = trim(fgets($handle));
                fclose($handle);
                
                if ($confirmation !== 'DELETE ALL') {
                    $cli->error("Deletion cancelled");
                    $script->shutdown(1);
                }
            }
            
            $cli->output("\nDeleting backups...");
            $deleted = 0;
            $failed = 0;
            
            foreach ($captions as $caption) {
                $result = $backup->deleteCaption($caption['timestamp']);
                if ($result['success']) {
                    $cli->output("✓ Deleted: {$caption['timestamp']}");
                    $deleted++;
                } else {
                    $cli->error("✗ Failed: {$caption['timestamp']} - {$result['message']}");
                    $failed++;
                }
            }
            
            $cli->output("\nDeleted: {$deleted}, Failed: {$failed}");
            $script->shutdown($failed > 0 ? 1 : 0);
        }

        // Delete specific backup
        if (count($args) < 1) {
            $cli->error("Error: Missing timestamp");
            $cli->output("\nUsage: php backup-delete.php <timestamp> [options]");
            $cli->output("   or: php backup-delete.php -a  (delete all)");
            $script->shutdown(1);
        }

        $timestamp = $args[0];

        // Validate timestamp format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $timestamp)) {
            $cli->error("Error: Invalid timestamp format");
            $cli->output("Expected format: YYYY-MM-DD_HH-MM-SS");
            $cli->output("Example: 2026-06-21_20-20-49");
            $script->shutdown(1);
        }

        // Check if backup exists
        $captions = $backup->listCaptions();
        $found = false;
        $captionInfo = null;

        foreach ($captions as $caption) {
            if ($caption['timestamp'] === $timestamp) {
                $found = true;
                $captionInfo = $caption;
                break;
            }
        }

        if (!$found) {
            $cli->error("Error: Backup not found: {$timestamp}");
            $cli->output("\nAvailable backups:");
            foreach ($captions as $caption) {
                $cli->output("  - {$caption['timestamp']}");
            }
            $script->shutdown(1);
        }

        // Confirm deletion
        if (!$force) {
            $cli->warning("About to delete backup: {$timestamp}");
            if (!empty($captionInfo['description'])) {
                $cli->output("Description: {$captionInfo['description']}");
            }
            $cli->output("Files: " . count($captionInfo['files']));
            $cli->output("Size: {$captionInfo['total_size_formatted']}");
            $cli->output("\nType 'yes' to confirm:");
            
            $handle = fopen("php://stdin", "r");
            $confirmation = strtolower(trim(fgets($handle)));
            fclose($handle);
            
            if ($confirmation !== 'yes') {
                $cli->error("Deletion cancelled");
                $script->shutdown(1);
            }
        }

        // Delete backup
        $result = $backup->deleteCaption($timestamp);

        if ($result['success']) {
            $cli->output("✓ Successfully deleted: {$timestamp}");
            $script->shutdown(0);
        } else {
            $cli->error("✗ Failed to delete: {$result['message']}");
            $script->shutdown(1);
        }

        $script->shutdown();
    }
}

}
