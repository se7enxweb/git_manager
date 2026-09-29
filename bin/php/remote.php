#!/usr/bin/env php
<?php
/**
 * The remotes of the installation's git repository, from the command line:
 * the same checks as the dashboard (names and addresses validated, a push
 * never forced, git never waiting for a password).
 *
 * Usage (from the installation root):
 *   php extension/git_manager/bin/php/remote.php list
 *   php extension/git_manager/bin/php/remote.php add <name> <address>
 *   php extension/git_manager/bin/php/remote.php set-url <name> <address>
 *   php extension/git_manager/bin/php/remote.php rename <name> <new name>
 *   php extension/git_manager/bin/php/remote.php remove <name>
 *   php extension/git_manager/bin/php/remote.php fetch <name>
 *   php extension/git_manager/bin/php/remote.php push <name> [<branch>]   (default: the checked out branch)
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array(
    'description'    => "The remotes of the installation's git repository: list, add, set-url, rename, remove, fetch, push.",
    'use-session'    => false,
    'use-modules'    => true,
    'use-extensions' => true,
) );
$script->startup();
$options = $script->getOptions( '', '[command][name][value]', array() );
$script->initialize();

if ( !class_exists( 'GitManager' ) )
{
    require_once dirname( __FILE__ ) . '/../../classes/git_manager.php';
}

$arguments = array_values( $options['arguments'] );
$command = isset( $arguments[0] ) ? $arguments[0] : 'list';
$name    = isset( $arguments[1] ) ? $arguments[1] : '';
$value   = isset( $arguments[2] ) ? $arguments[2] : '';
$git     = GitManager::getInstance();

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

switch ( $command )
{
    case 'list':
        $branch = $git->attribute( 'current_branch' );
        $remotes = $git->attribute( 'remotes' );
        if ( !$remotes )
        {
            $cli->output( 'No remotes.' );
            break;
        }
        foreach ( $remotes as $remote => $url )
        {
            $state = $git->aheadBehind( $remote, $branch );
            $cli->output( sprintf( '%-14s %-60s %s', $remote, $url,
                $state === false ? $branch . ' not on this remote yet'
                                 : sprintf( '%s: %d to push, %d behind', $branch, $state['ahead'], $state['behind'] ) ) );
        }
        break;

    case 'add':
        if ( $name === '' || $value === '' ) $need( 'name or address' );
        $finish( $git->addRemote( $name, $value ), "Added $name." );
        break;

    case 'set-url':
        if ( $name === '' || $value === '' ) $need( 'name or address' );
        $finish( $git->setRemoteUrl( $name, $value ), "Saved $name." );
        break;

    case 'rename':
        if ( $name === '' || $value === '' ) $need( 'name or new name' );
        $finish( $git->renameRemote( $name, $value ), "Renamed $name to $value." );
        break;

    case 'remove':
        if ( $name === '' ) $need( 'name' );
        $finish( $git->removeRemote( $name ), "Removed $name." );
        break;

    case 'fetch':
        if ( $name === '' ) $need( 'name' );
        $finish( $git->fetch( $name ), "Fetched $name." );
        break;

    case 'push':
        if ( $name === '' ) $need( 'name' );
        $branch = $value !== '' ? $value : $git->attribute( 'current_branch' );
        $finish( $git->push( $name, $branch ), "Pushed $branch to $name." );
        break;

    default:
        $cli->error( "Unknown command $command. Commands: list, add, set-url, rename, remove, fetch, push." );
        $script->shutdown( 1 );
}

$script->shutdown( 0 );
