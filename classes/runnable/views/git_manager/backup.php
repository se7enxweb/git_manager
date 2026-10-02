<?php
/**
 * The code of extension/git_manager/modules/git_manager/backup.php, moved into a class (#207 stage 1). The file extension/git_manager/modules/git_manager/backup.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/modules/git_manager/backup.php:
 *
 *
 * @package GitManager
 * @author  SE7ENX
 * @date    2026-06-21
 *
 */

namespace Exponential\View\Extension\GitManager\GitManager
{

class Backup extends \Exponential\Runnable\ModuleView
{
    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        $http   = \eZHTTPTool::instance();
        $module = $Params['Module'];
        $backup = new \BackupManager();
        $error  = null;
        $message = null;
        $processing = false;

        // Every file an action writes (captions, archives, descriptions) is its
        // owner's only: backups hold the database and the settings with their
        // passwords. Put back before the page is drawn.
        $gmOldUmask = umask( 0077 );
        $backup->protectBackupRoot();

        // Handle actions
        if( $module->isCurrentAction( 'CreateFullCaption' ) ) {
            $processing = true;
            $description = trim( $http->postVariable( 'description', '' ) );
            $encrypt = $http->postVariable( 'encrypt', '' ) === 'yes';
            $passphrase = $http->postVariable( 'passphrase', '' );
            $agplCompatible = $http->postVariable( 'agpl_compatible', '' ) === 'yes';
            
            // Validate passphrase if encryption is requested
            if( $encrypt && empty($passphrase) ) {
                $error = 'Passphrase is required when encryption is enabled';
                $processing = false;
            } else {
                $result = $backup->createFullCaption( $description, $encrypt, $passphrase, $agplCompatible );
                
                if( $result['success'] ) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                $processing = false;
            }
        } elseif( $module->isCurrentAction( 'CreateDatabaseCaption' ) ) {
            $processing = true;
            $timestamp = date('Y-m-d_H-i-s');
            $description = trim( $http->postVariable( 'description', '' ) );
            $encrypt = $http->postVariable( 'encrypt', '' ) === 'yes';
            $passphrase = $http->postVariable( 'passphrase', '' );
            $agplCompatible = $http->postVariable( 'agpl_compatible', '' ) === 'yes';
            
            // Validate passphrase if encryption is requested
            if( $encrypt && empty($passphrase) ) {
                $error = 'Passphrase is required when encryption is enabled';
                $processing = false;
            } else {
                $ini = \eZINI::instance( 'git_manager.ini' );
                
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
                
                $backupPath = $ini->variable( 'GitManagerSettings', 'BackupPath' );
                $captionDir = $realPath . '/' . $backupPath . '/' . $timestamp;
                
                if( !is_dir($captionDir) ) {
                    mkdir($captionDir, 0700, true);
                }
                
                if( !empty($description) ) {
                    file_put_contents( $captionDir . '/description.txt', $description );
                }
                
                $result = $backup->createDatabaseDump( $captionDir, $encrypt, $passphrase, $agplCompatible );
                
                if( $result['success'] ) {
                    $message = 'Database caption created successfully: ' . $timestamp . ($encrypt ? ' (encrypted)' : '') . ($agplCompatible ? ' [AGPL Compatible]' : '');
                } else {
                    $error = $result['message'];
                }
                $processing = false;
            }
        } elseif( $module->isCurrentAction( 'CreateVarCaption' ) ) {
            $processing = true;
            $timestamp = date('Y-m-d_H-i-s');
            $description = trim( $http->postVariable( 'description', '' ) );
            $encrypt = $http->postVariable( 'encrypt', '' ) === 'yes';
            $passphrase = $http->postVariable( 'passphrase', '' );
            
            // Validate passphrase if encryption is requested
            if( $encrypt && empty($passphrase) ) {
                $error = 'Passphrase is required when encryption is enabled';
                $processing = false;
            } else {
                $ini = \eZINI::instance( 'git_manager.ini' );
                
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
                
                $backupPath = $ini->variable( 'GitManagerSettings', 'BackupPath' );
                $captionDir = $realPath . '/' . $backupPath . '/' . $timestamp;
                
                if( !is_dir($captionDir) ) {
                    mkdir($captionDir, 0700, true);
                }
                
                if( !empty($description) ) {
                    file_put_contents( $captionDir . '/description.txt', $description );
                }
                
                $result = $backup->createVarBackup( $captionDir, $encrypt, $passphrase );
                
                if( $result['success'] ) {
                    $message = 'Var directory caption created successfully: ' . $timestamp . ($encrypt ? ' (encrypted)' : '');
                } else {
                    $error = $result['message'];
                }
                $processing = false;
            }
        } elseif( $module->isCurrentAction( 'DeleteCaption' ) ) {
            $timestamp = $http->postVariable( 'timestamp', '' );
            
            $result = $backup->deleteCaption( $timestamp );
            
            if( $result['success'] ) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif( $module->isCurrentAction( 'CreateFullSiteBackup' ) ) {
            $processing = true;
            $timestamp = date('Y-m-d_H-i-s');
            $description = trim( $http->postVariable( 'description', '' ) );
            $encrypt = $http->postVariable( 'encrypt', '' ) === 'yes';
            $passphrase = $http->postVariable( 'passphrase', '' );
            $agplCompatible = $http->postVariable( 'agpl_compatible', '' ) === 'yes';
            
            // Validate passphrase if encryption is requested
            if( $encrypt && empty($passphrase) ) {
                $error = 'Passphrase is required when encryption is enabled';
                $processing = false;
            } else {
                // Create full caption first (DB + var) with AGPL flag if requested
                $result = $backup->createFullCaption( $description, $encrypt, $passphrase, $agplCompatible );
                
                if( $result['success'] ) {
                    // Now create full site backup (extensions, settings, config.php)
                    $ini = \eZINI::instance( 'git_manager.ini' );
                    $backupPath = $ini->variable( 'GitManagerSettings', 'BackupPath' );
                    
                    // Resolve real installation path
                    $realPath = getcwd();
                    $gitDir = $realPath . '/.git';
                    if( is_link( $gitDir ) ) {
                        $symlinkTarget = readlink( $gitDir );
                        if( $symlinkTarget[0] !== '/' ) {
                            $symlinkTarget = realpath( './' . $symlinkTarget );
                        }
                        if( substr( $symlinkTarget, -5 ) === '/.git' ) {
                            $realPath = substr( $symlinkTarget, 0, -5 );
                        }
                    }
                    
                    $captionDir = $realPath . '/' . $backupPath . '/' . $timestamp;
                    
                    // Create site archive (extensions, settings, config files)
                    $siteFile = $captionDir . '/site_' . $timestamp . '.tar.gz';
                    $excludes = '--exclude=\'./var/*\' --exclude=\'./.git\' --exclude=\'./vendor/composer\' --exclude=\'./autoload/*\'';
                    $siteCmd = 'cd ' . escapeshellarg( $realPath ) . ' && tar -czf ' . escapeshellarg( $siteFile ) . ' ' . $excludes . ' ./extension ./settings ./config.php ./config.php-RECOMMENDED 2>&1';
                    
                    exec($siteCmd, $siteOutput, $siteReturn);
                    
                    if( $siteReturn === 0 && file_exists($siteFile) ) {
                        // Optionally encrypt
                        if( $encrypt && !empty($passphrase) ) {
                            $gpg = new \GPGEncryption();
                            $deleteOriginal = $ini->variable( 'GitManagerSettings', 'DeleteUnencryptedAfterEncryption' ) === 'enabled';
                            $encResult = $gpg->encryptFile( $siteFile, $passphrase, $deleteOriginal );
                            
                            if( $encResult['success'] ) {
                                $message = 'Full site backup created successfully (encrypted): ' . $timestamp;
                            } else {
                                $error = 'Site backup created but encryption failed: ' . $encResult['message'];
                            }
                        } else {
                            $message = 'Full site backup created successfully: ' . $timestamp;
                        }
                    } else {
                        $error = 'Failed to create site archive: ' . implode("\n", $siteOutput);
                    }
                } else {
                    $error = $result['message'];
                }
                $processing = false;
            }
        } elseif( $module->isCurrentAction( 'DeleteSelectedCaptions' ) ) {
            $selectedTimestamps = $http->postVariable( 'selected_timestamps', '' );
            
            if( !empty($selectedTimestamps) ) {
                $timestamps = explode(',', $selectedTimestamps);
                $successCount = 0;
                $failureCount = 0;
                
                foreach( $timestamps as $timestamp ) {
                    $timestamp = trim($timestamp);
                    if( !empty($timestamp) ) {
                        $result = $backup->deleteCaption( $timestamp );
                        if( $result['success'] ) {
                            $successCount++;
                        } else {
                            $failureCount++;
                        }
                    }
                }
                
                if( $successCount > 0 ) {
                    $message = "Successfully deleted {$successCount} caption(s)";
                    if( $failureCount > 0 ) {
                        $message .= ", failed to delete {$failureCount} caption(s)";
                    }
                } else {
                    $error = "Failed to delete selected captions";
                }
            } else {
                $error = "No captions selected for deletion";
            }
        }

        // List all captions
        $captions = $backup->listCaptions();

        // Calculate oldest backup warning or no backups warning
        $oldestWarning = null;
        $noBackupsWarning = false;

        if( empty($captions) ) {
            // No backups exist at all - critical warning
            $noBackupsWarning = true;
        } else {
            $oldest = end($captions); // Last item is oldest
            $ageData = $oldest['time_ago'];
            
            // Show warning if backup is more than 7 days old
            $showWarning = false;
            if( $ageData['unit'] === 'months' || $ageData['unit'] === 'month' || $ageData['unit'] === 'years' || $ageData['unit'] === 'year' ) {
                $showWarning = true;
            } elseif( $ageData['unit'] === 'days' || $ageData['unit'] === 'day' ) {
                if( $ageData['value'] > 7 ) {
                    $showWarning = true;
                }
            }
            
            if( $showWarning ) {
                $oldestWarning = array(
                    'age' => $ageData['display'],
                    'color' => $ageData['color']
                );
            }
        }

        umask( $gmOldUmask );

        $tpl = \eZTemplate::factory();
        $tpl->setVariable( 'error', $error );
        $tpl->setVariable( 'message', $message );
        $tpl->setVariable( 'processing', $processing );
        $tpl->setVariable( 'captions', $captions );
        $tpl->setVariable( 'oldest_warning', $oldestWarning );
        $tpl->setVariable( 'no_backups_warning', $noBackupsWarning );

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:git_manager/backup.tpl' );
        $Result['path']    = array(
            array(
                'text' => \ezpI18n::tr( 'extension/git_manager', 'Backup Manager' ),
                'url'  => false
            )
        );

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
