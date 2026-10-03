<?php
/**
 * The upstream of the installation's repository at a glance: where the
 * checked out branch stands against its branch on the remote (origin), the
 * commits the remote has that the installation does not, the local ones the
 * other way, and what is not committed yet. And a fetch of that remote,
 * which only updates the remote-tracking branches: never a pull, merge,
 * reset or checkout.
 *
 * The status is kept in the cache directory and computed again when HEAD,
 * the branch, the remote-tracking branch, FETCH_HEAD or the index change, or
 * when it is older than [UpstreamSettings] StatusCacheTTL seconds (the
 * working tree's counts are the only part that can change without any of
 * those files changing).
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package GitManager
 */

class GitManagerUpstream
{
	const CACHE_VERSION = 1;

	/** @var string the repository's working tree, absolute */
	private $root;

	/** @var string|null the repository's git directory, absolute */
	private $gitDir;

	/** @var array [UpstreamSettings] */
	private $settings;

	public function __construct( $root = null ) {
		$root = $root === null ? self::repositoryPath() : $root;
		$real = realpath( $root );
		$this->root = $real !== false ? $real : $root;
		$this->gitDir = $this->findGitDir();
		$this->settings = self::settings();
	}

	/**
	 * [UpstreamSettings] of git_manager.ini, with the defaults.
	 */
	public static function settings() {
		$settings = array(
			'Remote'         => 'origin',
			'Branch'         => '',
			'FetchTimeout'   => 60,
			'HttpsFallback'  => 'enabled',
			'WebUrl'         => '',
			'MaxCommits'     => 100,
			'StatusCacheTTL' => 60
		);
		$ini = eZINI::instance( 'git_manager.ini' );
		if( $ini->hasGroup( 'UpstreamSettings' ) ) {
			foreach( array_keys( $settings ) as $name ) {
				if( $ini->hasVariable( 'UpstreamSettings', $name ) ) {
					$settings[ $name ] = $ini->variable( 'UpstreamSettings', $name );
				}
			}
		}
		$settings['FetchTimeout']   = max( 5, min( 600, (int)$settings['FetchTimeout'] ) );
		$settings['MaxCommits']     = max( 1, min( 1000, (int)$settings['MaxCommits'] ) );
		$settings['StatusCacheTTL'] = max( 0, (int)$settings['StatusCacheTTL'] );
		if( !GitManager::isRemoteName( $settings['Remote'] ) ) {
			$settings['Remote'] = 'origin';
		}
		if( $settings['Branch'] !== '' && !GitManager::isBranchName( $settings['Branch'] ) ) {
			$settings['Branch'] = '';
		}
		return $settings;
	}

	/**
	 * Where GitManager runs git. A process that loaded GitManager before it
	 * had repositoryPath() (a Velocity worker not yet restarted) gets './',
	 * which is what GitManager used unless .git is a symbolic link.
	 */
	private static function repositoryPath() {
		return method_exists( 'GitManager', 'repositoryPath' ) ? GitManager::repositoryPath() : './';
	}

	public function remoteName() {
		return $this->settings['Remote'];
	}

	/**
	 * The status, from the cache when nothing it depends on changed.
	 *
	 * @return array see compute(); plus 'cached' (bool) and 'seconds' (float, the time it took here)
	 */
	public function status( $refresh = false ) {
		$start = microtime( true );
		$key = $this->cacheKey();
		$file = $this->cacheFile();
		if( !$refresh && $key !== null && $file !== null && is_file( $file ) ) {
			$data = json_decode( (string)@file_get_contents( $file ), true );
			if( is_array( $data ) && isset( $data['key'], $data['status'] ) && $data['key'] === $key
				&& ( $this->settings['StatusCacheTTL'] === 0 || time() - (int)$data['status']['computed'] < $this->settings['StatusCacheTTL'] ) ) {
				$status = $data['status'];
				$status['fetched_iso'] = $status['fetched'] ? date( 'c', $status['fetched'] ) : '';
				$status['computed_iso'] = date( 'c', $status['computed'] );
				$status['cached'] = true;
				$status['seconds'] = microtime( true ) - $start;
				return $status;
			}
		}
		$status = $this->compute();
		if( $key !== null && $file !== null ) {
			$this->writeCache( $file, array( 'key' => $key, 'status' => $status ) );
		}
		$status['fetched_iso'] = $status['fetched'] ? date( 'c', $status['fetched'] ) : '';
		$status['computed_iso'] = date( 'c', $status['computed'] );
		$status['cached'] = false;
		$status['seconds'] = microtime( true ) - $start;
		return $status;
	}

	/**
	 * Works the status out with git.
	 */
	public function compute() {
		$remote = $this->settings['Remote'];
		$remotes = GitManager::getInstance()->getRemotes();
		$status = array(
			'computed'       => time(),
			'remote'         => $remote,
			'remote_exists'  => isset( $remotes[ $remote ] ),
			'url'            => isset( $remotes[ $remote ] ) ? $remotes[ $remote ] : '',
			'web_url'        => '',
			'branch'         => '',
			'detached'       => false,
			'head'           => '',
			'tracking'       => '',
			'tracking_set'   => false,
			'tracking_exists'=> false,
			'remote_head'    => '',
			'remote_date'    => '',
			'remote_subject' => '',
			'remote_url'     => '',
			'fetched'        => 0,
			'ahead'          => 0,
			'behind'         => 0,
			'state'          => 'unknown',
			'behind_commits' => array(),
			'ahead_commits'  => array(),
			'max_commits'    => $this->settings['MaxCommits'],
			'worktree'       => array( 'modified' => 0, 'staged' => 0, 'untracked' => 0, 'conflicts' => 0, 'total' => 0 ),
			'error'          => ''
		);
		$status['web_url'] = self::webUrl( $status['url'], $this->settings['WebUrl'] );
		$status['fetched'] = $this->fetchedAt();

		$head = $this->git( array( 'rev-parse', '--verify', '-q', 'HEAD' ) );
		$status['head'] = $head['exit'] === 0 ? trim( $head['output'] ) : '';
		$branch = $this->git( array( 'symbolic-ref', '-q', '--short', 'HEAD' ) );
		if( $branch['exit'] === 0 && trim( $branch['output'] ) !== '' ) {
			$status['branch'] = trim( $branch['output'] );
		} else {
			$status['detached'] = true;
		}

		// The branch to compare with: the configured upstream when it is on
		// this remote, else the setting, else the same name on the remote.
		$tracking = '';
		if( $this->settings['Branch'] !== '' ) {
			$tracking = $remote . '/' . $this->settings['Branch'];
		} elseif( $status['branch'] !== '' ) {
			$upstream = $this->git( array( 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}' ) );
			$name = trim( $upstream['output'] );
			if( $upstream['exit'] === 0 && strpos( $name, $remote . '/' ) === 0 ) {
				$tracking = $name;
				$status['tracking_set'] = true;
			} else {
				$tracking = $remote . '/' . $status['branch'];
			}
		} else {
			$default = $this->git( array( 'symbolic-ref', '-q', '--short', 'refs/remotes/' . $remote . '/HEAD' ) );
			$tracking = $default['exit'] === 0 && trim( $default['output'] ) !== '' ? trim( $default['output'] ) : $remote . '/main';
		}
		$status['tracking'] = $tracking;
		$ref = 'refs/remotes/' . $tracking;

		$tip = $this->git( array( 'log', '-1', '--format=%H%x1f%cI%x1f%s', $ref, '--' ) );
		if( $tip['exit'] === 0 && trim( $tip['output'] ) !== '' ) {
			$parts = explode( "\x1f", trim( $tip['output'] ), 3 );
			$status['tracking_exists'] = true;
			$status['remote_head']    = $parts[0];
			$status['remote_date']    = isset( $parts[1] ) ? $parts[1] : '';
			$status['remote_subject'] = isset( $parts[2] ) ? $parts[2] : '';
			$status['remote_url']     = $status['web_url'] !== '' ? self::commitUrl( $status['web_url'], $parts[0] ) : '';
		}

		if( $status['tracking_exists'] && $status['head'] !== '' ) {
			$counts = $this->git( array( 'rev-list', '--left-right', '--count', 'HEAD...' . $ref, '--' ) );
			if( $counts['exit'] === 0 && preg_match( '/^(\d+)\s+(\d+)$/', trim( $counts['output'] ), $m ) ) {
				$status['ahead']  = (int)$m[1];
				$status['behind'] = (int)$m[2];
				if( $status['ahead'] === 0 && $status['behind'] === 0 ) {
					$status['state'] = 'current';
				} elseif( $status['ahead'] === 0 ) {
					$status['state'] = 'behind';
				} elseif( $status['behind'] === 0 ) {
					$status['state'] = 'ahead';
				} else {
					$status['state'] = 'diverged';
				}
			} else {
				$status['error'] = trim( $counts['output'] );
			}
			if( $status['behind'] > 0 ) {
				$status['behind_commits'] = $this->commits( 'HEAD..' . $ref );
			}
			if( $status['ahead'] > 0 ) {
				$status['ahead_commits'] = $this->commits( $ref . '..HEAD' );
			}
		} elseif( !$status['remote_exists'] ) {
			$status['state'] = 'no_remote';
		} else {
			$status['state'] = 'not_fetched';
		}

		$status['worktree'] = $this->worktree();
		return $status;
	}

	/**
	 * The commits of a range, newest first, at most MaxCommits: hash, short
	 * hash, date (ISO 8601), author name (never an address), subject, url.
	 */
	private function commits( $range ) {
		$log = $this->git( array( 'log', '--max-count=' . $this->settings['MaxCommits'], '--format=%H%x1f%cI%x1f%an%x1f%s%x1e', $range, '--' ) );
		$commits = array();
		if( $log['exit'] !== 0 ) {
			return $commits;
		}
		$webUrl = self::webUrl( GitManager::getInstance()->getRemotes()[ $this->settings['Remote'] ] ?? '', $this->settings['WebUrl'] );
		foreach( explode( "\x1e", $log['output'] ) as $record ) {
			$parts = explode( "\x1f", trim( $record ), 4 );
			if( count( $parts ) !== 4 || !preg_match( '/^[0-9a-f]{40}$/', $parts[0] ) ) {
				continue;
			}
			$commits[] = array(
				'hash'    => $parts[0],
				'short'   => substr( $parts[0], 0, 10 ),
				'date'    => $parts[1],
				'author'  => $parts[2],
				'subject' => $parts[3],
				'url'     => $webUrl !== '' ? self::commitUrl( $webUrl, $parts[0] ) : ''
			);
		}
		return $commits;
	}

	/**
	 * Counts of what is not committed: modified (tracked files changed in the
	 * working tree or the index), staged, untracked (as git status lists
	 * them: a new directory counts once), conflicts.
	 */
	private function worktree() {
		$counts = array( 'modified' => 0, 'staged' => 0, 'untracked' => 0, 'conflicts' => 0, 'total' => 0 );
		$result = $this->git( array( 'status', '--porcelain=v1', '-z', '--ignore-submodules=dirty' ) );
		if( $result['exit'] !== 0 ) {
			return $counts;
		}
		$entries = explode( "\0", $result['output'] );
		for( $i = 0, $n = count( $entries ); $i < $n; $i++ ) {
			$entry = $entries[ $i ];
			if( strlen( $entry ) < 4 ) {
				continue;
			}
			$x = $entry[0];
			$y = $entry[1];
			$counts['total']++;
			if( $x === '?' ) {
				$counts['untracked']++;
				continue;
			}
			if( $x === '!' ) {
				$counts['total']--;
				continue;
			}
			if( $x === 'U' || $y === 'U' || ( $x === 'A' && $y === 'A' ) || ( $x === 'D' && $y === 'D' ) ) {
				$counts['conflicts']++;
			} else {
				$counts['modified']++;
				if( $x !== ' ' ) {
					$counts['staged']++;
				}
			}
			// A rename or copy is followed by its original path.
			if( $x === 'R' || $x === 'C' ) {
				$i++;
			}
		}
		return $counts;
	}

	/**
	 * Fetches the remote (git fetch --prune): only the remote-tracking
	 * branches and FETCH_HEAD change. Never asks for a password and stops
	 * after FetchTimeout seconds. When the web server runs as root (Velocity),
	 * git runs as the owner of the remote-tracking branches, so nothing in the
	 * repository changes owner. When the remote's SSH address cannot be used
	 * by this user and it is a GitHub repository, it is fetched anonymously
	 * over HTTPS into the same remote-tracking branches (HttpsFallback).
	 *
	 * @return array( 'exit', 'output', 'user', 'via' (ssh, https, http, git, file; '' when git did not run), 'seconds', 'reason' )
	 */
	public function fetch() {
		$start = microtime( true );
		$remote = $this->settings['Remote'];
		$remotes = GitManager::getInstance()->getRemotes();
		$user = self::processUser();
		$result = array( 'exit' => 1, 'output' => '', 'user' => $user['name'], 'via' => '', 'seconds' => 0.0, 'reason' => '' );
		if( !isset( $remotes[ $remote ] ) ) {
			$result['output'] = 'Unknown remote ' . $remote;
			$result['reason'] = 'no_remote';
			return $result;
		}
		if( $this->gitDir === null ) {
			$result['output'] = 'Not a git repository: ' . $this->root;
			$result['reason'] = 'no_repository';
			return $result;
		}

		$prefix = '';
		$refDir = $this->gitDir . '/refs/remotes/' . $remote;
		if( $user['uid'] === 0 ) {
			// As the owner of what the fetch writes.
			$owner = @fileowner( is_dir( $refDir ) ? $refDir : $this->gitDir );
			if( $owner !== false && $owner !== 0 && function_exists( 'posix_getpwuid' ) ) {
				$info = posix_getpwuid( $owner );
				if( $info && isset( $info['name'] ) && preg_match( '/^[a-z_][a-z0-9_.-]*$/i', $info['name'] ) ) {
					$prefix = 'runuser -u ' . escapeshellarg( $info['name'] ) . ' -- env HOME=' . escapeshellarg( $info['dir'] ) . ' ';
					$result['user'] = $info['name'];
				}
			}
		} else {
			// Say why before git tries: it would fetch and then fail to record it.
			$blocked = array();
			foreach( array( $this->gitDir, $this->gitDir . '/objects', is_dir( $refDir ) ? $refDir : null, is_file( $this->gitDir . '/FETCH_HEAD' ) ? $this->gitDir . '/FETCH_HEAD' : null ) as $path ) {
				if( $path !== null && !is_writable( $path ) ) {
					$blocked[] = $this->relative( $path ) . ' (' . self::ownerName( $path ) . ')';
				}
			}
			if( $blocked ) {
				$result['output'] = 'The site runs as ' . $user['name'] . ', who cannot write ' . implode( ', ', $blocked ) . ".\n"
					. 'git would fetch and then fail to record what it fetched; nothing was fetched. '
					. 'Fetch on the command line as the owner of these files, or give them to ' . $user['name'] . '.';
				$result['reason'] = 'permission';
				$result['seconds'] = microtime( true ) - $start;
				return $result;
			}
		}

		$timeout = $this->settings['FetchTimeout'];
		$step = $this->git( array( 'fetch', '--prune', $remote ), $timeout, $prefix, true );
		$result['exit'] = $step['exit'];
		$result['output'] = $step['output'];
		$result['via'] = preg_match( '#^(https?|git|file)://#i', $remotes[ $remote ], $scheme ) ? strtolower( $scheme[1] ) : ( $remotes[ $remote ][0] === '/' ? 'file' : 'ssh' );

		// The SSH address does not work for this user (a host alias of
		// another user's ~/.ssh/config, no key): GitHub over HTTPS.
		if( $step['exit'] !== 0 && $result['via'] === 'ssh' && $this->settings['HttpsFallback'] === 'enabled'
			&& preg_match( '/Could not resolve hostname|Permission denied \(publickey|Host key verification failed|No such identity|Could not read from remote repository/i', $step['output'] ) ) {
			$https = self::httpsCloneUrl( $remotes[ $remote ] );
			if( $https !== '' ) {
				$refspecs = array();
				$config = $this->git( array( 'config', '--get-all', 'remote.' . $remote . '.fetch' ) );
				foreach( explode( "\n", trim( $config['output'] ) ) as $spec ) {
					$spec = trim( $spec );
					if( preg_match( '#^\+?refs/[A-Za-z0-9._/*-]+:refs/remotes/' . preg_quote( $remote, '#' ) . '/[A-Za-z0-9._/*-]+$#', $spec ) ) {
						$refspecs[] = $spec;
					}
				}
				if( !$refspecs ) {
					$refspecs[] = '+refs/heads/*:refs/remotes/' . $remote . '/*';
				}
				$retry = $this->git( array_merge( array( 'fetch', '--prune', $https ), $refspecs ), $timeout, $prefix, true );
				$result['output'] = trim( $step['output'] ) . "\n\n" . 'Fetched anonymously over HTTPS instead (' . $https . '):' . "\n" . $retry['output'];
				$result['exit'] = $retry['exit'];
				$result['via'] = 'https';
			}
		}
		if( $result['exit'] === 124 ) {
			$result['output'] .= "\n(stopped after " . $timeout . ' seconds)';
			$result['reason'] = 'timeout';
		} elseif( $result['exit'] !== 0 ) {
			$result['reason'] = 'git';
		}
		$result['output'] = trim( preg_replace( '#([a-z][a-z0-9+.-]*://)[^/@\s]+@#i', '$1***@', $result['output'] ) );
		$result['seconds'] = microtime( true ) - $start;
		return $result;
	}

	/** The last fetch: FETCH_HEAD's modification time, 0 when never fetched. */
	public function fetchedAt() {
		if( $this->gitDir === null ) {
			return 0;
		}
		clearstatcache( true, $this->gitDir . '/FETCH_HEAD' );
		$time = @filemtime( $this->gitDir . '/FETCH_HEAD' );
		return $time === false ? 0 : $time;
	}

	/**
	 * The remote's address in a browser: https://host/owner/repository, from
	 * git@host:owner/repository.git, ssh://git@host/owner/repository.git or
	 * https://host/owner/repository.git. An SSH host alias with "github" in
	 * its name (github-as-someone, an alias for github.com in ~/.ssh/config)
	 * is GitHub. $override (WebUrl) wins when set.
	 */
	public static function webUrl( $url, $override = '' ) {
		if( is_string( $override ) && preg_match( '#^https?://[^\s"<>]+$#i', $override ) ) {
			return rtrim( $override, '/' );
		}
		$url = (string)$url;
		if( strpos( $url, '://' ) !== false ) {
			$pattern = '#^(?:https?|git|ssh)://(?:[^@/\s]+@)?([A-Za-z0-9._-]+)(?::\d+)?/([A-Za-z0-9._~/-]+?)(?:\.git)?/?$#i';
		} else {
			// The scp form: [user@]host:owner/repository.git
			$pattern = '#^(?:[^@\s:]+@)?([A-Za-z0-9._-]+):(?!/)([A-Za-z0-9._~/-]+?)(?:\.git)?/?$#';
		}
		if( !preg_match( $pattern, $url, $m ) ) {
			return '';
		}
		$host = strtolower( $m[1] );
		$path = $m[2];
		if( strpos( $host, 'github' ) !== false && $host !== 'github.com' && strpos( $host, '.' ) === false ) {
			$host = 'github.com';
		}
		if( strpos( $host, '.' ) === false ) {
			return '';
		}
		return 'https://' . $host . '/' . trim( $path, '/' );
	}

	/** The repository's HTTPS clone address when it is on GitHub, else ''. */
	public static function httpsCloneUrl( $url ) {
		$web = self::webUrl( $url );
		return strpos( $web, 'https://github.com/' ) === 0 ? $web . '.git' : '';
	}

	/** A commit's page: GitLab's /-/commit/, everyone else's /commit/. */
	public static function commitUrl( $webUrl, $hash ) {
		$host = (string)parse_url( $webUrl, PHP_URL_HOST );
		return $webUrl . ( strpos( $host, 'gitlab' ) !== false ? '/-/commit/' : '/commit/' ) . $hash;
	}

	/** Who this process is: array( 'uid', 'name' ). */
	public static function processUser() {
		$uid = function_exists( 'posix_geteuid' ) ? posix_geteuid() : -1;
		$name = (string)$uid;
		if( $uid >= 0 && function_exists( 'posix_getpwuid' ) ) {
			$info = posix_getpwuid( $uid );
			if( $info && isset( $info['name'] ) ) {
				$name = $info['name'];
			}
		}
		return array( 'uid' => $uid, 'name' => $name );
	}

	private static function ownerName( $path ) {
		$owner = @fileowner( $path );
		if( $owner !== false && function_exists( 'posix_getpwuid' ) ) {
			$info = posix_getpwuid( $owner );
			if( $info && isset( $info['name'] ) ) {
				return $info['name'];
			}
		}
		return (string)$owner;
	}

	private function relative( $path ) {
		return strpos( $path, $this->root . '/' ) === 0 ? substr( $path, strlen( $this->root ) + 1 ) : $path;
	}

	/**
	 * What the status depends on, from the file system only: HEAD, the
	 * branch's ref, the remote-tracking ref, packed-refs, FETCH_HEAD, the
	 * index, the settings.
	 */
	private function cacheKey() {
		if( $this->gitDir === null ) {
			return null;
		}
		$parts = array( self::CACHE_VERSION, $this->root, serialize( $this->settings ) );
		$headFile = $this->gitDir . '/HEAD';
		$head = (string)@file_get_contents( $headFile );
		$parts[] = trim( $head );
		$files = array( 'packed-refs', 'FETCH_HEAD', 'index', 'refs/remotes/' . $this->settings['Remote'] . '/HEAD' );
		if( preg_match( '#^ref: (refs/heads/[A-Za-z0-9._/-]+)$#', trim( $head ), $m ) ) {
			$files[] = $m[1];
			$name = substr( $m[1], strlen( 'refs/heads/' ) );
			$files[] = 'refs/remotes/' . $this->settings['Remote'] . '/' . ( $this->settings['Branch'] !== '' ? $this->settings['Branch'] : $name );
		} elseif( $this->settings['Branch'] !== '' ) {
			$files[] = 'refs/remotes/' . $this->settings['Remote'] . '/' . $this->settings['Branch'];
		}
		$files[] = 'config';
		clearstatcache();
		foreach( $files as $file ) {
			$stat = @stat( $this->gitDir . '/' . $file );
			$parts[] = $file . '=' . ( $stat ? $stat['mtime'] . '.' . $stat['size'] . '.' . $stat['ino'] : '-' );
		}
		return sha1( implode( "\n", $parts ) );
	}

	private function cacheFile() {
		$dir = eZSys::cacheDirectory() . '/git_manager';
		if( !is_dir( $dir ) ) {
			eZDir::mkdir( $dir, false, true );
		}
		return is_dir( $dir ) && is_writable( $dir ) ? $dir . '/upstream-' . md5( $this->root ) . '.json' : null;
	}

	/** Written whole and renamed into place: a reader never sees half a file. */
	private function writeCache( $file, array $data ) {
		$tmp = $file . '.' . getmypid() . '.' . mt_rand() . '.tmp';
		if( @file_put_contents( $tmp, json_encode( $data ) ) === false ) {
			return false;
		}
		@chmod( $tmp, 0666 );
		if( !@rename( $tmp, $file ) ) {
			@unlink( $tmp );
			return false;
		}
		return true;
	}

	/** The git directory: .git, a .git symbolic link, or a .git file (gitdir: ...). */
	private function findGitDir() {
		$dot = self::repositoryPath();
		$dot = ( $dot === './' ? $this->root : rtrim( $dot, '/' ) ) . '/.git';
		if( is_dir( $dot ) ) {
			return realpath( $dot );
		}
		if( is_file( $dot ) && preg_match( '/^gitdir:\s*(.+)$/m', (string)@file_get_contents( $dot ), $m ) ) {
			$path = trim( $m[1] );
			$path = $path[0] === '/' ? $path : dirname( $dot ) . '/' . $path;
			$real = realpath( $path );
			return $real !== false ? $real : null;
		}
		return null;
	}

	/**
	 * Runs git in the repository with each argument quoted. Reads leave
	 * stderr out; a fetch keeps it (that is its whole output).
	 */
	private function git( array $args, $timeout = 20, $prefix = '', $withErrors = false ) {
		$env = 'LC_ALL=C GIT_OPTIONAL_LOCKS=0 GIT_TERMINAL_PROMPT=0 GIT_ASKPASS=/bin/false SSH_ASKPASS=/bin/false '
			. 'GIT_SSH_COMMAND=' . escapeshellarg( 'ssh -o BatchMode=yes -o ConnectTimeout=15' ) . ' ';
		$command = 'git -c ' . escapeshellarg( 'safe.directory=' . $this->root ) . ' -c core.quotepath=off';
		foreach( $args as $arg ) {
			$command .= ' ' . escapeshellarg( $arg );
		}
		$cmd = 'cd ' . escapeshellarg( $this->root ) . ' && ' . $prefix . 'env ' . $env . 'timeout ' . (int)$timeout . ' ' . $command
			. ( $withErrors ? ' 2>&1' : ' 2>/dev/null' ) . '; echo "__GITMANAGER_EXIT:$?"';
		$output = (string)shell_exec( $cmd );
		$exit = 1;
		if( preg_match( '/__GITMANAGER_EXIT:(\d+)\s*$/', $output, $m ) ) {
			$exit = (int)$m[1];
			$output = substr( $output, 0, -strlen( $m[0] ) );
		}
		return array( 'exit' => $exit, 'output' => $output );
	}
}

?>
