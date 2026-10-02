<?php
/**
 * The code of extension/git_manager/bin/php/backup-list.php, moved into a class (#207 stage 1). The file extension/git_manager/bin/php/backup-list.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/bin/php/backup-list.php:
 *
 *
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
 */
/*
 * Further original header of extension/git_manager/bin/php/backup-list.php:
 *
 * Bootstrap eZ Publish
 */

namespace Exponential\Command\Extension\GitManager
{

class BackupList extends \Exponential\Runnable\Command
{
    public function run()
    {
        // the script's variables were globals; functions of the script read them with "global"
        foreach ( array( 'ageColor', 'ageDisplay', 'backup', 'caption', 'captions', 'cli', 'colorIndicator', 'encrypted', 'file', 'icon', 'script', 'showSizes', 'size', 'verbose' ) as $__name )
            ${$__name} = &$GLOBALS[$__name];
        unset( $__name );

        $cli = \eZCLI::instance();
        $script = \eZScript::instance([
            'description' => 'List backup captions',
            'use-session' => false,
            'use-modules' => true,
            'use-extensions' => true
        ]);
        $script->startup();
        $script->initialize();

        // Parse options
        $verbose = in_array('-v', $_SERVER['argv']);
        $showSizes = in_array('-s', $_SERVER['argv']);

        // Get backups
        $backup = new \BackupManager();
        $captions = $backup->listCaptions();

        if (empty($captions)) {
            $cli->warning("No backups found");
            $script->shutdown(0);
        }

        $cli->output("Found " . count($captions) . " backup(s):\n");

        foreach ($captions as $caption) {
            $cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $cli->output("📦 Timestamp: " . $caption['timestamp']);
            $cli->output("📅 Date: " . $caption['date']);
            
            // Show age with color indicator
            $ageColor = $caption['time_ago']['color'];
            $ageDisplay = $caption['time_ago']['display'];
            $colorIndicator = '🟢'; // Green
            if (strpos($ageColor, 'f39c12') !== false) $colorIndicator = '🟠'; // Orange
            if (strpos($ageColor, 'e67e22') !== false) $colorIndicator = '🟠'; // Dark orange
            if (strpos($ageColor, 'e74c3c') !== false || strpos($ageColor, 'c0392b') !== false) $colorIndicator = '🔴'; // Red
            
            $cli->output("⏱  Age: {$colorIndicator} {$ageDisplay}");
            
            if (!empty($caption['description'])) {
                $cli->output("📝 Description: " . $caption['description']);
            }
            
            if ($showSizes) {
                $cli->output("💾 Total Size: " . $caption['total_size_formatted']);
            }
            
            if ($verbose && !empty($caption['files'])) {
                $cli->output("\nFiles:");
                foreach ($caption['files'] as $file) {
                    $icon = $file['type'] === 'database' ? '🗄️ ' : '📁';
                    $encrypted = $file['encrypted'] ? ' 🔒' : '';
                    $size = $showSizes ? " ({$file['size_formatted']})" : '';
                    $cli->output("  {$icon} {$file['name']}{$size}{$encrypted}");
                }
            } else {
                $cli->output("Files: " . count($caption['files']));
            }
            
            $cli->output("");
        }

        $cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $cli->output("Total: " . count($captions) . " backup(s)");

        $script->shutdown(0);
    }
}

}
