<?php
/**
 * The code of extension/git_manager/modules/git_manager/dashboard.php, moved into a class (#207 stage 1). The file extension/git_manager/modules/git_manager/dashboard.php is one call to it.
 * Guide: doc/bc/6.0/cli_cronjob_view_abstractions.md
 */
/*
 * The original header of extension/git_manager/modules/git_manager/dashboard.php:
 *
 *
 * @package GitManager
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 *
 */

namespace Exponential\View\Extension\GitManager\GitManager
{

class Dashboard extends \Exponential\Runnable\ModuleView
{
    /**
     * GitManagerUpstream, loaded from its file when the class map does not
     * list it yet (a process started before the autoloads were regenerated).
     */
    private static function upstreamAvailable()
    {
        if ( !class_exists( 'GitManagerUpstream' ) && is_file( __DIR__ . '/../../../gitmanagerupstream.php' ) )
            require_once __DIR__ . '/../../../gitmanagerupstream.php';
        return class_exists( 'GitManagerUpstream', false );
    }

    /** GitManagerLog, loaded from its file when the class map does not list it yet (as upstreamAvailable()). */
    private static function logAvailable()
    {
        if ( !class_exists( 'GitManagerLog' ) && is_file( __DIR__ . '/../../../gitmanagerlog.php' ) )
            require_once __DIR__ . '/../../../gitmanagerlog.php';
        return class_exists( 'GitManagerLog', false );
    }

    public function run( array $scope )
    {
        // the including function's variables ($Params, $Module, $cli, ...)
        foreach ( array_keys( $scope ) as $__name )
            if ( $__name !== 'this' && $__name !== 'scope' )
                ${$__name} = &$scope[$__name];
        unset( $__name );

        $http    = \eZHTTPTool::instance();
        $module  = $Params['Module'];
        $git     = \GitManager::getInstance();
        $error   = null;
        $message = null;
        $output  = null;
        // The commit log's page and filters, from the view parameters (/(offset)/50/(limit)/25/(author)/jane ...),
        // so a link reproduces the view; the page size a user picked is kept as the preference
        // admin_git_manager_log_limit. GitManagerLog checks every value before git sees it.
        self::logAvailable();
        $preferredLimit = class_exists( 'eZPreferences' ) ? \eZPreferences::value( 'admin_git_manager_log_limit' ) : false;
        $log = \GitManagerLog::parseViewParameters( isset( $Params['UserParameters'] ) ? $Params['UserParameters'] : array(), $preferredLimit );
        if( $log['limit_given'] && (string)$log['limit'] !== (string)$preferredLimit ) {
        	\eZPreferences::setValue( 'admin_git_manager_log_limit', (string)$log['limit'] );
        }

        if(
        	$module->isCurrentAction( 'CheckoutLocalBranch' )
        	|| $module->isCurrentAction( 'CheckoutRemoteBranch' )
        ) {
        	$branches = array_merge(
        		$git->attribute( 'local_branches' ),
        		$git->attribute( 'remote_branches' )
        	);
        	$branch = $http->postVariable( 'branch', null );
                $regenerateAutoloads = $http->postVariable( 'regenerate', false ) == 'regenerate' ? true : false;

                if( in_array( $branch, $branches ) ) {
                        $output  = $git->checkout( $branch );
                        $output .= "\n" . $git->pull( $branch, $regenerateAutoloads );
        		$message = '"' . $branch . '" branch is checked out';
        	} else {
        		$error = 'Invalid "' . $branch . '" branch';
        	}
        } elseif( $module->isCurrentAction( 'CheckoutCommit' ) ) {
        	$hash = $http->postVariable( 'hash', null );

        	$output  = $git->checkoutCommit( $hash );
        	$message = '"' . $hash . '" commit is checked out';
        } elseif( $module->isCurrentAction( 'SetCommitsFilter' ) ) {
        	// The filter form: its values become view parameters of the first page, so the address says what is
        	// shown and a reload never posts again.
        	$state = \GitManagerLog::stateFromForm( $http->postVariable( 'filter', array() ), $http->postVariable( 'log_limit', $log['limit'] ) );
        	return $this->viewResult( isset( $Result ) ? $Result : null, $module->redirectTo( '/git_manager/dashboard' . \GitManagerLog::viewParameterPath( $state ) ) );
        } elseif( ( $module->isCurrentAction( 'FetchUpstream' ) || $http->hasPostVariable( 'FetchUpstream' ) ) && self::upstreamAvailable() ) {
        	// hasPostVariable too: a process that read module.php before FetchUpstream was in it.
        	// The Upstream card's "Fetch now": git fetch --prune of the upstream
        	// remote, nothing else. The result goes through the session to the
        	// page this redirects to, so a reload never fetches again.
        	$upstream = new \GitManagerUpstream();
        	$result = $upstream->fetch();
        	$state = $upstream->status( true );
        	if( class_exists( 'expAudit' ) && class_exists( 'gitManagerAuditBranch' ) ) {
        		$data = array(
        			'object' => array( 'type' => 'remote', 'id' => $upstream->remoteName() ),
        			'after'  => array(
        				'via'      => $result['via'],
        				'user'     => $result['user'],
        				'seconds'  => round( $result['seconds'], 2 ),
        				'ahead'    => $state['ahead'],
        				'behind'   => $state['behind'],
        				'tracking' => $state['tracking']
        			)
        		);
        		if( $result['exit'] !== 0 ) {
        			$data['result'] = 'failed';
        			$data['reason'] = $result['reason'] !== '' ? $result['reason'] : 'git';
        		}
        		\expAudit::event( 'system.git_manager.fetch', $data );
        	}
        	$http->setSessionVariable( 'git_manager_upstream_fetch', array(
        		'exit'    => $result['exit'],
        		'output'  => $result['output'],
        		'remote'  => $upstream->remoteName(),
        		'seconds' => round( $result['seconds'], 1 ),
        		'via'     => $result['via'],
        		'user'    => $result['user']
        	) );
        	return $this->viewResult( isset( $Result ) ? $Result : null, $module->redirectTo( '/git_manager/dashboard' ) );
        } elseif( $module->isCurrentAction( 'FetchRemote' ) ) {
        	$remote = (string)$http->postVariable( 'remote', '' );
        	$result = $git->fetch( $remote );
        	$output = $result['output'] !== '' ? $result['output'] : \ezpI18n::tr( 'extension/git_manager', 'Already up to date.' );
        	if( $result['exit'] === 0 ) {
        		$message = \ezpI18n::tr( 'extension/git_manager', 'Fetched %remote.', null, array( '%remote' => $remote ) );
        	} else {
        		$error = \ezpI18n::tr( 'extension/git_manager', 'Fetching %remote failed (git exit %exit).', null, array( '%remote' => $remote, '%exit' => $result['exit'] ) );
        	}
        } elseif( $module->isCurrentAction( 'PushBranch' ) ) {
        	// Publishing: its own policy function, git_manager/push.
        	$access = \eZUser::currentUser()->hasAccessTo( 'git_manager', 'push' );
        	if( $access['accessWord'] === 'no' ) {
        		return $this->viewResult( isset( $Result ) ? $Result : null,  $module->handleError( \eZError::KERNEL_ACCESS_DENIED, 'kernel' ) );
        	}
        	$remote = (string)$http->postVariable( 'remote', '' );
        	$branch = (string)$http->postVariable( 'branch', '' );
        	$result = $git->push( $remote, $branch );
        	$output = $result['output'];
        	if( $result['exit'] === 0 ) {
        		$message = \ezpI18n::tr( 'extension/git_manager', '%branch was pushed to %remote.', null, array( '%branch' => $branch, '%remote' => $remote ) );
        	} else {
        		$error = \ezpI18n::tr( 'extension/git_manager', 'Pushing %branch to %remote failed (git exit %exit); see the output.', null, array( '%branch' => $branch, '%remote' => $remote, '%exit' => $result['exit'] ) );
        	}
        } elseif( $module->isCurrentAction( 'AddRemote' ) || $module->isCurrentAction( 'UpdateRemote' ) || $module->isCurrentAction( 'RemoveRemote' ) ) {
        	// The repository's configuration: its own policy function, git_manager/remotes.
        	$access = \eZUser::currentUser()->hasAccessTo( 'git_manager', 'remotes' );
        	if( $access['accessWord'] === 'no' ) {
        		return $this->viewResult( isset( $Result ) ? $Result : null,  $module->handleError( \eZError::KERNEL_ACCESS_DENIED, 'kernel' ) );
        	}
        	$remote = (string)$http->postVariable( 'remote', '' );
        	$url    = trim( (string)$http->postVariable( 'url', '' ) );
        	$steps  = array();
        	if( $module->isCurrentAction( 'AddRemote' ) ) {
        		$steps[] = $git->addRemote( $remote, $url );
        		$done = \ezpI18n::tr( 'extension/git_manager', 'The remote %remote was added.', null, array( '%remote' => $remote ) );
        	} elseif( $module->isCurrentAction( 'RemoveRemote' ) ) {
        		$steps[] = $git->removeRemote( $remote );
        		$done = \ezpI18n::tr( 'extension/git_manager', 'The remote %remote was removed.', null, array( '%remote' => $remote ) );
        	} else {
        		$newName = trim( (string)$http->postVariable( 'new_name', $remote ) );
        		$steps[] = $git->setRemoteUrl( $remote, $url );
        		if( $steps[0]['exit'] === 0 && $newName !== $remote ) {
        			$steps[] = $git->renameRemote( $remote, $newName );
        		}
        		$done = \ezpI18n::tr( 'extension/git_manager', 'The remote %remote was saved.', null, array( '%remote' => $newName ) );
        	}
        	$failed = false;
        	$output = '';
        	foreach( $steps as $step ) {
        		$output .= ( $output !== '' && $step['output'] !== '' ? "\n" : '' ) . $step['output'];
        		$failed = $failed || $step['exit'] !== 0;
        	}
        	if( $failed ) {
        		$error = \ezpI18n::tr( 'extension/git_manager', 'The remote was not changed; see the output.' );
        	} else {
        		$message = $done;
        	}
        	if( $output === '' ) {
        		$output = null;
        	}
        } elseif( $module->isCurrentAction( 'UpdateSubmodule' ) ) {
        	$name = (string)$http->postVariable( 'submodule', '' );
        	$result = $git->updateSubmodule( $name );
        	$output = $result['output'] !== '' ? $result['output'] : \ezpI18n::tr( 'extension/git_manager', 'Already up to date.' );
        	if( $result['exit'] === 0 ) {
        		$message = \ezpI18n::tr( 'extension/git_manager', 'The submodule %name was updated.', null, array( '%name' => $name ) );
        	} else {
        		$error = \ezpI18n::tr( 'extension/git_manager', 'Updating the submodule %name failed (git exit %exit).', null, array( '%name' => $name, '%exit' => $result['exit'] ) );
        	}
        } elseif( $module->isCurrentAction( 'AddSubmodule' ) || $module->isCurrentAction( 'EditSubmodule' ) || $module->isCurrentAction( 'RemoveSubmodule' ) ) {
        	// Changes the working tree and the index: its own policy function, git_manager/submodules.
        	$access = \eZUser::currentUser()->hasAccessTo( 'git_manager', 'submodules' );
        	if( $access['accessWord'] === 'no' ) {
        		return $this->viewResult( isset( $Result ) ? $Result : null,  $module->handleError( \eZError::KERNEL_ACCESS_DENIED, 'kernel' ) );
        	}
        	$name   = (string)$http->postVariable( 'submodule', '' );
        	$url    = trim( (string)$http->postVariable( 'url', '' ) );
        	$branch = trim( (string)$http->postVariable( 'submodule_branch', '' ) );
        	if( $module->isCurrentAction( 'AddSubmodule' ) ) {
        		$path = trim( (string)$http->postVariable( 'path', '' ), " /" );
        		$result = $git->addSubmodule( $url, $path, $branch );
        		$done = \ezpI18n::tr( 'extension/git_manager', 'The submodule %name was added and staged; commit it to keep it.', null, array( '%name' => $path ) );
        	} elseif( $module->isCurrentAction( 'RemoveSubmodule' ) ) {
        		$result = $git->removeSubmodule( $name );
        		$done = \ezpI18n::tr( 'extension/git_manager', 'The submodule %name was removed and the removal staged; commit it to keep it.', null, array( '%name' => $name ) );
        	} else {
        		$result = $git->editSubmodule( $name, $url, $branch );
        		$done = \ezpI18n::tr( 'extension/git_manager', 'The submodule %name was saved and .gitmodules staged; commit it to keep it.', null, array( '%name' => $name ) );
        	}
        	$output = $result['output'] !== '' ? $result['output'] : null;
        	if( $result['exit'] === 0 ) {
        		$message = $done;
        	} else {
        		$error = \ezpI18n::tr( 'extension/git_manager', 'The submodule was not changed; see the output.' );
        	}
        } elseif( $module->isCurrentAction( 'CheckoutUpdateSubmodules' ) ) {
                $output = $git->updateSubmodules();
                if( $output == '' )
                {
                    $output = 'Checkout submodules already up to date!';
                }
        }

        // One page of the log: the total from git rev-list --count, the page from git log --skip --max-count;
        // the whole history is never read. A branch or tag the repository does not have is reported, not used.
        $refNames = $git->refNames();
        $logRef = \GitManagerLog::resolveRef( $log['ref'], $refNames );
        $unknownRef = false;
        if( $logRef === null ) {
        	$unknownRef = $log['ref'];
        	$log['ref'] = '';
        	$logRef = 'HEAD';
        }
        $paging = \GitManagerLog::paging( $git->countCommits( $log, $logRef ), $log['offset'], $log['limit'] );
        $log['offset'] = $paging['offset'];
        $commits = $paging['total'] > 0 ? $git->logPage( $log, $logRef, $paging['offset'], $paging['limit'] ) : array();

        $logLinks = array();
        foreach( array( 'first', 'previous', 'next', 'last' ) as $which ) {
        	$logLinks[ $which ] = $paging[ $which . '_offset' ] === false ? false
        		: '/git_manager/dashboard' . \GitManagerLog::viewParameterPath( $log, array( 'offset' => $paging[ $which . '_offset' ] ) );
        }
        $logSizes = array();
        foreach( \GitManagerLog::LIMITS as $size ) {
        	// The first commit shown stays on the page the new size shows.
        	$logSizes[] = array( 'size' => $size, 'current' => $size === $paging['limit'],
        		'url' => '/git_manager/dashboard' . \GitManagerLog::viewParameterPath( $log, array( 'limit' => $size, 'offset' => intdiv( $paging['offset'], $size ) * $size ) ) );
        }
        $logBranches = array( 'local' => array(), 'remote' => array() );
        foreach( $refNames as $refName ) {
        	if( strpos( $refName, 'refs/heads/' ) === 0 ) {
        		$logBranches['local'][] = substr( $refName, 11 );
        	} elseif( strpos( $refName, 'refs/remotes/' ) === 0 && substr( $refName, -5 ) !== '/HEAD' ) {
        		$logBranches['remote'][] = substr( $refName, 13 );
        	}
        }
        $filter = array( 'ref' => $log['ref'], 'author' => $log['author'], 'grep' => $log['grep'], 'start_date' => $log['since'], 'end_date' => $log['until'] );

        $tpl = \eZTemplate::factory();
        $tpl->setVariable( 'git_manager', $git );
        $tpl->setVariable( 'error', $error );
        $tpl->setVariable( 'message', $message );
        $tpl->setVariable( 'output',  $output );
        $tpl->setVariable( 'filter',  $filter );
        $tpl->setVariable( 'commits',  $commits );
        $tpl->setVariable( 'log', $log );
        $tpl->setVariable( 'log_filtered', \GitManagerLog::isFiltered( $log ) );
        $tpl->setVariable( 'log_paging', $paging );
        $tpl->setVariable( 'log_links', $logLinks );
        $tpl->setVariable( 'log_sizes', $logSizes );
        $tpl->setVariable( 'log_branches', $logBranches );
        $tpl->setVariable( 'log_unknown_ref', $unknownRef );
        $tpl->setVariable( 'log_navigator_parameters', \GitManagerLog::navigatorParameters( $log ) );
        $tpl->setVariable( 'log_clear_url', '/git_manager/dashboard' . \GitManagerLog::viewParameterPath( array( 'limit' => $log['limit'] ) ) );
        // The log is open when this user keeps it open, or when the address asks for a page or a filter.
        $tpl->setVariable( 'log_open', $log['offset'] > 0 || $log['limit_given'] || \GitManagerLog::isFiltered( $log ) || $unknownRef !== false );
        $tpl->setVariable( 'head_commit_date', $git->headCommitDate() );

        // Upstream: where the installation stands against the remote's branch,
        // from the cache unless something it depends on changed; and the
        // result of the last "Fetch now", shown once.
        $tpl->setVariable( 'upstream', self::upstreamAvailable() ? ( new \GitManagerUpstream() )->status() : false );
        $upstreamFetch = $http->hasSessionVariable( 'git_manager_upstream_fetch' ) ? $http->sessionVariable( 'git_manager_upstream_fetch' ) : null;
        if( $upstreamFetch !== null ) {
        	$http->removeSessionVariable( 'git_manager_upstream_fetch' );
        }
        $tpl->setVariable( 'upstream_fetch', is_array( $upstreamFetch ) ? $upstreamFetch : false );

        // Push: the remotes, and how the checked out branch stands against each.
        $remotes = $git->attribute( 'remotes' );
        $currentBranch = $git->attribute( 'current_branch' );
        $pushState = array();
        foreach( $remotes as $remoteName => $remoteUrl ) {
        	$pushState[] = array( 'name' => $remoteName, 'url' => $remoteUrl, 'state' => $git->aheadBehind( $remoteName, $currentBranch ) );
        }
        $pushAccess = \eZUser::currentUser()->hasAccessTo( 'git_manager', 'push' );
        $tpl->setVariable( 'remotes', $pushState );

        // Which commits of the log a remote does not have yet: marked on each commit,
        // counted for the log's summary.
        $unpushed = array();
        foreach( array_keys( $remotes ) as $remoteName ) {
        	$unpushed[ $remoteName ] = $git->unpushedCommits( $remoteName, 500, $logRef );
        }
        $unpushedCounts = array_fill_keys( array_keys( $remotes ), 0 );
        $localOnly = 0;
        foreach( $commits as $index => $commit ) {
        	$missing = array();
        	foreach( $unpushed as $remoteName => $hashes ) {
        		if( isset( $hashes[ $commit['hash'] ] ) ) {
        			$missing[] = $remoteName;
        			$unpushedCounts[ $remoteName ]++;
        		}
        	}
        	$commits[ $index ]['missing'] = $missing;
        	$commits[ $index ]['local_only'] = $remotes && count( $missing ) === count( $remotes );
        	if( $commits[ $index ]['local_only'] ) {
        		$localOnly++;
        	}
        }
        $tpl->setVariable( 'commits', $commits );
        $tpl->setVariable( 'unpushed_counts', $unpushedCounts );
        $tpl->setVariable( 'local_only_count', $localOnly );
        $tpl->setVariable( 'unpushed_total', array_sum( $unpushedCounts ) );
        $tpl->setVariable( 'can_push', $pushAccess['accessWord'] !== 'no' );
        $remotesAccess = \eZUser::currentUser()->hasAccessTo( 'git_manager', 'remotes' );
        $tpl->setVariable( 'can_manage_remotes', $remotesAccess['accessWord'] !== 'no' );
        $submodulesAccess = \eZUser::currentUser()->hasAccessTo( 'git_manager', 'submodules' );
        $tpl->setVariable( 'can_manage_submodules', $submodulesAccess['accessWord'] !== 'no' );
        $tpl->setVariable( 'submodules', $git->attribute( 'submodules' ) );

        // The "Add a submodule" card stays open when that very action just failed,
        // so the error is never hidden behind the fold.
        $tpl->setVariable( 'submodule_add_failed', $module->isCurrentAction( 'AddSubmodule' ) && $error !== null );

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:git_manager/dashboard.tpl' );
        // The Git tab's own left menu (menu.ini [Leftmenu_gitmanager]): Git Manager and Backup, with the width switch
        $Result['left_menu'] = 'design:parts/gitmanager/menu.tpl';
        $Result['path']    = array(
        	array(
        		'text' => \ezpI18n::tr( 'extension/git_manager', 'Git Manager' ),
        		'url'  => false
        	)
        );

        return $this->viewResult( isset( $Result ) ? $Result : null, null );
    }
}

}
