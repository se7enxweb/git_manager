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
 */

// Bootstrap eZ Publish
require_once dirname(__FILE__) . '/../../../../autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => 'Show backup caption details',
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
]);
$script->startup();
$script->initialize();

// Parse arguments
$args = $_SERVER['argv'];
array_shift($args);

if (count($args) < 1) {
    $cli->error("Error: Missing timestamp");
    $cli->output("\nUsage: php backup-info.php <timestamp>");
    $cli->output("Example: php backup-info.php 2026-06-21_20-20-49");
    $script->shutdown(1);
}

$timestamp = $args[0];

// Get backup info
$backup = new BackupManager();
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
    $script->shutdown(1);
}

// Display detailed info
$cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
$cli->output("📦 BACKUP CAPTION DETAILS");
$cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
$cli->output("");
$cli->output("Timestamp:   {$captionInfo['timestamp']}");
$cli->output("Date:        {$captionInfo['date']}");

// Show age with color indicator
$ageColor = $captionInfo['time_ago']['color'];
$ageDisplay = $captionInfo['time_ago']['display'];
$colorIndicator = '🟢'; // Green
if (strpos($ageColor, 'f39c12') !== false) $colorIndicator = '🟠'; // Orange
if (strpos($ageColor, 'e67e22') !== false) $colorIndicator = '🟠'; // Dark orange
if (strpos($ageColor, 'e74c3c') !== false || strpos($ageColor, 'c0392b') !== false) $colorIndicator = '🔴'; // Red

$cli->output("Age:         {$colorIndicator} {$ageDisplay}");

if (!empty($captionInfo['description'])) {
    $cli->output("Description: {$captionInfo['description']}");
}

$cli->output("Total Size:  {$captionInfo['total_size_formatted']}");
$cli->output("Files:       " . count($captionInfo['files']));
$cli->output("");

if (!empty($captionInfo['files'])) {
    $cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    $cli->output("FILES:");
    $cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    foreach ($captionInfo['files'] as $file) {
        $icon = $file['type'] === 'database' ? '🗄️ ' : '📁';
        $encrypted = $file['encrypted'] ? ' 🔒 ENCRYPTED' : '';
        
        $cli->output("");
        $cli->output("{$icon} {$file['name']}{$encrypted}");
        $cli->output("   Type: " . ucfirst($file['type']));
        $cli->output("   Size: {$file['size_formatted']} (" . number_format($file['size']) . " bytes)");
        
        // Get file path
        $ini = eZINI::instance('git_manager.ini');
        $backupPath = $ini->variable('GitManagerSettings', 'BackupPath');
        
        $realPath = getcwd();
        $gitDir = $realPath . '/.git';
        if (is_link($gitDir)) {
            $symlinkTarget = readlink($gitDir);
            if ($symlinkTarget[0] !== '/') {
                $symlinkTarget = realpath('./' . $symlinkTarget);
            }
            if (substr($symlinkTarget, -5) === '/.git') {
                $realPath = substr($symlinkTarget, 0, -5);
            }
        }
        
        $filePath = $realPath . '/' . $backupPath . '/' . $timestamp . '/' . $file['name'];
        if (file_exists($filePath)) {
            $cli->output("   Path: {$filePath}");
        }
    }
}

$cli->output("");
$cli->output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

$script->shutdown(0);
