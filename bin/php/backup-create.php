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
 *   -p "passphrase"   - Encryption passphrase (required with -e)
 * 
 * Examples:
 *   php backup-create.php full
 *   php backup-create.php fullsite -d "Complete backup"
 *   php backup-create.php db -d "Before update"
 *   php backup-create.php full -e -p "mySecretPass123" -d "Encrypted backup"
 *   php backup-create.php var -d "Files only"
 */

// Bootstrap eZ Publish
require_once dirname(__FILE__) . '/../../../../autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => 'Create backup captions',
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
]);
$script->startup();
$script->initialize();

// Parse arguments
$args = $_SERVER['argv'];
array_shift($args); // Remove script name

if (count($args) < 1) {
    $cli->error("Error: Missing backup type");
    $cli->output("\nUsage: php backup-create.php [type] [options]");
    $cli->output("Types: full, fullsite, db, var");
    $cli->output("\nOptions:");
    $cli->output("  -d \"description\"  - Add description");
    $cli->output("  -e                - Encrypt backup");
    $cli->output("  -p \"passphrase\"   - Encryption passphrase (required with -e)");
    $script->shutdown(1);
}

$type = strtolower($args[0]);
if (!in_array($type, ['full', 'fullsite', 'db', 'var'])) {
    $cli->error("Error: Invalid type '{$type}'. Must be: full, fullsite, db, or var");
    $script->shutdown(1);
}

// Parse options
$description = '';
$encrypt = false;
$passphrase = '';

for ($i = 1; $i < count($args); $i++) {
    switch ($args[$i]) {
        case '-d':
            if (isset($args[$i + 1])) {
                $description = $args[$i + 1];
                $i++;
            }
            break;
        case '-e':
            $encrypt = true;
            break;
        case '-p':
            if (isset($args[$i + 1])) {
                $passphrase = $args[$i + 1];
                $i++;
            }
            break;
    }
}

// Validate encryption
if ($encrypt && empty($passphrase)) {
    $cli->error("Error: Encryption enabled but no passphrase provided. Use -p \"passphrase\"");
    $script->shutdown(1);
}

// Create backup
$backup = new BackupManager();
$timestamp = date('Y-m-d_H-i-s');

$cli->output("Creating {$type} backup...");
if ($encrypt) {
    $cli->warning("Encryption enabled with passphrase");
}

try {
    switch ($type) {
        case 'full':
            $result = $backup->createFullCaption($description, $encrypt, $passphrase);
            break;
            
        case 'db':
            $ini = eZINI::instance('git_manager.ini');
            $backupPath = $ini->variable('GitManagerSettings', 'BackupPath');
            
            // Resolve installation path
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
            
            $captionDir = $realPath . '/' . $backupPath . '/' . $timestamp;
            if (!is_dir($captionDir)) {
                mkdir($captionDir, 0775, true);
            }
            
            if (!empty($description)) {
                file_put_contents($captionDir . '/description.txt', $description);
            }
            
            $result = $backup->createDatabaseDump($captionDir, $encrypt, $passphrase);
            break;
            
        case 'var':
            $ini = eZINI::instance('git_manager.ini');
            $backupPath = $ini->variable('GitManagerSettings', 'BackupPath');
            
            // Resolve installation path
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
            
            $captionDir = $realPath . '/' . $backupPath . '/' . $timestamp;
            if (!is_dir($captionDir)) {
                mkdir($captionDir, 0775, true);
            }
            
            if (!empty($description)) {
                file_put_contents($captionDir . '/description.txt', $description);
            }
            
            $result = $backup->createVarBackup($captionDir, $encrypt, $passphrase);
            break;
            
        case 'fullsite':
            // First create full caption (DB + var)
            $result = $backup->createFullCaption($description, $encrypt, $passphrase);
            
            if ($result['success']) {
                // Now add site backup
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
                
                $captionDir = $realPath . '/' . $backupPath . '/' . $timestamp;
                $siteFile = $captionDir . '/site_' . $timestamp . '.tar.gz';
                $excludes = '--exclude=\'./var/*\' --exclude=\'./.git\' --exclude=\'./vendor/composer\' --exclude=\'./autoload/*\'';
                $siteCmd = "cd {$realPath} && tar -czf {$siteFile} {$excludes} ./extension ./settings ./config.php ./config.php-RECOMMENDED 2>&1";
                
                exec($siteCmd, $siteOutput, $siteReturn);
                
                if ($siteReturn === 0 && file_exists($siteFile)) {
                    if ($encrypt && !empty($passphrase)) {
                        $gpg = new GPGEncryption();
                        $deleteOriginal = $ini->variable('GitManagerSettings', 'DeleteUnencryptedAfterEncryption') === 'enabled';
                        $encResult = $gpg->encryptFile($siteFile, $passphrase, $deleteOriginal);
                        
                        if (!$encResult['success']) {
                            $cli->warning("Site backup created but encryption failed");
                        }
                    }
                    $result['message'] = 'Full site backup (DB + var + site files) created successfully';
                } else {
                    $cli->warning("Full caption created but site archive failed: " . implode("\n", $siteOutput));
                }
            }
            break;
    }
    
    if ($result['success']) {
        $cli->output("\n✓ Success!");
        $cli->output("Timestamp: {$timestamp}");
        if (isset($result['file'])) {
            $cli->output("File: {$result['file']}");
            $cli->output("Size: " . round($result['size'] / 1024 / 1024, 2) . " MB");
        }
        if ($result['encrypted']) {
            $cli->warning("⚠ Encrypted - Remember your passphrase!");
        }
        $script->shutdown(0);
    } else {
        $cli->error("Error: {$result['message']}");
        $script->shutdown(1);
    }
    
} catch (Exception $e) {
    $cli->error("Exception: " . $e->getMessage());
    $script->shutdown(1);
}

$script->shutdown();
