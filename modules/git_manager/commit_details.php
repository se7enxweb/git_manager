<?php
/**
 * @package GitManager
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 **/

$module  = $Params['Module'];
$git     = GitManager::getInstance();
$hash    = $Params['Hash'];
$output  = null;

if( strlen( $hash ) == 0  ) {
	return $module->redirectTo( 'git_manager/dashboard' );
}

$output = $git->commitInfo( $hash );

/**
 * "git log -1 -p" output as the page shows it: the header fields, the
 * message, and each changed file with its lines typed (add, del, hunk, meta,
 * context) and counted. False when it is not a commit (an unknown hash).
 */
function gitManagerParseCommit( $text )
{
	$lines = preg_split( '/\r\n|\n/', (string)$text );
	if ( !$lines || !preg_match( '/^commit ([0-9a-f]{7,40})/', $lines[0], $m ) )
		return false;
	$commit = array( 'hash' => $m[1], 'fields' => array(), 'message' => array(), 'files' => array(),
	                 'additions' => 0, 'deletions' => 0 );
	$i = 1;
	for ( ; $i < count( $lines ) && $lines[$i] !== ''; $i++ )
	{
		if ( preg_match( '/^([A-Za-z]+):\s*(.*)$/', $lines[$i], $f ) )
			$commit['fields'][] = array( 'name' => $f[1], 'value' => $f[2] );
	}
	for ( $i++; $i < count( $lines ) && strpos( $lines[$i], 'diff --git ' ) !== 0; $i++ )
		$commit['message'][] = preg_replace( '/^    /', '', $lines[$i] );
	while ( $commit['message'] && trim( end( $commit['message'] ) ) === '' )
		array_pop( $commit['message'] );
	$file = null;
	for ( ; $i < count( $lines ); $i++ )
	{
		$line = $lines[$i];
		if ( strpos( $line, 'diff --git ' ) === 0 )
		{
			if ( $file )
				$commit['files'][] = $file;
			$name = preg_match( '#^diff --git a/(.*) b/(.*)$#', $line, $n ) ? $n[2] : substr( $line, 11 );
			$file = array( 'name' => $name, 'lines' => array(), 'additions' => 0, 'deletions' => 0, 'truncated' => false );
			continue;
		}
		if ( !$file )
			continue;
		if ( strpos( $line, '@@' ) === 0 )
			$type = 'hunk';
		else if ( preg_match( '#^(index |new file|deleted file|old mode|new mode|similarity|rename |Binary files|--- |\+\+\+ )#', $line ) )
			$type = 'meta';
		else if ( $line !== '' && $line[0] === '+' )
			$type = 'add';
		else if ( $line !== '' && $line[0] === '-' )
			$type = 'del';
		else
			$type = 'context';
		if ( $type === 'add' ) { $file['additions']++; $commit['additions']++; }
		if ( $type === 'del' ) { $file['deletions']++; $commit['deletions']++; }
		// Drawn up to 500 lines a file, so a huge commit still opens; the
		// counts cover every line.
		if ( count( $file['lines'] ) < 500 )
			$file['lines'][] = array( 'type' => $type, 'text' => $line );
		else
			$file['truncated'] = true;
	}
	if ( $file )
		$commit['files'][] = $file;
	return $commit;
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'commit', gitManagerParseCommit( $output ) );
$tpl->setVariable( 'output',  $output );
$tpl->setVariable( 'hash',  $hash );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:git_manager/commit_details.tpl' );
$Result['path']    = array(
	array(
		'text' => ezpI18n::tr( 'extension/git_manager', 'GIT Manager' ),
		'url'  => 'git_manager/dashboard'
	),
	array(
		'text' => ezpI18n::tr( 'extension/git_manager', 'Commit details' ),
		'url'  => false
	)
);