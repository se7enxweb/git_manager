<?php
/**
 * @package GitManager
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 **/

$http    = eZHTTPTool::instance();
$module  = $Params['Module'];
$git     = GitManager::getInstance();
$error   = null;
$message = null;
$output  = null;
$filter  = array(
	'start_date' => null,
	'end_date'   => null,
	'author'     => null
);

$sess = $http->sessionVariable( 'git_commits_filter', array() );

$filter = is_array($sess) ? array_merge( $filter, $sess ) : $filter;

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
	$filter = array_merge( $filter, $http->postVariable( 'filter', array() ) );
} elseif( $module->isCurrentAction( 'FetchRemote' ) ) {
	$remote = (string)$http->postVariable( 'remote', '' );
	$result = $git->fetch( $remote );
	$output = $result['output'] !== '' ? $result['output'] : ezpI18n::tr( 'extension/git_manager', 'Already up to date.' );
	if( $result['exit'] === 0 ) {
		$message = ezpI18n::tr( 'extension/git_manager', 'Fetched %remote.', null, array( '%remote' => $remote ) );
	} else {
		$error = ezpI18n::tr( 'extension/git_manager', 'Fetching %remote failed (git exit %exit).', null, array( '%remote' => $remote, '%exit' => $result['exit'] ) );
	}
} elseif( $module->isCurrentAction( 'PushBranch' ) ) {
	// Publishing: its own policy function, git_manager/push.
	$access = eZUser::currentUser()->hasAccessTo( 'git_manager', 'push' );
	if( $access['accessWord'] === 'no' ) {
		return $module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
	}
	$remote = (string)$http->postVariable( 'remote', '' );
	$branch = (string)$http->postVariable( 'branch', '' );
	$result = $git->push( $remote, $branch );
	$output = $result['output'];
	if( $result['exit'] === 0 ) {
		$message = ezpI18n::tr( 'extension/git_manager', '%branch was pushed to %remote.', null, array( '%branch' => $branch, '%remote' => $remote ) );
	} else {
		$error = ezpI18n::tr( 'extension/git_manager', 'Pushing %branch to %remote failed (git exit %exit); see the output.', null, array( '%branch' => $branch, '%remote' => $remote, '%exit' => $result['exit'] ) );
	}
} elseif( $module->isCurrentAction( 'CheckoutUpdateSubmodules' ) ) {
        $output = $git->updateSubmodules();
        if( $output == '' )
        {
            $output = 'Checkout submodules already up to date!';
        }
}

$http->setSessionVariable( 'git_commits_filter', $filter );

$commits = $git->getCommits( $filter );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'git_manager', $git );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'message', $message );
$tpl->setVariable( 'output',  $output );
$tpl->setVariable( 'filter',  $filter );
$tpl->setVariable( 'commits',  $commits );

// Push: the remotes, and how the checked out branch stands against each.
$remotes = $git->attribute( 'remotes' );
$currentBranch = $git->attribute( 'current_branch' );
$pushState = array();
foreach( $remotes as $remoteName => $remoteUrl ) {
	$pushState[] = array( 'name' => $remoteName, 'url' => $remoteUrl, 'state' => $git->aheadBehind( $remoteName, $currentBranch ) );
}
$pushAccess = eZUser::currentUser()->hasAccessTo( 'git_manager', 'push' );
$tpl->setVariable( 'remotes', $pushState );
$tpl->setVariable( 'can_push', $pushAccess['accessWord'] !== 'no' );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:git_manager/dashboard.tpl' );
$Result['path']    = array(
	array(
		'text' => ezpI18n::tr( 'extension/git_manager', 'Git Manager' ),
		'url'  => false
	)
);