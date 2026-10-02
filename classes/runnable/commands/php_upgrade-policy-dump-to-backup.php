<?php
/**
 * The code of extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php, moved into a class (#207 stage 1). The file extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php:
 *
 *
 * Renames the role policies git_manager/dump to git_manager/backup.
 *
 * Before 2.0.4 the backup view was git_manager/dump and its policy function was
 * called dump. The function is now backup; dump is still accepted, so nothing
 * breaks without this script, but a role shows the old name until it is run.
 * Limitations of each policy stay with it.
 *
 * Usage (from the installation root):
 *   php extension/git_manager/bin/php/upgrade-policy-dump-to-backup.php [--dry-run]
 *
 */

namespace Exponential\Command\Extension\GitManager
{

class UpgradePolicyDumpToBackup extends \Exponential\Runnable\Command
{
    public function run()
    {
        // the script's variables were globals; functions of the script read them with "global"
        foreach ( array( 'changed', 'cli', 'db', 'options', 'policyID', 'roleID', 'row', 'rows', 'script' ) as $__name )
            ${$__name} = &$GLOBALS[$__name];
        unset( $__name );

        $cli = \eZCLI::instance();
        $script = \eZScript::instance( array(
            'description'    => "Renames the role policies git_manager/dump to git_manager/backup.",
            'use-session'    => false,
            'use-modules'    => true,
            'use-extensions' => true,
        ) );
        $script->startup();
        $options = $script->getOptions( '[dry-run]', '', array( 'dry-run' => 'show what would change, change nothing' ) );
        $script->initialize();

        $db = \eZDB::instance();
        $rows = $db->arrayQuery( "SELECT id, role_id FROM ezpolicy WHERE module_name = 'git_manager' AND function_name = 'dump'" );
        if ( !$rows )
        {
            $cli->output( 'No policy names git_manager/dump; nothing to do.' );
            $script->shutdown( 0 );
        }

        $changed = 0;
        foreach ( $rows as $row )
        {
            $policyID = (int)$row['id'];
            $roleID = (int)$row['role_id'];
            $cli->output( "role $roleID, policy $policyID: git_manager/dump -> git_manager/backup" . ( $options['dry-run'] ? ' (dry run)' : '' ) );
            if ( $options['dry-run'] )
            {
                continue;
            }
            $db->query( "UPDATE ezpolicy SET function_name = 'backup' WHERE id = $policyID" );
            $changed++;
        }

        if ( $changed )
        {
            // Roles are cached; let every user read the new names at once.
            \eZRole::expireCache();
            $cli->output( "$changed policies renamed." );
        }
        $script->shutdown( 0 );
    }
}

}
