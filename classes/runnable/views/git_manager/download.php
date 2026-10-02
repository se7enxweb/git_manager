<?php
/**
 * The code of extension/git_manager/modules/git_manager/download.php, moved into a class (#207 stage 1). The file extension/git_manager/modules/git_manager/download.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/modules/git_manager/download.php:
 *
 *
 * @package GitManager
 * @author  SE7ENX
 * @date    2026-06-21
 *
 */

namespace Exponential\View\Extension\GitManager\GitManager
{

class Download extends \Exponential\Runnable\ModuleView
{
    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        $http      = \eZHTTPTool::instance();
        $Module    = $Params['Module'];
        $timestamp = $Params['Timestamp'];
        $filename  = $Params['Filename'];
        $backup    = new \BackupManager();

        // Validate timestamp format to prevent directory traversal
        if( !preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $timestamp) ) {
            return $this->viewResult( isset( $Result ) ? $Result : null,  $Module->handleError( \eZError::KERNEL_NOT_FOUND, 'kernel' ) );
        }

        // Validate filename format (allow .gpg extension for encrypted files)
        if( !preg_match('/^(sql|var|site)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz(\.gpg)?$/', $filename) ) {
            return $this->viewResult( isset( $Result ) ? $Result : null,  $Module->handleError( \eZError::KERNEL_NOT_FOUND, 'kernel' ) );
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
            return $this->viewResult( isset( $Result ) ? $Result : null,  $Module->handleError( \eZError::KERNEL_NOT_FOUND, 'kernel' ) );
        }

        // Build file path
        $ini = \eZINI::instance( 'git_manager.ini' );
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
            return $this->viewResult( isset( $Result ) ? $Result : null,  $Module->handleError( \eZError::KERNEL_NOT_FOUND, 'kernel' ) );
        }

        // Send file to browser
        header( 'Content-Type: application/gzip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $filePath ) );
        // A backup holds the database and the settings: never kept by a browser or
        // a cache on the way (it was sent "Pragma: public").
        header( 'Cache-Control: private, no-store, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'X-Content-Type-Options: nosniff' );

        readfile( $filePath );

        \eZExecution::cleanExit();

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
