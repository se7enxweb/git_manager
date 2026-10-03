<?php
/**
 * @package GitManager
 * @class   GitManager
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 **/

class GitManager
{
	private static $path = './';
	private static $instance = null;

	private $callbackAttributes = array(
		'current_branch'  => 'getCurrentBranch',
		'current_commit'  => 'getCurrentCommit',
		'local_branches'  => 'getLocalBranches',
		'remote_branches' => 'getRemoteBranches',
		'remotes'         => 'getRemotes',
		'submodules'      => 'getSubmodules'
	);

	private function __construct() {
		// Resolve symlinked .git directory to actual repository root
		self::$path = $this->resolveGitPath();
	}

	/**
	 * Resolve the actual git repository path, following symlinks if necessary
	 * 
	 * @return string The real path to the git repository root
	 */
	private function resolveGitPath() {
		$currentPath = './';
		$gitDir = $currentPath . '.git';
		
		// Check if .git exists and is a symlink
		if( is_link( $gitDir ) ) {
			// Read the symlink target
			$symlinkTarget = readlink( $gitDir );
			
			// If it's a relative path, resolve it relative to current directory
			if( $symlinkTarget[0] !== '/' ) {
				$symlinkTarget = realpath( $currentPath . $symlinkTarget );
			}
			
			// Remove the .git suffix to get the repository root
			if( substr( $symlinkTarget, -5 ) === '/.git' ) {
				$repoRoot = substr( $symlinkTarget, 0, -5 );
				return $repoRoot;
			}
		}
		
		// If not a symlink or couldn't resolve, use current path
		return $currentPath;
	}

	/** The repository's working tree as the commands use it ('./' or the root a .git link points into). */
	public static function repositoryPath() {
		self::getInstance();
		return self::$path;
	}

	public static function getInstance() {
		if( self::$instance === null ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}

	public function attributes() {
		return array_keys( $this->callbackAttributes );
	}

	public function hasAttribute( $attr ) {
		return isset( $this->callbackAttributes[ $attr ] );
	}

	public function attribute( $attr ) {
		if( isset( $this->callbackAttributes[ $attr ] ) === false ) {
			throw new Exception( 'Undefined "' . $attr . '" attribute' );
		}

		$callback = array(
			$this,
			$this->callbackAttributes[ $attr ]
		);
		return call_user_func( $callback );
	}

	private function getCurrentBranch() {
		return $this->cli( 'rev-parse --abbrev-ref HEAD' );
	}

	private function getCurrentCommit() {
		return $this->cli( 'rev-parse --verify HEAD' );
	}

	private function getLocalBranches() {
		$branches = $this->cli( 'branch', false, true );
		foreach( $branches as $key => $branch )  {
			$branches[ $key ] = trim( $branch, '* ' );
		}

		return $branches;
	}

	private function getRemoteBranches() {
		$branches = $this->cli( 'branch -r', false, true );
		foreach( $branches as $key => $branch ) {
			if( strpos( $branch, 'origin/HEAD' ) !== false ) {
				unset( $branches[ $key ] );
				continue;
			}

			$branches[ $key ] = str_replace( 'origin/', '', $branch );
		}
		return $branches;
	}

	public function getCommits( array $params = null ) {
		$separator = ',|.';
		$filter    = null;
		$limit     = 50;
		
		if( is_array(  $params ) ) {
			// Each value quoted for the shell: they come from the filter form
			// and went into the git command as typed, so a value with a ; or
			// a $( ) in it ran as a command of its own.
			if( isset( $params['author'] ) && is_string( $params['author'] ) && $params['author'] !== '' ) {
				$limit .= ' --author=' . escapeshellarg( $params['author'] );
			}
			if( isset( $params['start_date'] ) && is_string( $params['start_date'] ) && $params['start_date'] !== '' ) {
				$limit .= ' --since=' . escapeshellarg( $params['start_date'] );
			}
			if( isset( $params['end_date'] ) && is_string( $params['end_date'] ) && $params['end_date'] !== '' ) {
				$limit .= ' --until=' . escapeshellarg( $params['end_date'] );
			}
		}
		
		$commits = $this->cli(
			'log -' . $limit . $filter . ' --pretty=format:"%H' . $separator . '%an' . $separator . '%cD' . $separator . '%s"',
                        false,
			true
		);
	
		$return = array();
		foreach( $commits as $commit ) {
			$commit = explode( $separator, $commit );
			if( count( $commit ) !== 4 ) {
				continue;
			}

			$return[] = array(
				'hash'   => $commit[0],
				'author' => $commit[1],
				'date'   => $commit[2],
				'title'  => $commit[3]
			);
		}

		return $return;
	}

	/**
	 * The remotes: name => fetch address, with any user name and password or
	 * token in an address left out, so the page never shows a credential.
	 */
	public function getRemotes() {
		$remotes = array();
		foreach( $this->cli( 'remote -v', false, true ) as $line ) {
			if( preg_match( '/^(\S+)\s+(\S+)\s+\(fetch\)$/', trim( $line ), $m ) ) {
				$remotes[ $m[1] ] = preg_replace( '#^([a-z][a-z0-9+.-]*://)[^/@]*@#i', '$1', $m[2] );
			}
		}
		return $remotes;
	}

	/**
	 * How $branch stands against $remote's copy of it, from the refs the last
	 * fetch left: array( 'ahead' => n, 'behind' => n ), or false when the
	 * remote has no such branch (yet).
	 */
	public function aheadBehind( $remote, $branch ) {
		if( !isset( $this->getRemotes()[ $remote ] ) || !in_array( $branch, $this->getLocalBranches(), true ) ) {
			return false;
		}
		$counts = $this->cli( 'rev-list --left-right --count ' . escapeshellarg( 'refs/remotes/' . $remote . '/' . $branch . '...refs/heads/' . $branch ) );
		if( !preg_match( '/^(\d+)\s+(\d+)$/', trim( $counts ), $m ) ) {
			return false;
		}
		return array( 'behind' => (int)$m[1], 'ahead' => (int)$m[2] );
	}

	/**
	 * The commits of HEAD that $remote has in none of its branches, as the
	 * last fetch knows them: hash => true, the newest $max. A remote nothing
	 * was fetched from has none of them.
	 */
	public function unpushedCommits( $remote, $max = 500 ) {
		if( !isset( $this->getRemotes()[ $remote ] ) ) {
			return array();
		}
		$hashes = $this->cli( 'rev-list --max-count=' . (int)$max . ' HEAD --not ' . escapeshellarg( '--remotes=' . $remote ), false, true );
		$result = array();
		foreach( $hashes as $hash ) {
			if( preg_match( '/^[0-9a-f]{40}$/', trim( $hash ) ) ) {
				$result[ trim( $hash ) ] = true;
			}
		}
		return $result;
	}

	/**
	 * Fetches $remote (with --prune). Returns array( 'exit', 'output' ).
	 */
	public function fetch( $remote ) {
		if( !isset( $this->getRemotes()[ $remote ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown remote' );
		}
		return $this->run( 'fetch --prune ' . escapeshellarg( $remote ) );
	}

	/**
	 * Pushes the local $branch to the branch of the same name on $remote.
	 * Never forced: a remote that has commits this branch lacks refuses the
	 * push, and the output says so. Returns array( 'exit', 'output' ).
	 */
	public function push( $remote, $branch ) {
		if( !isset( $this->getRemotes()[ $remote ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown remote' );
		}
		if( !in_array( $branch, $this->getLocalBranches(), true ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown local branch' );
		}
		return $this->run( 'push --porcelain ' . escapeshellarg( $remote ) . ' ' . escapeshellarg( 'refs/heads/' . $branch . ':refs/heads/' . $branch ) );
	}

	/**
	 * A remote name git accepts and a shell cannot misread: letters, digits,
	 * dot, dash and underscore, not starting with a dash or dot.
	 */
	public static function isRemoteName( $name ) {
		return is_string( $name ) && preg_match( '/^[A-Za-z0-9_][A-Za-z0-9._-]{0,99}$/', $name ) === 1;
	}

	/**
	 * A remote address: https://, http://, ssh://, git:// or file:// URL, the
	 * scp form user@host:path, or an absolute path. No spaces or control
	 * characters, no leading dash (it would be read as an option).
	 */
	public static function isRemoteUrl( $url ) {
		if( !is_string( $url ) || $url === '' || strlen( $url ) > 1000 || preg_match( '/[\s\x00-\x1f\x7f]/', $url ) || $url[0] === '-' ) {
			return false;
		}
		// No transport helper: "ext::" runs a command of its own, and "x::"
		// hands the address to a git-remote-x program.
		if( strpos( $url, '::' ) !== false ) {
			return false;
		}
		return preg_match( '#^(https?|ssh|git)://[^/]#i', $url ) === 1
			|| preg_match( '#^file:///[^/]#i', $url ) === 1
			|| preg_match( '#^[A-Za-z0-9._-]+@[A-Za-z0-9._-]+:[^/].*$#', $url ) === 1
			|| preg_match( '#^[A-Za-z0-9._-]+:[^/]#', $url ) === 1 && strpos( $url, '://' ) === false && preg_match( '#^[A-Za-z]:#', $url ) === 0
			|| $url[0] === '/';
	}

	/** Adds a remote. Returns array( 'exit', 'output' ). */
	public function addRemote( $name, $url ) {
		if( !self::isRemoteName( $name ) ) {
			return array( 'exit' => 1, 'output' => 'Not a remote name: letters, digits, dot, dash and underscore' );
		}
		if( isset( $this->getRemotes()[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'A remote of that name exists already' );
		}
		if( !self::isRemoteUrl( $url ) ) {
			return array( 'exit' => 1, 'output' => 'Not a remote address' );
		}
		return $this->run( 'remote add ' . escapeshellarg( $name ) . ' ' . escapeshellarg( $url ) );
	}

	/**
	 * Changes the address of a remote (fetch and push). An address the page
	 * showed with its credential left out and sent back unchanged changes
	 * nothing, so editing a remote never drops a token by accident.
	 */
	public function setRemoteUrl( $name, $url ) {
		$remotes = $this->getRemotes();
		if( !isset( $remotes[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown remote' );
		}
		if( $url === $remotes[ $name ] ) {
			return array( 'exit' => 0, 'output' => '' );
		}
		if( !self::isRemoteUrl( $url ) ) {
			return array( 'exit' => 1, 'output' => 'Not a remote address' );
		}
		return $this->run( 'remote set-url ' . escapeshellarg( $name ) . ' ' . escapeshellarg( $url ) );
	}

	/** Renames a remote; its remote-tracking branches follow. */
	public function renameRemote( $name, $newName ) {
		$remotes = $this->getRemotes();
		if( !isset( $remotes[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown remote' );
		}
		if( !self::isRemoteName( $newName ) ) {
			return array( 'exit' => 1, 'output' => 'Not a remote name: letters, digits, dot, dash and underscore' );
		}
		if( isset( $remotes[ $newName ] ) ) {
			return array( 'exit' => 1, 'output' => 'A remote of that name exists already' );
		}
		return $this->run( 'remote rename ' . escapeshellarg( $name ) . ' ' . escapeshellarg( $newName ) );
	}

	/** Removes a remote and its remote-tracking branches. The commits stay. */
	public function removeRemote( $name ) {
		if( !isset( $this->getRemotes()[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown remote' );
		}
		return $this->run( 'remote remove ' . escapeshellarg( $name ) );
	}

	/**
	 * A git command that talks to a remote, with its exit status. It must not
	 * wait for anyone: no password prompt (GIT_TERMINAL_PROMPT=0, ssh in
	 * batch mode), and it is stopped after 90 seconds; a missing credential
	 * then shows as git's own error instead of a request that never ends.
	 */
	private function run( $command ) {
		$env = 'GIT_TERMINAL_PROMPT=0 GIT_ASKPASS=/bin/false SSH_ASKPASS=/bin/false '
			. 'GIT_SSH_COMMAND=' . escapeshellarg( 'ssh -o BatchMode=yes -o ConnectTimeout=15' );
		$cmd = 'cd ' . escapeshellarg( self::$path ) . ' && ' . $env . ' timeout 90 git ' . $command . ' 2>&1; echo "__GITMANAGER_EXIT:$?"';
		$output = (string)shell_exec( $cmd );
		$exit = 1;
		if( preg_match( '/__GITMANAGER_EXIT:(\d+)\s*$/', $output, $m ) ) {
			$exit = (int)$m[1];
			$output = substr( $output, 0, -strlen( $m[0] ) );
		}
		if( $exit === 124 ) {
			$output .= "\n(stopped after 90 seconds)";
		}
		// No credentials in an address git prints (To https://token@host/...).
		$output = preg_replace( '#([a-z][a-z0-9+.-]*://)[^/@\s]+@#i', '$1***@', $output );
		return array( 'exit' => $exit, 'output' => trim( $output ) );
	}

	/** A commit hash: 7 to 40 hexadecimal characters, nothing else. */
	public static function isCommitHash( $hash ) {
		return is_string( $hash ) && preg_match( '/^[0-9a-f]{7,40}$/i', $hash ) === 1;
	}

	/**
	 * Checks out a branch the repository has. The name is validated and
	 * quoted, so a branch name is never read as a command or an option.
	 */
	public function checkout( $branch ) {
		if( !self::isBranchName( $branch ) ) {
			return 'Not a branch name';
		}
		return $this->cli( 'checkout ' . escapeshellarg( $branch ) );
	}

	public function pull( $branch, $regenerateAutoloads = false ) {
		if( !self::isBranchName( $branch ) ) {
			return 'Not a branch name';
		}
		return $this->cli( 'pull origin ' . escapeshellarg( $branch ), false, false, $regenerateAutoloads );
	}

	/** git log of one commit; only a commit hash is accepted. */
	public function commitInfo( $hash ) {
		if( !self::isCommitHash( $hash ) ) {
			return '';
		}
		return $this->cli( 'log -1 -p ' . escapeshellarg( $hash ) . ' --' );
	}

	/** Checks out one commit (detached HEAD); only a commit hash is accepted. */
	public function checkoutCommit( $hash ) {
		if( !self::isCommitHash( $hash ) ) {
			return 'Not a commit hash';
		}
		return $this->cli( 'checkout ' . escapeshellarg( $hash ) );
	}

	/**
	 * A submodule path inside the repository: relative, letters, digits, dot,
	 * dash, underscore and slashes, no "..", no leading dash, dot or slash.
	 */
	public static function isSubmodulePath( $path ) {
		return is_string( $path ) && strlen( $path ) <= 255
			&& preg_match( '#^[A-Za-z0-9_][A-Za-z0-9._/-]*$#', $path ) === 1
			&& strpos( $path, '..' ) === false && strpos( $path, '//' ) === false && substr( $path, -1 ) !== '/'
			&& strpos( $path, '.git' ) !== 0;
	}

	/** A branch name git accepts and the shell cannot misread. */
	public static function isBranchName( $branch ) {
		return is_string( $branch ) && strlen( $branch ) <= 200
			&& preg_match( '#^[A-Za-z0-9_][A-Za-z0-9._/-]*$#', $branch ) === 1
			&& strpos( $branch, '..' ) === false && strpos( $branch, '//' ) === false
			&& substr( $branch, -1 ) !== '/' && substr( $branch, -5 ) !== '.lock';
	}

	/**
	 * A submodule address: what a remote may be (isRemoteUrl), or a path
	 * relative to the superproject's remote (../name.git, ./name.git).
	 */
	public static function isSubmoduleUrl( $url ) {
		if( is_string( $url ) && preg_match( '#^\.\.?/[A-Za-z0-9._/-]+$#', $url ) === 1 && strpos( $url, '::' ) === false && strpos( $url, '//' ) === false ) {
			return true;
		}
		return self::isRemoteUrl( $url );
	}

	/**
	 * The submodules of .gitmodules with their state:
	 * name => array( 'name', 'path', 'url' (credentials left out), 'branch',
	 * 'commit', 'state' (current, not_initialized, changed, conflict, missing),
	 * 'describe' ).
	 */
	public function getSubmodules() {
		$modules = array();
		if( !is_file( rtrim( self::$path, '/' ) . '/.gitmodules' ) ) {
			return $modules;
		}
		foreach( $this->cli( 'config -f .gitmodules --get-regexp ' . escapeshellarg( '^submodule\..*\.(path|url|branch)$' ), false, true ) as $line ) {
			if( preg_match( '/^submodule\.(.+)\.(path|url|branch) (.*)$/', trim( $line ), $m ) ) {
				if( !isset( $modules[ $m[1] ] ) ) {
					$modules[ $m[1] ] = array( 'name' => $m[1], 'path' => '', 'url' => '', 'branch' => '', 'commit' => '', 'state' => 'missing', 'describe' => '' );
				}
				$value = $m[2] === 'url' ? preg_replace( '#^([a-z][a-z0-9+.-]*://)[^/@]*@#i', '$1', $m[3] ) : $m[3];
				$modules[ $m[1] ][ $m[2] ] = $value;
			}
		}
		$byPath = array();
		foreach( $modules as $name => $module ) {
			$byPath[ $module['path'] ] = $name;
		}
		foreach( $this->cli( 'submodule status', false, true ) as $line ) {
			// The mark may be missing: cli() trims the whole output, and with it
			// the space (up to date) in front of the first line.
			if( preg_match( '/^([ +\-U]?)([0-9a-f]{40}) (\S+)(?: \((.*)\))?$/', rtrim( $line ), $m ) && isset( $byPath[ $m[3] ] ) ) {
				$states = array( '' => 'current', ' ' => 'current', '-' => 'not_initialized', '+' => 'changed', 'U' => 'conflict' );
				$name = $byPath[ $m[3] ];
				$modules[ $name ]['commit'] = $m[2];
				$modules[ $name ]['state'] = $states[ $m[1] ];
				$modules[ $name ]['describe'] = isset( $m[4] ) ? $m[4] : '';
			}
		}
		ksort( $modules );
		return $modules;
	}

	/**
	 * Adds a submodule: clones $url into $path (optionally following
	 * $branch) and stages .gitmodules and the new path, for a commit.
	 */
	public function addSubmodule( $url, $path, $branch = '' ) {
		if( !self::isSubmoduleUrl( $url ) ) {
			return array( 'exit' => 1, 'output' => 'Not a submodule address' );
		}
		if( !self::isSubmodulePath( $path ) ) {
			return array( 'exit' => 1, 'output' => 'Not a submodule path: a relative path inside the repository' );
		}
		if( file_exists( rtrim( self::$path, '/' ) . '/' . $path ) ) {
			return array( 'exit' => 1, 'output' => 'That path exists already' );
		}
		if( $branch !== '' && !self::isBranchName( $branch ) ) {
			return array( 'exit' => 1, 'output' => 'Not a branch name' );
		}
		return $this->run( 'submodule add ' . ( $branch !== '' ? '-b ' . escapeshellarg( $branch ) . ' ' : '' )
			. '-- ' . escapeshellarg( $url ) . ' ' . escapeshellarg( $path ) );
	}

	/**
	 * Changes a submodule's address and the branch it follows ('' for none)
	 * in .gitmodules, carries the address into the submodule's own
	 * configuration (git submodule sync) and stages .gitmodules. An address
	 * shown without its credential and sent back unchanged is left as it is.
	 */
	public function editSubmodule( $name, $url, $branch ) {
		$modules = $this->getSubmodules();
		if( !isset( $modules[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown submodule' );
		}
		$module = $modules[ $name ];
		$key = 'submodule.' . $name;
		$output = array();
		if( $url !== $module['url'] ) {
			if( !self::isSubmoduleUrl( $url ) ) {
				return array( 'exit' => 1, 'output' => 'Not a submodule address' );
			}
			$step = $this->run( 'config -f .gitmodules ' . escapeshellarg( $key . '.url' ) . ' ' . escapeshellarg( $url ) );
			if( $step['exit'] !== 0 ) return $step;
			$output[] = $step['output'];
		}
		if( $branch !== $module['branch'] ) {
			if( $branch !== '' && !self::isBranchName( $branch ) ) {
				return array( 'exit' => 1, 'output' => 'Not a branch name' );
			}
			$step = $branch === ''
				? $this->run( 'config -f .gitmodules --unset ' . escapeshellarg( $key . '.branch' ) )
				: $this->run( 'config -f .gitmodules ' . escapeshellarg( $key . '.branch' ) . ' ' . escapeshellarg( $branch ) );
			if( $step['exit'] !== 0 ) return $step;
			$output[] = $step['output'];
		}
		if( !$output ) {
			return array( 'exit' => 0, 'output' => '' );
		}
		foreach( array( 'submodule sync -- ' . escapeshellarg( $module['path'] ), 'add .gitmodules' ) as $command ) {
			$step = $this->run( $command );
			if( $step['exit'] !== 0 ) return $step;
			$output[] = $step['output'];
		}
		return array( 'exit' => 0, 'output' => trim( implode( "\n", array_filter( $output, 'strlen' ) ) ) );
	}

	/** Initialises and updates one submodule to the commit the branch records. */
	public function updateSubmodule( $name ) {
		$modules = $this->getSubmodules();
		if( !isset( $modules[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown submodule' );
		}
		return $this->run( 'submodule update --init --recursive -- ' . escapeshellarg( $modules[ $name ]['path'] ) );
	}

	/**
	 * Removes a submodule: deinit (its working tree emptied), git rm (the
	 * path and its .gitmodules entry, staged for a commit), and its copy of
	 * the repository under .git/modules, so that it can be added again.
	 */
	public function removeSubmodule( $name ) {
		$modules = $this->getSubmodules();
		if( !isset( $modules[ $name ] ) ) {
			return array( 'exit' => 1, 'output' => 'Unknown submodule' );
		}
		$path = $modules[ $name ]['path'];
		if( !self::isSubmodulePath( $path ) ) {
			return array( 'exit' => 1, 'output' => 'The submodule path in .gitmodules is not one this page removes' );
		}
		$output = array();
		foreach( array( 'submodule deinit -f -- ' . escapeshellarg( $path ), 'rm -f -- ' . escapeshellarg( $path ) ) as $command ) {
			$step = $this->run( $command );
			$output[] = $step['output'];
			if( $step['exit'] !== 0 ) {
				return array( 'exit' => $step['exit'], 'output' => trim( implode( "\n", $output ) ) );
			}
		}
		// Its repository copy, only when it lies inside this repository's
		// .git/modules.
		$gitDir = trim( $this->cli( 'rev-parse --absolute-git-dir' ) );
		$modulesDir = realpath( $gitDir . '/modules' );
		$moduleDir = realpath( $gitDir . '/modules/' . $name );
		if( $modulesDir && $moduleDir && strpos( $moduleDir, $modulesDir . '/' ) === 0 && is_dir( $moduleDir ) ) {
			$removed = eZDir::recursiveDelete( $moduleDir, false );
			$output[] = $removed ? 'Removed ' . $moduleDir : 'Could not remove ' . $moduleDir;
		}
		return array( 'exit' => 0, 'output' => trim( implode( "\n", array_filter( $output, 'strlen' ) ) ) );
	}

	public function updateSubmodules() {
                $result = $this->cli( 'submodule update --init --recursive', false );

                if( strpos( $result, 'You need to run this command from the toplevel of the working tree') !== false )
                {
                    $result = $this->cli( 'submodule update --init --recursive', '..' );
                }

		return $result;
	}

	private function cli( $command, $path = false, $explodeLines = false, $regenerateAutoloads = false ) {
                if( $path === false )
                {
                    $cdCmd = 'cd ' . escapeshellarg( self::$path );
                }
                else
                {
                    $cdCmd = 'cd ' . escapeshellarg( $path );
                }

		$cmd    = $cdCmd . ' && git ' . $command . ' 2>&1';
		$result = trim( shell_exec( $cmd ) );

                if( $regenerateAutoloads === true )
                {
                    $result .= trim( shell_exec( $cdCmd . ' && ./bin/php/ezpgenerateautoloads.php 2>&1' ) );
                    // clean up results for display within the browser for easy readability
                    $result = str_replace( '[0m', '', str_replace( '[31m', '', str_replace( "of them to the autoload array.\n", 'of them to the autoload array.', str_replace('Scanning for PHP-files', "\n\nScanning for PHP-files", $result ) ) ) );
                }

		if( $explodeLines ) {
			$result = explode( "\n", $result );
		}

		return $result;
	}
}

?>