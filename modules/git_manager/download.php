<?php
/**
 * @package GitManager
 * @author  SE7ENX
 * @date    2026-06-21
 **/

$http      = eZHTTPTool::instance();
$Module    = $Params['Module'];
$timestamp = $Params['Timestamp'];
$filename  = $Params['Filename'];
$backup    = new BackupManager();

// Validate timestamp format to prevent directory traversal
if( !preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $timestamp) ) {
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

// Validate filename format (allow .gpg extension for encrypted files)
if( !preg_match('/^(sql|var|site)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz(\.gpg)?$/', $filename) ) {
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

// Get list of captions to verify this one exists
$captions = $backup->listCaptions();
$found = false;

foreach( $captions as $caption ) {
    if( $caption['timestamp'] === $timestamp ) {
        foreach( $caption['files'] as $file ) {
            if( $file['name'] === $filename ) {
                $found = true;
                break 2;
            }
        }
    }
}

if( !$found ) {
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

// Build file path
$ini = eZINI::instance( 'git_manager.ini' );
$backupPath = $ini->variable( 'GitManagerSettings', 'BackupPath' );

// Resolve real installation path
$realPath = realpath('./');
$gitDir = './.git';
if( is_link( $gitDir ) ) {
    $symlinkTarget = readlink( $gitDir );
    if( $symlinkTarget[0] !== '/' ) {
        $symlinkTarget = realpath( './' . $symlinkTarget );
    }
    if( substr( $symlinkTarget, -5 ) === '/.git' ) {
        $realPath = substr( $symlinkTarget, 0, -5 );
    }
}

$filePath = $realPath . '/' . $backupPath . '/' . $timestamp . '/' . $filename;

// Verify file exists and is readable
if( !file_exists( $filePath ) || !is_readable( $filePath ) ) {
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}

// Send file to browser
header( 'Content-Type: application/gzip' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
header( 'Content-Length: ' . filesize( $filePath ) );
header( 'Cache-Control: must-revalidate' );
header( 'Pragma: public' );

readfile( $filePath );

eZExecution::cleanExit();
