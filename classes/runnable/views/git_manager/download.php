<?php
/**
 * The view git_manager/download/<caption>/<file>: one archive of a backup. The
 * entry point is extension/git_manager/modules/git_manager/download.php (#207).
 *
 * Only an archive of a caption is sent (sql_, sql_agpl_, var_ and site_
 * archives, encrypted or not): both names are checked against their patterns
 * and the file must resolve inside the backup folder
 * (GitManagerBackupCatalogue::archivePath()), so no path can be smuggled in.
 *
 * The file is streamed in pieces with the output buffers ended and the session
 * closed, so a backup of several gigabytes neither fills the memory nor holds
 * the session (and every other page of the user) for as long as it takes. A
 * persistent PHP server (Velocity) keeps the whole response in memory however
 * it is written; there a file above PersistentServerDownloadLimitMB is refused
 * with a message on the Backup page instead of exhausting the worker.
 *
 * eZExecution::cleanExit() is called outside any try/catch: on Velocity it
 * ends the request by throwing, and catching that would append the page to
 * the file.
 *
 * @package GitManager
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

namespace Exponential\View\Extension\GitManager\GitManager
{

class Download extends \Exponential\Runnable\ModuleView
{
    public function run( array $scope )
    {
        $module = $scope['Params']['Module'];
        $name = (string)$scope['Params']['Timestamp'];
        $file = (string)$scope['Params']['Filename'];

        $backup = new \BackupManager();
        $path = $backup->catalogue()->archivePath( $name, $file );
        if ( $path === null )
        {
            return $module->handleError( \eZError::KERNEL_NOT_FOUND, 'kernel' );
        }

        $size = filesize( $path );
        $limit = \BackupManager::downloadLimitBytes();
        if ( \BackupManager::isPersistentServer() && $limit > 0 && $size > $limit )
        {
            \eZHTTPTool::instance()->setSessionVariable( Backup::FLASH, array(
                'ok'   => false,
                'text' => \ezpI18n::tr( 'extension/git_manager',
                    '%file is %size, more than this server sends (%limit). Download it through the address served by Apache or PHP-FPM, or copy it from the server.',
                    null, array( '%file' => $file, '%size' => \BackupManager::formatBytes( $size ), '%limit' => \BackupManager::formatBytes( $limit ) ) ),
            ) );
            return $module->redirectTo( 'git_manager/backup' );
        }

        $handle = fopen( $path, 'rb' );
        if ( $handle === false )
        {
            return $module->handleError( \eZError::KERNEL_NOT_FOUND, 'kernel' );
        }

        while ( ob_get_level() > 0 && @ob_end_clean() );
        \eZSession::stop();

        header( 'Content-Type: ' . ( substr( $file, -4 ) === '.gpg' ? 'application/octet-stream' : 'application/gzip' ) );
        header( 'Content-Disposition: attachment; filename="' . $file . '"' );
        header( 'Content-Length: ' . $size );
        // A backup holds the database and the settings: never kept by a browser or
        // a cache on the way.
        header( 'Cache-Control: private, no-store, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'X-Content-Type-Options: nosniff' );

        while ( !feof( $handle ) )
        {
            $chunk = fread( $handle, 1048576 );
            if ( $chunk === false )
            {
                break;
            }
            echo $chunk;
            if ( !\BackupManager::isPersistentServer() )
            {
                flush();
            }
            if ( connection_aborted() )
            {
                break;
            }
        }
        fclose( $handle );

        \eZExecution::cleanExit();
        return null;
    }
}

}
