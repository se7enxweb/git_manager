<?php
/**
 * The code of extension/git_manager/modules/git_manager/dump.php, moved into a class (#207 stage 1). The file extension/git_manager/modules/git_manager/dump.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/modules/git_manager/dump.php:
 *
 *
 * @package GitManager
 * @author  7x <info@se7enx.com>
 * @date    28 Sep 2026
 *
 * git_manager/dump was the address of the backup view before 2.0.4. It now
 * sends every request on to git_manager/backup, so bookmarks and links keep
 * working. It only redirects: forms post to git_manager/backup.
 *
 */

namespace Exponential\View\Extension\GitManager\GitManager
{

class Dump extends \Exponential\Runnable\ModuleView
{
    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        return $this->viewResult( isset( $Result ) ? $Result : null,  $Params['Module']->redirectTo( 'git_manager/backup' ) );

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
