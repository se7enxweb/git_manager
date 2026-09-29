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
 *   -p "passphrase"   - Encryption passphrase (visible to other accounts in the process list; prefer below)
 *   --passphrase-file <file>  - Read the passphrase from the first line of a file
 *   GIT_MANAGER_BACKUP_PASSPHRASE - or from this environment variable
 *   (with -e and none of these on a terminal, the passphrase is asked for, not echoed)
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
    $cli->output("  -p \"passphrase\"   - Encryption passphrase (other accounts can see it in the process list)");
    $cli->output("  --passphrase-file <file>   - Read the passphrase from a file (safer)");
    $cli->output("  GIT_MANAGER_BACKUP_PASSPHRASE - Or from this environment variable; on a terminal it is asked for");
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
                $passphraseOnCommandLine = true;
                $i++;
            }
            break;
        case '--passphrase-file':
            if (isset($args[$i + 1])) {
                $passphraseFile = $args[$i + 1];
                $i++;
            }
            break;
    }
}

// The passphrase, safest first: a file, the environment, a hidden prompt; -p last.
if ($encrypt && $passphrase === '' && isset($passphraseFile)) {
    if (!is_readable($passphraseFile)) {
        $cli->error("Error: cannot read the passphrase file");
        $script->shutdown(1);
    }
    $passphrase = rtrim((string)strtok((string)file_get_contents($passphraseFile), "\n"), "\r");
}
if ($encrypt && $passphrase === '' && getenv('GIT_MANAGER_BACKUP_PASSPHRASE') !== false) {
    $passphrase = (string)getenv('GIT_MANAGER_BACKUP_PASSPHRASE');
}
if ($encrypt && $passphrase === '' && function_exists('posix_isatty') && posix_isatty(STDIN)) {
    fwrite(STDOUT, 'Passphrase: ');
    shell_exec('stty -echo 2>/dev/null');
    $passphrase = rtrim((string)fgets(STDIN), "\r\n");
    shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}
if (!empty($passphraseOnCommandLine)) {
    $cli->warning("The passphrase was given with -p: other accounts on this server can see it in the process list. Prefer --passphrase-file or GIT_MANAGER_BACKUP_PASSPHRASE.");
}

// Validate encryption
if ($encrypt && empty($passphrase)) {
    $cli->error("Error: Encryption enabled but no passphrase provided. Use --passphrase-file, GIT_MANAGER_BACKUP_PASSPHRASE or -p.");
    $script->shutdown(1);
}

// Every file the backup writes is its owner's only.
umask(0077);

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
                mkdir($captionDir, 0700, true);
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
                mkdir($captionDir, 0700, true);
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
                $siteCmd = 'cd ' . escapeshellarg($realPath) . ' && tar -czf ' . escapeshellarg($siteFile) . ' ' . $excludes . ' ./extension ./settings ./config.php ./config.php-RECOMMENDED 2>&1';
                
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
