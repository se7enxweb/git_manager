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
    /** createFullCaption, with every file it writes readable by its owner only. */
    public function createFullCaption( $description = '', $encrypt = false, $passphrase = '', $agplCompatible = false ) {
        return $this->withPrivateFiles( function () use ( $description, $encrypt, $passphrase, $agplCompatible ) {
            return $this->createFullCaptionUnguarded( $description, $encrypt, $passphrase, $agplCompatible );
        } );
    }

    private function createFullCaptionUnguarded( $description = '', $encrypt = false, $passphrase = '', $agplCompatible = false ) {
        $timestamp = $this->catalogue()->nameFor( time() );
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
    /** createDatabaseDump, with every file it writes readable by its owner only. */
    public function createDatabaseDump( $outputDir, $encrypt = false, $passphrase = '', $agplCompatible = false ) {
        return $this->withPrivateFiles( function () use ( $outputDir, $encrypt, $passphrase, $agplCompatible ) {
            return $this->createDatabaseDumpUnguarded( $outputDir, $encrypt, $passphrase, $agplCompatible );
        } );
    }

    private function createDatabaseDumpUnguarded( $outputDir, $encrypt = false, $passphrase = '', $agplCompatible = false ) {
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
        
        // The connection in an option file (not on the command line).
        $optionFile = $this->mysqlOptionFile( $dbSettings );
        if( $optionFile === false ) {
            return array(
                'success' => false,
                'message' => 'Could not write the mysqldump option file'
            );
        }

        // Dump schema only, data only, and complete (schema + data)
        $schemaFile = $sqlDir . '/schema.sql';
        $dataFile = $sqlDir . '/data.sql';
        $completeFile = $sqlDir . '/complete.sql';
        $schemaCmd = $this->mysqldumpCommand( $dumpCommand, $optionFile, $dbSettings, $schemaFile ) . ' --no-data > ' . escapeshellarg($schemaFile) . ' 2>&1';
        $dataCmd = $this->mysqldumpCommand( $dumpCommand, $optionFile, $dbSettings, $dataFile ) . ' --no-create-info > ' . escapeshellarg($dataFile) . ' 2>&1';
        $completeCmd = $this->mysqldumpCommand( $dumpCommand, $optionFile, $dbSettings, $completeFile ) . ' > ' . escapeshellarg($completeFile) . ' 2>&1';

        // Execute dumps
        exec( $schemaCmd, $schemaOutput, $schemaReturn );
        exec( $dataCmd, $dataOutput, $dataReturn );
        exec( $completeCmd, $completeOutput, $completeReturn );
        unlink( $optionFile );

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
    /** createVarBackup, with every file it writes readable by its owner only. */
    public function createVarBackup( $outputDir, $encrypt = false, $passphrase = '' ) {
        return $this->withPrivateFiles( function () use ( $outputDir, $encrypt, $passphrase ) {
            return $this->createVarBackupUnguarded( $outputDir, $encrypt, $passphrase );
        } );
    }

    private function createVarBackupUnguarded( $outputDir, $encrypt = false, $passphrase = '' ) {
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
     * The archive of the site's own files (extension/, settings/, config.php)
     * in the caption $name, encrypted when asked. Part of a full site backup.
     *
     * @return array( 'success' => bool, 'message' => string, 'file' => string )
     */
    public function createSiteArchive( $name, $encrypt = false, $passphrase = '' ) {
        return $this->withPrivateFiles( function () use ( $name, $encrypt, $passphrase ) {
            $captionDir = $this->catalogue()->captionPath( $name );
            if( $captionDir === null || !is_dir( $captionDir ) ) {
                return array( 'success' => false, 'message' => 'Caption not found: ' . $name );
            }
            $siteFile = $captionDir . '/site_' . $name . '.tar.gz';
            $excludes = '--exclude=\'./var/*\' --exclude=\'./.git\' --exclude=\'./vendor/composer\' --exclude=\'./autoload/*\'';
            $paths = array();
            foreach( array( './extension', './settings', './config.php', './config.php-RECOMMENDED' ) as $path ) {
                if( file_exists( $this->realInstallPath . '/' . $path ) ) {
                    $paths[] = $path;
                }
            }
            $cmd = 'cd ' . escapeshellarg( $this->realInstallPath ) . ' && tar -czf ' . escapeshellarg( $siteFile ) . ' ' . $excludes . ' ' . implode( ' ', $paths ) . ' 2>&1';
            exec( $cmd, $output, $return );
            if( $return !== 0 || !file_exists( $siteFile ) ) {
                return array( 'success' => false, 'message' => 'Failed to create site archive: ' . implode( "\n", $output ) );
            }
            if( $encrypt && $passphrase !== '' ) {
                $gpg = new GPGEncryption();
                $deleteOriginal = eZINI::instance( 'git_manager.ini' )->variable( 'GitManagerSettings', 'DeleteUnencryptedAfterEncryption' ) === 'enabled';
                $enc = $gpg->encryptFile( $siteFile, $passphrase, $deleteOriginal );
                if( !$enc['success'] ) {
                    return array( 'success' => false, 'message' => 'Site backup created but encryption failed: ' . $enc['message'] );
                }
                return array( 'success' => true, 'message' => '', 'file' => basename( $enc['file'] ) );
            }
            return array( 'success' => true, 'message' => '', 'file' => basename( $siteFile ) );
        } );
    }

    /**
     * Whether this request is served by a persistent PHP server (Exponential
     * Velocity), which keeps a response in memory until the script ends.
     */
    public static function isPersistentServer() {
        return defined( 'QBIX_WEBSERVER' ) || isset( $_SERVER['QBIX_WORKER'] ) || isset( $_SERVER['VELOCITY'] )
            || class_exists( 'Q_WebServer', false );
    }

    /** PersistentServerDownloadLimitMB of git_manager.ini in bytes; 0 = no limit. */
    public static function downloadLimitBytes() {
        $ini = eZINI::instance( 'git_manager.ini' );
        if( !$ini->hasVariable( 'GitManagerSettings', 'PersistentServerDownloadLimitMB' ) ) {
            return 128 * 1048576;
        }
        $mb = trim( (string)$ini->variable( 'GitManagerSettings', 'PersistentServerDownloadLimitMB' ) );
        return is_numeric( $mb ) && (float)$mb > 0 ? (int)round( (float)$mb * 1048576 ) : 0;
    }

    /** The backup folder, absolute. */
    public function backupPath() {
        return $this->backupPath;
    }

    /** The backups on disk (GitManagerBackupCatalogue), in the installation's time zone. */
    public function catalogue() {
        return new GitManagerBackupCatalogue( $this->backupPath );
    }

    /** How fresh the backups must be, from [BackupFreshnessSettings] of git_manager.ini. */
    public function freshness() {
        return GitManagerBackupFreshness::fromIni( eZINI::instance( 'git_manager.ini' ) );
    }

    /**
     * The folder of a new caption made now, created (owner only). Returns
     * array( name, path ) or false when it could not be created.
     */
    public function newCaptionDir() {
        $name = $this->catalogue()->nameFor( time() );
        $path = $this->backupPath . '/' . $name;
        if( !$this->withPrivateFiles( function () use ( $path ) { return $this->createDirectory( $path ); } ) ) {
            return false;
        }
        return array( $name, $path );
    }

    /**
     * Every caption, newest first, with the keys the page and the scripts
     * have used since 1.0: timestamp, date, description, files (name, size,
     * size_formatted, type, encrypted), total_size, total_size_formatted,
     * time_ago (value, unit, display, color) and agpl_compatible. Since 2.0.15
     * also created, created_source, readable, valid_name and per file archive
     * (whether it can be downloaded). See GitManagerBackupCatalogue::captions().
     *
     * @param int|null $now the moment the ages are taken at; default now
     * @return array
     */
    public function listCaptions( $now = null ) {
        $now = $now === null ? time() : (int)$now;
        $catalogue = $this->catalogue();
        $freshness = $this->freshness();
        $captions = $catalogue->captions();
        foreach( $captions as &$caption ) {
            $caption['date'] = self::formatCreated( $caption['created'], $catalogue->timeZone(), $caption['timestamp'] );
            $caption['time_ago'] = $freshness->timeAgo( $caption['created'], $now );
            foreach( $caption['files'] as &$file ) {
                $file['size_formatted'] = $file['size'] === null ? '?' : self::formatBytes( $file['size'] );
            }
            unset( $file );
            $caption['total_size_formatted'] = self::formatBytes( $caption['total_size'] );
        }
        unset( $caption );
        return $captions;
    }

    /** A moment as the list shows it, in the installation's time zone. */
    public static function formatCreated( $created, DateTimeZone $timeZone, $fallback = '' ) {
        if( $created === null ) {
            return (string)$fallback;
        }
        $dt = new DateTime( '@' . (int)$created );
        $dt->setTimezone( $timeZone );
        return $dt->format( 'F j, Y g:i:s A T' );
    }

    /** A size in bytes as text: 1.5 KB, 7.32 GB. */
    public static function formatBytes( $bytes ) {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        $bytes = max( (float)$bytes, 0 );
        $pow = (int)floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );
        $bytes /= pow( 1024, $pow );
        return round( $bytes, 2 ) . ' ' . $units[$pow];
    }

    /**
     * Delete a caption and all its files
     * 
     * @param string $timestamp Caption timestamp to delete
     * @return array Result with success status and message
     */
    public function deleteCaption( $timestamp ) {
        // Validate timestamp format to prevent directory traversal
        if( !GitManagerBackupCatalogue::isValidName( $timestamp ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid timestamp format'
            );
        }
        
        $captionPath = $this->backupPath . '/' . $timestamp;
        
        if( !is_dir($captionPath) || is_link($captionPath) ) {
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
        
        return mkdir($dir, 0700, true);
    }

    /**
     * Runs $work with umask 077, so every file and directory it and the
     * commands it starts create (dumps, archives, encrypted copies) is its
     * owner's only. Backups hold the database and the settings with their
     * passwords; world-readable, any account on a shared server could read
     * them. The umask is put back afterwards: several servers (PHP-FPM,
     * Velocity) share this installation's cache and run as different users.
     */
    private function withPrivateFiles( $work ) {
        $old = umask( 0077 );
        try {
            $this->protectBackupRoot();
            return $work();
        } finally {
            umask( $old );
        }
    }

    /**
     * The backup folder refuses web requests itself (Apache .htaccess), in
     * case the web server's rules ever let a request through to var/.
     */
    public function protectBackupRoot() {
        if( !is_dir( $this->backupPath ) && !mkdir( $this->backupPath, 0700, true ) ) {
            return false;
        }
        $htaccess = $this->backupPath . '/.htaccess';
        if( !is_file( $htaccess ) ) {
            file_put_contents( $htaccess, "# git_manager backups: never served.\nRequire all denied\n" );
        }
        if( !is_file( $this->backupPath . '/index.html' ) ) {
            file_put_contents( $this->backupPath . '/index.html', '' );
        }
        return true;
    }

    /**
     * The client settings for mysqldump in an option file only its owner can
     * read, so the password is never on a command line (where every account
     * on the server can see it in the process list). Returns the file path;
     * the caller removes it.
     */
    private function mysqlOptionFile( array $db ) {
        $file = tempnam( sys_get_temp_dir(), 'gm-my-' );
        if( $file === false ) {
            return false;
        }
        chmod( $file, 0600 );
        $quote = function ( $value ) {
            return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string)$value ) . '"';
        };
        $lines = array( '[client]' );
        foreach( array( 'host', 'port', 'user', 'password' ) as $key ) {
            if( isset( $db[ $key ] ) && (string)$db[ $key ] !== '' ) {
                $lines[] = $key . '=' . $quote( $db[ $key ] );
            }
        }
        file_put_contents( $file, implode( "\n", $lines ) . "\n" );
        return $file;
    }

    /**
     * The MySQLDumpCommand of git_manager.ini as a shell command: the option
     * file first (mysqldump reads it only as its first argument), the
     * connection placeholders dropped (the option file has them), the database
     * and output file quoted.
     */
    private function mysqldumpCommand( $template, $optionFile, array $db, $outputFile ) {
        $template = preg_replace( '/\s*(--host=\{host\}|--port=\{port\}|--user=\{user\}|--password=\{password\}|-p\{password\}|-h\s*\{host\}|-P\s*\{port\}|-u\s*\{user\})/', '', $template );
        $parts = preg_split( '/\s+/', trim( $template ), 2 );
        $command = $parts[0] . ' --defaults-extra-file=' . escapeshellarg( $optionFile ) . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
        return str_replace( array( '{database}', '{output_file}' ),
                            array( escapeshellarg( (string)$db['database'] ), escapeshellarg( $outputFile ) ), $command );
    }
    
}

?>