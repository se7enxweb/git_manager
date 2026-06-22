<?php
/**
 * @package GitManager
 * @class   BackupManager
 * @author  SE7ENX
 * @date    2026-06-21
 **/

class BackupManager
{
    private $backupPath;
    private $varExcludeDirs;
    private $maxBackups;
    private $realInstallPath;
    
    public function __construct() {
        $ini = eZINI::instance( 'git_manager.ini' );
        $this->varExcludeDirs = $ini->variable( 'GitManagerSettings', 'VarExcludeDirs' );
        $this->maxBackups = $ini->variable( 'GitManagerSettings', 'MaxBackups' );
        
        // Resolve real installation path (follow symlinks)
        $this->realInstallPath = $this->resolveInstallPath();
        
        // Set backup path relative to real installation
        $configuredPath = $ini->variable( 'GitManagerSettings', 'BackupPath' );
        $this->backupPath = $this->realInstallPath . '/' . $configuredPath;
    }
    
    /**
     * Resolve the actual installation path, following symlinks if necessary
     * 
     * @return string The real path to the installation root
     */
    private function resolveInstallPath() {
        $currentPath = './';
        $gitDir = $currentPath . '.git';
        
        // Check if .git exists and is a symlink
        if( is_link( $gitDir ) ) {
            // Read the symlink target
            $symlinkTarget = readlink( $gitDir );
            
            // If it's a relative path, resolve it relative to current directory
            if( $symlinkTarget[0] !== '/' ) {
                $symlinkTarget = realpath( $currentPath . $symlinkTarget );
            }
            
            // Remove the .git suffix to get the repository root
            if( substr( $symlinkTarget, -5 ) === '/.git' ) {
                $repoRoot = substr( $symlinkTarget, 0, -5 );
                return $repoRoot;
            }
        }
        
        // If not a symlink or couldn't resolve, use realpath of current directory
        return realpath( $currentPath );
    }
    
    /**
     * Get database connection settings from site.ini
     * 
     * @return array Database settings
     */
    private function getDatabaseSettings() {
        $ini = eZINI::instance( 'site.ini' );
        
        return array(
            'host'     => $ini->variable( 'DatabaseSettings', 'Server' ),
            'port'     => $ini->variable( 'DatabaseSettings', 'Port' ),
            'user'     => $ini->variable( 'DatabaseSettings', 'User' ),
            'password' => $ini->variable( 'DatabaseSettings', 'Password' ),
            'database' => $ini->variable( 'DatabaseSettings', 'Database' )
        );
    }
    
    /**
     * Create a full caption (DB + var directory)
     * 
     * @param string $description Optional description for this caption
     * @param bool $encrypt Whether to encrypt the backup files
     * @param string $passphrase Passphrase for encryption (required if $encrypt is true)
     * @return array Result with success status and message
     */
    public function createFullCaption( $description = '', $encrypt = false, $passphrase = '', $agplCompatible = false ) {
        $timestamp = date('Y-m-d_H-i-s');
        $captionDir = $this->backupPath . '/' . $timestamp;
        
        // Create caption directory
        if( !$this->createDirectory( $captionDir ) ) {
            return array(
                'success' => false,
                'message' => 'Failed to create caption directory: ' . $captionDir
            );
        }
        
        // Save description if provided
        if( !empty($description) ) {
            file_put_contents( $captionDir . '/description.txt', $description );
        }
        
        // Bundle license files (GPL v2 + AGPL v3) into caption dir
        $this->writeLicenseFiles( $captionDir );
        
        // Create database dump
        $dbResult = $this->createDatabaseDump( $captionDir, $encrypt, $passphrase, $agplCompatible );
        if( !$dbResult['success'] ) {
            return $dbResult;
        }
        
        // Create var directory backup
        $varResult = $this->createVarBackup( $captionDir, $encrypt, $passphrase );
        if( !$varResult['success'] ) {
            return $varResult;
        }
        
        // Cleanup old backups if needed
        $this->cleanupOldBackups();
        
        return array(
            'success' => true,
            'message' => 'Caption created successfully: ' . $timestamp . ($encrypt ? ' (encrypted)' : '') . ($agplCompatible ? ' [AGPL Compatible]' : ''),
            'timestamp' => $timestamp,
            'db_file' => $dbResult['file'],
            'var_file' => $varResult['file'],
            'encrypted' => $encrypt,
            'agpl_compatible' => $agplCompatible
        );
    }
    
    /**
     * Create database dump (schema, data, combined)
     * 
     * @param string $outputDir Directory to store dump files
     * @param bool $encrypt Whether to encrypt the backup file
     * @param string $passphrase Passphrase for encryption
     * @return array Result with success status and file path
     */
    public function createDatabaseDump( $outputDir, $encrypt = false, $passphrase = '', $agplCompatible = false ) {
        $dbSettings = $this->getDatabaseSettings();
        
        if( empty($dbSettings['database']) ) {
            return array(
                'success' => false,
                'message' => 'Database settings not found'
            );
        }
        
        $ini = eZINI::instance( 'git_manager.ini' );
        $dumpCommand = $ini->variable( 'GitManagerSettings', 'MySQLDumpCommand' );
        
        // Create SQL dump directory
        $sqlDir = $outputDir . '/sql';
        if( !$this->createDirectory( $sqlDir ) ) {
            return array(
                'success' => false,
                'message' => 'Failed to create SQL directory'
            );
        }
        
        // Bundle license files if not already written (e.g. DB-only captions bypass createFullCaption)
        if( !is_dir($outputDir . '/licenses') ) {
            $this->writeLicenseFiles( $outputDir );
        }
        
        // Dump schema only
        $schemaFile = $sqlDir . '/schema.sql';
        $schemaCmd = str_replace(
            array('{host}', '{port}', '{user}', '{password}', '{database}', '{output_file}'),
            array($dbSettings['host'], $dbSettings['port'], $dbSettings['user'], $dbSettings['password'], $dbSettings['database'], $schemaFile),
            $dumpCommand
        );
        $schemaCmd .= ' --no-data > ' . escapeshellarg($schemaFile) . ' 2>&1';
        
        // Dump data only
        $dataFile = $sqlDir . '/data.sql';
        $dataCmd = str_replace(
            array('{host}', '{port}', '{user}', '{password}', '{database}', '{output_file}'),
            array($dbSettings['host'], $dbSettings['port'], $dbSettings['user'], $dbSettings['password'], $dbSettings['database'], $dataFile),
            $dumpCommand
        );
        $dataCmd .= ' --no-create-info > ' . escapeshellarg($dataFile) . ' 2>&1';
        
        // Dump complete (schema + data)
        $completeFile = $sqlDir . '/complete.sql';
        $completeCmd = str_replace(
            array('{host}', '{port}', '{user}', '{password}', '{database}', '{output_file}'),
            array($dbSettings['host'], $dbSettings['port'], $dbSettings['user'], $dbSettings['password'], $dbSettings['database'], $completeFile),
            $dumpCommand
        );
        $completeCmd .= ' > ' . escapeshellarg($completeFile) . ' 2>&1';
        
        // Execute dumps
        exec( $schemaCmd, $schemaOutput, $schemaReturn );
        exec( $dataCmd, $dataOutput, $dataReturn );
        exec( $completeCmd, $completeOutput, $completeReturn );
        
        if( $schemaReturn !== 0 || $dataReturn !== 0 || $completeReturn !== 0 ) {
            return array(
                'success' => false,
                'message' => 'Database dump failed: ' . implode("\n", array_merge($schemaOutput, $dataOutput, $completeOutput))
            );
        }
        
        // Create tarball (include licenses/ if present)
        $timestamp = basename($outputDir);
        $tarFile = $outputDir . '/sql_' . $timestamp . '.tar.gz';
        $tarPaths = 'sql/' . ( is_dir($outputDir . '/licenses') ? ' licenses/' : '' );
        $tarCmd = 'cd ' . escapeshellarg($outputDir) . ' && tar -czf ' . escapeshellarg(basename($tarFile)) . ' ' . $tarPaths . ' 2>&1';
        exec( $tarCmd, $tarOutput, $tarReturn );
        
        if( $tarReturn !== 0 ) {
            return array(
                'success' => false,
                'message' => 'Failed to create SQL tarball: ' . implode("\n", $tarOutput)
            );
        }
        
        // Remove uncompressed SQL files
        exec( 'rm -rf ' . escapeshellarg($sqlDir) );
        
        // Create AGPL-compatible sanitized dump if requested
        if( $agplCompatible ) {
            // Re-extract complete.sql, sanitize it, repackage as agpl_sql_*.tar.gz
            $agplSqlDir = $outputDir . '/sql_agpl';
            $this->createDirectory( $agplSqlDir );
            
            // Extract original tar to get complete.sql
            exec( 'cd ' . escapeshellarg($outputDir) . ' && tar -xzf ' . escapeshellarg(basename($tarFile)) . ' sql/complete.sql 2>&1' );
            $rawComplete = $outputDir . '/sql/complete.sql';
            
            if( file_exists($rawComplete) ) {
                $sanitizer = new AGPLDumpSanitizer();
                $agplSqlFile = $agplSqlDir . '/complete_agpl_compatible.sql';
                $sanitizeResult = $sanitizer->sanitize( $rawComplete, $agplSqlFile );
                
                if( $sanitizeResult['success'] ) {
                    $agplTarFile = $outputDir . '/sql_agpl_' . $timestamp . '.tar.gz';
                    $agplTarPaths = 'sql_agpl/' . ( is_dir($outputDir . '/licenses') ? ' licenses/' : '' );
                    exec( 'cd ' . escapeshellarg($outputDir) . ' && tar -czf ' . escapeshellarg(basename($agplTarFile)) . ' ' . $agplTarPaths . ' 2>&1' );
                    
                    // Write AGPL marker and sanitization log
                    $captionDir = $outputDir;
                    $agplLog = implode("\n", $sanitizeResult['log']);
                    file_put_contents( $captionDir . '/agpl_compatible.txt', 
                        "AGPL Compatible Release\nSanitized: " . date('Y-m-d H:i:s') . "\n\nSanitization log:\n" . $agplLog
                    );
                }
                exec( 'rm -rf ' . escapeshellarg($outputDir . '/sql') );
            }
            exec( 'rm -rf ' . escapeshellarg($agplSqlDir) );
        }
        
        // Encrypt if requested
        if( $encrypt && !empty($passphrase) ) {
            $gpg = new GPGEncryption();
            $ini = eZINI::instance( 'git_manager.ini' );
            $deleteOriginal = $ini->variable( 'GitManagerSettings', 'DeleteUnencryptedAfterEncryption' ) === 'enabled';
            
            $encResult = $gpg->encryptFile( $tarFile, $passphrase, $deleteOriginal );
            if( !$encResult['success'] ) {
                return $encResult;
            }
            
            return array(
                'success' => true,
                'file' => basename($encResult['file']),
                'size' => $encResult['size'],
                'encrypted' => true
            );
        }
        
        return array(
            'success' => true,
            'file' => basename($tarFile),
            'size' => filesize($tarFile),
            'encrypted' => false
        );
    }
    
    /**
     * Create var directory backup (excluding cache, log, backups)
     * 
     * @param string $outputDir Directory to store backup file
     * @param bool $encrypt Whether to encrypt the backup file
     * @param string $passphrase Passphrase for encryption
     * @return array Result with success status and file path
     */
    public function createVarBackup( $outputDir, $encrypt = false, $passphrase = '' ) {
        // Ensure output directory exists
        if( !$this->createDirectory( $outputDir ) ) {
            return array(
                'success' => false,
                'message' => 'Failed to create output directory: ' . $outputDir
            );
        }
        
        $varDir = $this->realInstallPath . '/var';
        $timestamp = basename($outputDir);
        $tarFile = $outputDir . '/var_' . $timestamp . '.tar.gz';
        
        // Build exclude options
        $excludes = '';
        foreach( $this->varExcludeDirs as $excludeDir ) {
            $excludes .= ' --exclude=' . escapeshellarg('var/' . $excludeDir);
        }
        
        // Create tarball from real installation path, include licenses/ from caption dir if present
        $tarCmd = 'cd ' . escapeshellarg($this->realInstallPath) . ' && tar -czf ' . escapeshellarg($tarFile) . $excludes . ' var/';
        if( is_dir($outputDir . '/licenses') ) {
            $tarCmd .= ' -C ' . escapeshellarg($outputDir) . ' licenses/';
        }
        $tarCmd .= ' 2>&1';
        exec( $tarCmd, $tarOutput, $tarReturn );
        
        if( $tarReturn !== 0 ) {
            return array(
                'success' => false,
                'message' => 'Failed to create var backup: ' . implode("\n", $tarOutput)
            );
        }
        
        if( !file_exists($tarFile) ) {
            return array(
                'success' => false,
                'message' => 'Tar file was not created: ' . $tarFile
            );
        }
        
        // Encrypt if requested
        if( $encrypt && !empty($passphrase) ) {
            $gpg = new GPGEncryption();
            $ini = eZINI::instance( 'git_manager.ini' );
            $deleteOriginal = $ini->variable( 'GitManagerSettings', 'DeleteUnencryptedAfterEncryption' ) === 'enabled';
            
            $encResult = $gpg->encryptFile( $tarFile, $passphrase, $deleteOriginal );
            if( !$encResult['success'] ) {
                return $encResult;
            }
            
            return array(
                'success' => true,
                'file' => basename($encResult['file']),
                'size' => $encResult['size'],
                'encrypted' => true
            );
        }
        
        return array(
            'success' => true,
            'file' => basename($tarFile),
            'size' => filesize($tarFile),
            'encrypted' => false
        );
    }
    
    /**
     * List all existing captions/backups
     * 
     * @return array List of captions with details
     */
    public function listCaptions() {
        if( !is_dir($this->backupPath) ) {
            return array();
        }
        
        $captions = array();
        $dirs = scandir( $this->backupPath, SCANDIR_SORT_DESCENDING );
        
        foreach( $dirs as $dir ) {
            if( $dir === '.' || $dir === '..' ) {
                continue;
            }
            
            $captionPath = $this->backupPath . '/' . $dir;
            if( !is_dir($captionPath) ) {
                continue;
            }
            
            $caption = array(
                'timestamp' => $dir,
                'date' => $this->formatTimestamp($dir),
                'description' => '',
                'files' => array(),
                'total_size' => 0,
                'time_ago' => $this->calculateTimeAgo($dir),
                'agpl_compatible' => file_exists($captionPath . '/agpl_compatible.txt')
            );
            
            // Read description if exists
            $descFile = $captionPath . '/description.txt';
            if( file_exists($descFile) ) {
                $caption['description'] = file_get_contents($descFile);
            }
            
            // List files in caption
            $files = scandir($captionPath);
            foreach( $files as $file ) {
                if( $file === '.' || $file === '..' || $file === 'description.txt' ) {
                    continue;
                }
                
                $filePath = $captionPath . '/' . $file;
                if( is_file($filePath) ) {
                    $isEncrypted = substr($file, -4) === '.gpg';
                    $baseFile = $isEncrypted ? substr($file, 0, -4) : $file;
                    
                    // Determine file type
                    $type = 'var';
                    if( strpos($baseFile, 'sql_agpl_') === 0 ) {
                        $type = 'agpl';
                    } elseif( strpos($baseFile, 'sql_') === 0 ) {
                        $type = 'database';
                    } elseif( strpos($baseFile, 'var_') === 0 ) {
                        $type = 'var';
                    } elseif( strpos($baseFile, 'site_') === 0 ) {
                        $type = 'site';
                    }
                    
                    $fileInfo = array(
                        'name' => $file,
                        'size' => filesize($filePath),
                        'size_formatted' => $this->formatBytes(filesize($filePath)),
                        'type' => $type,
                        'encrypted' => $isEncrypted
                    );
                    $caption['files'][] = $fileInfo;
                    $caption['total_size'] += $fileInfo['size'];
                }
            }
            
            $caption['total_size_formatted'] = $this->formatBytes($caption['total_size']);
            $captions[] = $caption;
        }
        
        return $captions;
    }
    
    /**
     * Delete a caption and all its files
     * 
     * @param string $timestamp Caption timestamp to delete
     * @return array Result with success status and message
     */
    public function deleteCaption( $timestamp ) {
        // Validate timestamp format to prevent directory traversal
        if( !preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $timestamp) ) {
            return array(
                'success' => false,
                'message' => 'Invalid timestamp format'
            );
        }
        
        $captionPath = $this->backupPath . '/' . $timestamp;
        
        if( !is_dir($captionPath) ) {
            return array(
                'success' => false,
                'message' => 'Caption not found: ' . $timestamp
            );
        }
        
        // Remove directory recursively
        $cmd = 'rm -rf ' . escapeshellarg($captionPath) . ' 2>&1';
        exec( $cmd, $output, $return );
        
        if( $return !== 0 ) {
            return array(
                'success' => false,
                'message' => 'Failed to delete caption: ' . implode("\n", $output)
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Caption deleted successfully: ' . $timestamp
        );
    }
    
    /**
     * Write GPL v2 and AGPL v3 license files into a licenses/ subdirectory of the caption dir.
     * These are bundled for legal distribution compliance when sharing backup archives.
     *
     * @param string $captionDir Absolute path to the caption directory
     * @return bool Success
     */
    private function writeLicenseFiles( $captionDir ) {
        $licenseDir = $captionDir . '/licenses';
        if( !$this->createDirectory( $licenseDir ) ) {
            return false;
        }

        $docPath = dirname( dirname( __FILE__ ) ) . '/doc';

        // Copy all four pre-built license files (plain text + Markdown)
        foreach( array( 'LICENSE-GPL-2.0.txt', 'LICENSE-GPL-2.0.md', 'LICENSE-AGPL-3.0.txt', 'LICENSE-AGPL-3.0.md' ) as $file ) {
            $src = $docPath . '/' . $file;
            if( file_exists( $src ) ) {
                copy( $src, $licenseDir . '/' . $file );
            }
        }

        // Write a human-readable README explaining the inclusions
        file_put_contents( $licenseDir . '/README.txt',
            "License Files – Why Are These Here?\n" .
            "====================================\n\n" .
            "This backup caption was created by the eZ Publish git_manager extension.\n\n" .
            "LICENSE-GPL-2.0.txt / LICENSE-GPL-2.0.md\n" .
            "  GNU General Public License, Version 2 (June 1991)\n" .
            "  Governs eZ Publish CMS source code and this extension.\n" .
            "  https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt\n\n" .
            "LICENSE-AGPL-3.0.txt / LICENSE-AGPL-3.0.md\n" .
            "  GNU Affero General Public License, Version 3 (November 2007)\n" .
            "  Any sql_agpl_*.tar.gz file in this caption is a sanitized dump\n" .
            "  intended for public sharing. Recipients may use it under AGPL v3.\n" .
            "  https://www.gnu.org/licenses/agpl-3.0.txt\n\n" .
            "Both licenses are included verbatim as required by their terms.\n"
        );

        return true;
    }

    /**
     * Cleanup old backups based on MaxBackups setting
     */
    private function cleanupOldBackups() {
        if( $this->maxBackups <= 0 ) {
            return; // Unlimited backups
        }
        
        $captions = $this->listCaptions();
        $count = count($captions);
        
        if( $count > $this->maxBackups ) {
            // Delete oldest captions
            $toDelete = array_slice($captions, $this->maxBackups);
            foreach( $toDelete as $caption ) {
                $this->deleteCaption($caption['timestamp']);
            }
        }
    }
    
    /**
     * Create directory recursively
     * 
     * @param string $dir Directory path
     * @return bool Success status
     */
    private function createDirectory( $dir ) {
        if( is_dir($dir) ) {
            return true;
        }
        
        return mkdir($dir, 0775, true);
    }
    
    /**
     * Format timestamp for display
     * 
     * @param string $timestamp Timestamp string (Y-m-d_H-i-s)
     * @return string Formatted date
     */
    private function formatTimestamp( $timestamp ) {
        $dt = DateTime::createFromFormat('Y-m-d_H-i-s', $timestamp);
        if( $dt ) {
            return $dt->format('F j, Y g:i:s A');
        }
        return $timestamp;
    }
    
    /**
     * Format bytes to human readable size
     * 
     * @param int $bytes Bytes
     * @return string Formatted size
     */
    private function formatBytes( $bytes ) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Calculate time ago from timestamp
     * 
     * @param string $timestamp Timestamp string (Y-m-d_H-i-s)
     * @return array Time ago details with value, unit, and color
     */
    private function calculateTimeAgo( $timestamp ) {
        $dt = DateTime::createFromFormat('Y-m-d_H-i-s', $timestamp);
        if( !$dt ) {
            return array(
                'value' => 0,
                'unit' => 'unknown',
                'display' => 'unknown age',
                'color' => '#999'
            );
        }
        
        $now = new DateTime();
        $diff = $now->getTimestamp() - $dt->getTimestamp();
        
        // Calculate appropriate unit
        $seconds = abs($diff);
        $minutes = floor($seconds / 60);
        $hours = floor($seconds / 3600);
        $days = floor($seconds / 86400);
        $months = floor($days / 30);
        $years = floor($days / 365);
        
        // Determine color based on age (green for fresh, yellow/orange for older)
        $color = '#27ae60'; // Green - fresh
        if( $days > 7 ) $color = '#f39c12'; // Orange - week old
        if( $days > 30 ) $color = '#e67e22'; // Dark orange - month old
        if( $days > 90 ) $color = '#e74c3c'; // Red - 3+ months old
        
        // Format display
        if( $years > 1 ) {
            return array(
                'value' => $years,
                'unit' => 'years',
                'display' => ">{$years} years old",
                'color' => '#c0392b' // Dark red
            );
        } elseif( $years === 1 ) {
            return array(
                'value' => 1,
                'unit' => 'year',
                'display' => '1 year old',
                'color' => '#c0392b'
            );
        } elseif( $months > 0 ) {
            return array(
                'value' => $months,
                'unit' => $months === 1 ? 'month' : 'months',
                'display' => "{$months} " . ($months === 1 ? 'month' : 'months') . ' old',
                'color' => $color
            );
        } elseif( $days > 0 ) {
            return array(
                'value' => $days,
                'unit' => $days === 1 ? 'day' : 'days',
                'display' => "{$days} " . ($days === 1 ? 'day' : 'days') . ' old',
                'color' => $color
            );
        } elseif( $hours > 0 ) {
            return array(
                'value' => $hours,
                'unit' => $hours === 1 ? 'hour' : 'hours',
                'display' => "{$hours} " . ($hours === 1 ? 'hour' : 'hours') . ' old',
                'color' => $color
            );
        } elseif( $minutes > 0 ) {
            return array(
                'value' => $minutes,
                'unit' => $minutes === 1 ? 'minute' : 'minutes',
                'display' => "{$minutes} " . ($minutes === 1 ? 'minute' : 'minutes') . ' old',
                'color' => $color
            );
        } else {
            return array(
                'value' => $seconds,
                'unit' => $seconds === 1 ? 'second' : 'seconds',
                'display' => "{$seconds} " . ($seconds === 1 ? 'second' : 'seconds') . ' old',
                'color' => $color
            );
        }
    }
}

?>