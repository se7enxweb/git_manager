<?php
/**
 * @package GitManager
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 **/

$Module = array(
	'name'      => 'Git Manager',
	'functions' => array()
);

$ViewList = array(
	'dashboard' => array(
		'script'                  => 'dashboard.php',
		'functions'               => array( 'git_manager' ),
		'params'                  => array(),
		'default_navigation_part' => 'ezsetupnavigationpart',
		'single_post_actions'     => array(
			'CheckoutLocalBranch'  => 'CheckoutLocalBranch',
			'CheckoutRemoteBranch' => 'CheckoutRemoteBranch',
			'SetCommitsFilter'     => 'SetCommitsFilter',
			'CheckoutCommit'       => 'CheckoutCommit',
                        'CheckoutUpdateSubmodules' => 'CheckoutUpdateSubmodules',
			'FetchRemote'          => 'FetchRemote',
			'PushBranch'           => 'PushBranch'
		)
	),
	'commit_details' => array(
		'script'                  => 'commit_details.php',
		'functions'               => array( 'git_manager' ),
		'params'                  => array( 'Hash' ),
		'default_navigation_part' => 'ezsetupnavigationpart'
	),
	'backup' => array(
		'script'                  => 'backup.php',
		// 'dump' is the function's name before 2.0.4: roles that grant it keep working.
		'functions'               => 'backup or dump',
		'params'                  => array(),
		'default_navigation_part' => 'ezsetupnavigationpart',
		'single_post_actions'     => array(
			'CreateFullCaption'     => 'CreateFullCaption',
			'CreateDatabaseCaption' => 'CreateDatabaseCaption',
			'CreateVarCaption'      => 'CreateVarCaption',
			'CreateFullSiteBackup'  => 'CreateFullSiteBackup',
			'DeleteCaption'         => 'DeleteCaption',
			'DeleteSelectedCaptions' => 'DeleteSelectedCaptions'
		)
	),
	// The address before 2.0.4, kept so bookmarks and links still arrive: it
	// redirects to git_manager/backup.
	'dump' => array(
		'script'                  => 'dump.php',
		'functions'               => 'backup or dump',
		'params'                  => array(),
		'default_navigation_part' => 'ezsetupnavigationpart'
	),
	'download' => array(
		'script'                  => 'download.php',
		'functions'               => 'backup or dump',
		'params'                  => array( 'Timestamp', 'Filename' ),
		'default_navigation_part' => 'ezsetupnavigationpart'
	)
);

$FunctionList = array(
	'git_manager' => array(),
	'backup' => array(),
	// Pushing a branch to a remote: publishing, so a function of its own.
	'push' => array(),
	// Before 2.0.4 the backup function was called dump. Kept so existing roles
	// stay valid; bin/php/upgrade-policy-dump-to-backup.php renames them.
	'dump' => array()
);