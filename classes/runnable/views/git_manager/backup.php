<?php
/**
 * The view git_manager/backup: the backups, how fresh they are, and the forms
 * that make and remove them. The entry point is
 * extension/git_manager/modules/git_manager/backup.php (#207).
 *
 * The view is thin: the backups are read by GitManagerBackupCatalogue (through
 * BackupManager::listCaptions()), how fresh they are is decided by
 * GitManagerBackupFreshness from the NEWEST backup, and the words come from
 * GitManagerBackupMessages. Every action is a POST (with the form token of
 * ezformtoken) and ends in a redirect to this view, so a reload never repeats
 * it; its outcome is carried over in the session once.
 *
 * Template variables (design:git_manager/backup.tpl):
 *   captions            the backups, newest first (BackupManager::listCaptions()), each also with
 *                       age_text (translated), is_newest, too_large_here
 *   backup_status       state, severity, title, text, notes, count (GitManagerBackupMessages::status())
 *   error, message      the outcome of the last action, or null
 *   no_backups_warning  true when there is no backup (kept from 1.x)
 *   oldest_warning      kept from 1.x for overridden templates: since 2.0.15 the age of the NEWEST
 *                       backup when it is older than WarnAfterDays (age, color), else null
 *   processing          always false (kept from 1.x)
 *   persistent_server   whether this request is served by a persistent PHP server (Velocity)
 *   download_limit      the largest file that server sends, in bytes (0: no limit)
 *
 * @package GitManager
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

namespace Exponential\View\Extension\GitManager\GitManager
{

class Backup extends \Exponential\Runnable\ModuleView
{
    /** The session key the outcome of an action is carried in to the page after the redirect. */
    const FLASH = 'GitManagerBackupFlash';

    public function run( array $scope )
    {
        $module = $scope['Params']['Module'];
        $http = \eZHTTPTool::instance();
        $backup = new \BackupManager();

        // Every file an action writes (captions, archives, descriptions) is its
        // owner's only: backups hold the database and the settings with their
        // passwords. Put back before the page is drawn.
        $oldUmask = umask( self::creationUmask( 0077 ) );
        try
        {
            $backup->protectBackupRoot();
            $outcome = $this->runAction( $module, $http, $backup );
        }
        finally
        {
            umask( $oldUmask );
        }

        if ( $outcome !== null )
        {
            $http->setSessionVariable( self::FLASH, $outcome );
            // Always this view, never an address from the request.
            return $module->redirectTo( 'git_manager/backup' );
        }

        $error = null;
        $message = null;
        if ( $http->hasSessionVariable( self::FLASH, false ) )
        {
            $flash = $http->sessionVariable( self::FLASH );
            $http->removeSessionVariable( self::FLASH );
            if ( is_array( $flash ) && isset( $flash['text'] ) )
            {
                if ( !empty( $flash['ok'] ) ) $message = (string)$flash['text'];
                else $error = (string)$flash['text'];
            }
        }

        $now = time();
        $captions = $backup->listCaptions( $now );
        $freshness = $backup->freshness();
        $evaluation = $freshness->evaluate( $captions, $now );
        $texts = new \GitManagerBackupMessages();
        $status = $texts->status( $evaluation );
        $status['count'] = $evaluation['count'];
        $status['count_text'] = $texts->countText( $evaluation['count'] );

        $persistent = \BackupManager::isPersistentServer();
        $limit = \BackupManager::downloadLimitBytes();
        $newestName = $evaluation['newest'] ? $evaluation['newest']['timestamp'] : null;
        foreach ( $captions as &$caption )
        {
            $caption['age_text'] = $texts->captionAgeText( $caption['time_ago'] );
            $caption['is_newest'] = $caption['timestamp'] === $newestName;
            foreach ( $caption['files'] as &$file )
            {
                $file['too_large_here'] = $persistent && $limit > 0 && $file['size'] !== null && $file['size'] > $limit;
            }
            unset( $file );
        }
        unset( $caption );

        // 1.x variables, for templates overridden from the old page.
        $oldestWarning = null;
        if ( $evaluation['state'] === \GitManagerBackupFreshness::STATE_AGEING || $evaluation['state'] === \GitManagerBackupFreshness::STATE_STALE )
        {
            $oldestWarning = array(
                'age'   => $texts->ageText( $evaluation['age'] ),
                'color' => \GitManagerBackupFreshness::color( $evaluation['state'] ),
            );
        }

        $tpl = \eZTemplate::factory();
        $tpl->setVariable( 'error', $error );
        $tpl->setVariable( 'message', $message );
        $tpl->setVariable( 'processing', false );
        $tpl->setVariable( 'captions', $captions );
        $tpl->setVariable( 'backup_status', $status );
        $tpl->setVariable( 'oldest_warning', $oldestWarning );
        $tpl->setVariable( 'no_backups_warning', $evaluation['state'] === \GitManagerBackupFreshness::STATE_NONE );
        $tpl->setVariable( 'persistent_server', $persistent );
        $tpl->setVariable( 'download_limit', $limit );
        $tpl->setVariable( 'download_limit_text', $limit > 0 ? \BackupManager::formatBytes( $limit ) : '' );

        return array(
            'content' => $tpl->fetch( 'design:git_manager/backup.tpl' ),
            'path'    => array(
                array( 'text' => \ezpI18n::tr( 'extension/git_manager', 'Backup Manager' ), 'url' => false ),
            ),
        );
    }

    /**
     * Runs the action of a POST, if any.
     *
     * @return array|null array( 'ok' => bool, 'text' => string ), or null when there was none
     */
    private function runAction( $module, \eZHTTPTool $http, \BackupManager $backup )
    {
        foreach ( array( 'CreateFullSiteBackup', 'CreateFullCaption', 'CreateDatabaseCaption', 'CreateVarCaption' ) as $action )
        {
            if ( $module->isCurrentAction( $action ) )
            {
                return $this->createBackup( $action, $http, $backup );
            }
        }
        if ( $module->isCurrentAction( 'DeleteCaption' ) )
        {
            // The name comes in "timestamp" (1.x forms) or as the value of the button.
            $name = (string)$http->postVariable( 'timestamp', '' );
            if ( $name === '' ) $name = (string)$http->postVariable( 'DeleteCaption', '' );
            $result = $backup->deleteCaption( $name );
            return array( 'ok' => $result['success'], 'text' => $result['message'] );
        }
        if ( $module->isCurrentAction( 'DeleteSelectedCaptions' ) )
        {
            return $this->deleteSelected( $http, $backup );
        }
        return null;
    }

    private function createBackup( $action, \eZHTTPTool $http, \BackupManager $backup )
    {
        $description = trim( (string)$http->postVariable( 'description', '' ) );
        $encrypt = $http->postVariable( 'encrypt', '' ) === 'yes';
        $passphrase = (string)$http->postVariable( 'passphrase', '' );
        $agpl = $action !== 'CreateVarCaption' && $http->postVariable( 'agpl_compatible', '' ) === 'yes';

        if ( $encrypt && $passphrase === '' )
        {
            return array( 'ok' => false, 'text' => \ezpI18n::tr( 'extension/git_manager', 'A passphrase is needed to encrypt the backup. Nothing was created.' ) );
        }

        if ( $action === 'CreateFullCaption' )
        {
            $result = $backup->createFullCaption( $description, $encrypt, $passphrase, $agpl );
            return array( 'ok' => $result['success'], 'text' => $result['message'] );
        }
        if ( $action === 'CreateFullSiteBackup' )
        {
            $result = $backup->createFullCaption( $description, $encrypt, $passphrase, $agpl );
            if ( !$result['success'] )
            {
                return array( 'ok' => false, 'text' => $result['message'] );
            }
            // The site archive goes into the caption just made (its name, not a second date()).
            $site = $backup->createSiteArchive( $result['timestamp'], $encrypt, $passphrase );
            if ( !$site['success'] )
            {
                return array( 'ok' => false, 'text' => $site['message'] );
            }
            return array( 'ok' => true, 'text' => 'Full site backup created successfully: ' . $result['timestamp'] . ( $encrypt ? ' (encrypted)' : '' ) . ( $agpl ? ' [AGPL Compatible]' : '' ) );
        }

        $dir = $backup->newCaptionDir();
        if ( $dir === false )
        {
            return array( 'ok' => false, 'text' => 'Failed to create caption directory' );
        }
        list( $name, $path ) = $dir;
        if ( $description !== '' )
        {
            file_put_contents( $path . '/description.txt', $description );
        }
        if ( $action === 'CreateDatabaseCaption' )
        {
            $result = $backup->createDatabaseDump( $path, $encrypt, $passphrase, $agpl );
            $text = 'Database caption created successfully: ' . $name . ( $encrypt ? ' (encrypted)' : '' ) . ( $agpl ? ' [AGPL Compatible]' : '' );
        }
        else
        {
            $result = $backup->createVarBackup( $path, $encrypt, $passphrase );
            $text = 'Var directory caption created successfully: ' . $name . ( $encrypt ? ' (encrypted)' : '' );
        }
        return $result['success'] ? array( 'ok' => true, 'text' => $text ) : array( 'ok' => false, 'text' => $result['message'] );
    }

    private function deleteSelected( \eZHTTPTool $http, \BackupManager $backup )
    {
        $selected = $http->postVariable( 'selected_timestamps', '' );
        $names = is_array( $selected ) ? $selected : explode( ',', (string)$selected );
        // Also the checkboxes themselves, when the page was sent without javascript.
        if ( $http->hasPostVariable( 'timestamps' ) && is_array( $http->postVariable( 'timestamps' ) ) )
        {
            $names = array_merge( $names, $http->postVariable( 'timestamps' ) );
        }
        $names = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $names ) ), 'strlen' ) ) );
        if ( !$names )
        {
            return array( 'ok' => false, 'text' => \ezpI18n::tr( 'extension/git_manager', 'No backup was selected. Nothing was removed.' ) );
        }
        $done = 0;
        $failed = 0;
        foreach ( $names as $name )
        {
            $result = $backup->deleteCaption( $name );
            $result['success'] ? $done++ : $failed++;
        }
        if ( $done === 0 )
        {
            return array( 'ok' => false, 'text' => \ezpI18n::tr( 'extension/git_manager', 'None of the selected backups could be removed.' ) );
        }
        $text = \ezpI18n::tr( 'extension/git_manager', 'Backups removed: %count.', null, array( '%count' => $done ) );
        if ( $failed > 0 )
        {
            $text .= ' ' . \ezpI18n::tr( 'extension/git_manager', 'Not removed: %count.', null, array( '%count' => $failed ) );
        }
        return array( 'ok' => true, 'text' => $text );
    }

    /**
     * The umask $umask, narrowed further by the limits EZP_FILE_MODE_MAX / EZP_DIR_MODE_MAX of the kernel
     * (eZFile::creationUmask()); on a kernel without that helper $umask as it is.
     */
    private static function creationUmask( $umask ) {
        return method_exists( 'eZFile', 'creationUmask' ) ? \eZFile::creationUmask( $umask ) : (int)$umask & 0777;
    }
}

}
