<?php
/**
 * The code of extension/git_manager/bin/php/submodule.php, moved into a class (#207 stage 1). The file extension/git_manager/bin/php/submodule.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/bin/php/submodule.php:
 *
 *
 * The submodules of the installation's git repository, from the command line:
 * the same checks as the dashboard (paths, addresses and branches validated,
 * git never waiting for a password). Adding, changing and removing stage the
 * change; commit it to keep it.
 *
 * Usage (from the installation root):
 *   php extension/git_manager/bin/php/submodule.php list
 *   php extension/git_manager/bin/php/submodule.php add <address> <path> [<branch>]
 *   php extension/git_manager/bin/php/submodule.php set-url <name> <address>
 *   php extension/git_manager/bin/php/submodule.php set-branch <name> [<branch>]   (none: follow no branch)
 *   php extension/git_manager/bin/php/submodule.php update [<name>]              (none: every submodule)
 *   php extension/git_manager/bin/php/submodule.php remove <name>
 *
 */

namespace
{


if ( !class_exists( 'GitManager' ) )
{
    require_once dirname( getcwd() . '/extension/git_manager/bin/php/submodule.php' ) . '/../../classes/git_manager.php';
}
}

namespace Exponential\Command\Extension\GitManager
{

class Submodule extends \Exponential\Runnable\Command
{
    public function run()
    {
        // the script's variables were globals; functions of the script read them with "global"
        foreach ( array( 'arguments', 'branch', 'cli', 'command', 'finish', 'first', 'git', 'module', 'modules', 'need', 'options', 'script', 'second', 'third', 'url' ) as $__name )
            ${$__name} = &$GLOBALS[$__name];
        unset( $__name );

        $cli = \eZCLI::instance();
        $script = \eZScript::instance( array(
            'description'    => "The submodules of the installation's git repository: list, add, set-url, set-branch, update, remove.",
            'use-session'    => false,
            'use-modules'    => true,
            'use-extensions' => true,
        ) );
        $script->startup();
        $options = $script->getOptions( '', '[command][first][second][third]', array() );
        $script->initialize();

        $arguments = array_values( $options['arguments'] );
        $command = isset( $arguments[0] ) ? $arguments[0] : 'list';
        $first   = isset( $arguments[1] ) ? $arguments[1] : '';
        $second  = isset( $arguments[2] ) ? $arguments[2] : '';
        $third   = isset( $arguments[3] ) ? $arguments[3] : '';
        $git     = \GitManager::getInstance();

        $finish = function ( array $result, $done ) use ( $cli, $script )
        {
            if ( $result['output'] !== '' )
            {
                $cli->output( $result['output'] );
            }
            if ( $result['exit'] !== 0 )
            {
                $cli->error( 'FAILED (exit ' . $result['exit'] . ')' );
                $script->shutdown( 1 );
            }
            $cli->output( $done );
            $script->shutdown( 0 );
        };
        $need = function ( $what ) use ( $cli, $script )
        {
            $cli->error( 'Missing ' . $what . '. See the usage at the top of this script.' );
            $script->shutdown( 1 );
        };
        $modules = $git->attribute( 'submodules' );

        switch ( $command )
        {
            case 'list':
                if ( !$modules )
                {
                    $cli->output( 'No submodules.' );
                    break;
                }
                foreach ( $modules as $module )
                {
                    $cli->output( sprintf( '%-30s %-12s %-10s %s%s', $module['path'], $module['state'],
                        $module['commit'] !== '' ? substr( $module['commit'], 0, 10 ) : '-', $module['url'],
                        $module['branch'] !== '' ? '  (branch ' . $module['branch'] . ')' : '' ) );
                }
                break;

            case 'add':
                if ( $first === '' || $second === '' ) $need( 'address or path' );
                $finish( $git->addSubmodule( $first, trim( $second, '/' ), $third ), "Added $second; commit it to keep it." );
                break;

            case 'set-url':
                if ( $first === '' || $second === '' ) $need( 'name or address' );
                $branch = isset( $modules[$first] ) ? $modules[$first]['branch'] : '';
                $finish( $git->editSubmodule( $first, $second, $branch ), "Saved $first; commit .gitmodules to keep it." );
                break;

            case 'set-branch':
                if ( $first === '' ) $need( 'name' );
                $url = isset( $modules[$first] ) ? $modules[$first]['url'] : '';
                $finish( $git->editSubmodule( $first, $url, $second ), "Saved $first; commit .gitmodules to keep it." );
                break;

            case 'update':
                if ( $first === '' )
                {
                    $cli->output( $git->updateSubmodules() );
                    break;
                }
                $finish( $git->updateSubmodule( $first ), "Updated $first." );
                break;

            case 'remove':
                if ( $first === '' ) $need( 'name' );
                $finish( $git->removeSubmodule( $first ), "Removed $first; commit the removal to keep it." );
                break;

            default:
                $cli->error( "Unknown command $command. Commands: list, add, set-url, set-branch, update, remove." );
                $script->shutdown( 1 );
        }

        $script->shutdown( 0 );
    }
}

}
