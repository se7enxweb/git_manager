<?php
/**
 * History with file counts, and what changed between two commits: the argument lists for git (never a shell command
 * line; GitManager::gitAt() runs git without a shell) and the parsers of what git prints.
 *
 * - history: `git log --shortstat` with the commit log's filters (GitManagerLog::filterArguments()), one page at a time
 * - files: `git diff --numstat -z` and `--name-status -z` of two commits (or a commit and the working tree), merged:
 *   path, status (A M D T), lines added and removed, binary or text
 * - patch: `git diff` of one text file, parsed into lines with their kind; binary files are never read
 * - tables: a database repository written per table (schema/<table>.sql, data/<table>.sql, data/<table>/part-*.sql)
 *   summed per table from the file list alone, so no row of it is ever read
 *
 * Plain PHP with no Exponential calls, so the unit tests and the hosting tools run it without a bootstrap.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package GitManager
 */

class GitManagerDiff
{
    /** The record separator of a history (ASCII record separator) and the field separator (unit separator). */
    const RECORD = "\x1e";
    const FIELD = "\x1f";

    /** File name endings shown as text diffs: code, templates, settings, styles, scripts, documents. */
    const TEXT_SUFFIXES = array( 'php', 'tpl', 'ini', 'append', 'css', 'scss', 'less', 'js', 'mjs', 'ts', 'json', 'xml', 'html', 'htm',
                                 'twig', 'yml', 'yaml', 'md', 'txt', 'csv', 'sh', 'py', 'pl', 'rb', 'sql', 'svg', 'conf', 'htaccess',
                                 'gitignore', 'lock', 'dist', 'po', 'ts', 'inc', 'module', 'theme', 'vue', 'jsx', 'tsx', 'env', 'example' );

    /** A commit as an argument: 7 to 40 (or 64) hex digits. */
    public static function isCommit( $value )
    {
        return is_string( $value ) && preg_match( '/^[0-9a-f]{7,64}$/D', $value ) === 1;
    }

    /**
     * A path inside a repository as a user may name it: relative, no "..", no leading dash, no control characters,
     * at most 500 bytes. A folder may end with "/".
     */
    public static function isPath( $value )
    {
        if ( !is_string( $value ) || $value === '' || strlen( $value ) > 500 || preg_match( '/[\x00-\x1f\x7f]/', $value ) )
            return false;
        if ( $value[0] === '/' || $value[0] === '-' || $value === '.' )
            return false;
        foreach ( explode( '/', rtrim( $value, '/' ) ) as $part )
            if ( $part === '' || $part === '.' || $part === '..' || $part === '.git' )
                return false;
        return true;
    }

    /** Whether a path is shown as a text diff (by its name; git's own binary test decides as well). */
    public static function isTextPath( $path )
    {
        $base = strtolower( basename( (string)$path ) );
        if ( in_array( $base, array( 'makefile', 'dockerfile', 'upstream', 'readme', 'license', 'tables', 'composer.lock', '.htaccess', '.gitignore' ), true ) )
            return true;
        $dot = strrpos( $base, '.' );
        return $dot !== false && in_array( substr( $base, $dot + 1 ), self::TEXT_SUFFIXES, true );
    }

    // ---------------------------------------------------------------------------------------------- history

    /**
     * `git log` of one page with the files changed and lines of each commit: hash, author, committer time (Unix),
     * subject, then the --shortstat line. Filters as the commit log has them (author, grep, since, until).
     */
    public static function historyArguments( array $state, $ref, $offset, $limit )
    {
        $ref = is_string( $ref ) && ( $ref === 'HEAD' || self::isCommit( $ref ) || ( strpos( $ref, 'refs/' ) === 0 && GitManagerLog::isRefName( substr( $ref, 5 ) ) ) ) ? $ref : 'HEAD';
        return array_merge(
            array( 'log', '--no-color', '--skip=' . max( 0, (int)$offset ), '--max-count=' . max( 1, min( 200, (int)$limit ) ),
                   '--shortstat', '--format=' . '%x1e%H%x1f%an%x1f%ct%x1f%s' ),
            GitManagerLog::filterArguments( $state ),
            array( '--end-of-options', $ref, '--' )
        );
    }

    /** The commits of a historyArguments() output: hash, author, time, title, files, insertions, deletions. */
    public static function parseHistory( $output )
    {
        $commits = array();
        foreach ( explode( self::RECORD, (string)$output ) as $record )
        {
            $lines = preg_split( '/\r?\n/', trim( $record, "\r\n" ) );
            $fields = explode( self::FIELD, (string)array_shift( $lines ) );
            if ( count( $fields ) !== 4 || !preg_match( '/^[0-9a-f]{40}([0-9a-f]{24})?$/D', $fields[0] ) )
                continue;
            $stat = self::parseShortstat( implode( ' ', $lines ) );
            $commits[] = array( 'hash' => $fields[0], 'author' => $fields[1], 'time' => (int)$fields[2], 'title' => $fields[3] ) + $stat;
        }
        return $commits;
    }

    /** " 3 files changed, 10 insertions(+), 2 deletions(-)" as files, insertions, deletions (0 when absent). */
    public static function parseShortstat( $line )
    {
        $out = array( 'files' => 0, 'insertions' => 0, 'deletions' => 0 );
        if ( preg_match( '/(\d+) files? changed/', (string)$line, $m ) )
            $out['files'] = (int)$m[1];
        if ( preg_match( '/(\d+) insertions?\(\+\)/', (string)$line, $m ) )
            $out['insertions'] = (int)$m[1];
        if ( preg_match( '/(\d+) deletions?\(-\)/', (string)$line, $m ) )
            $out['deletions'] = (int)$m[1];
        return $out;
    }

    // ---------------------------------------------------------------------------------------------- files

    /** The revisions of a diff: from and to, or from alone (to the working tree when $to is 'now' or ''). */
    private static function revisions( $from, $to )
    {
        if ( !self::isCommit( $from ) && $from !== 'HEAD' )
            throw new InvalidArgumentException( 'not a commit: from' );
        if ( $to === '' || $to === null || $to === 'now' )
            return array( $from );
        if ( !self::isCommit( $to ) && $to !== 'HEAD' )
            throw new InvalidArgumentException( 'not a commit: to' );
        return array( $from, $to );
    }

    /** `git diff --numstat -z` (no renames: a moved file is one removed and one added). */
    public static function numstatArguments( $from, $to = 'now', $path = '' )
    {
        return array_merge( array( 'diff', '--no-color', '--no-ext-diff', '--no-renames', '--numstat', '-z', '--end-of-options' ),
                            self::revisions( $from, $to ), array( '--' ), self::pathArgument( $path ) );
    }

    /** `git diff --name-status -z`, for the status letters. */
    public static function nameStatusArguments( $from, $to = 'now', $path = '' )
    {
        return array_merge( array( 'diff', '--no-color', '--no-ext-diff', '--no-renames', '--name-status', '-z', '--end-of-options' ),
                            self::revisions( $from, $to ), array( '--' ), self::pathArgument( $path ) );
    }

    /** The patch of one file, 3 lines of context. */
    public static function patchArguments( $from, $to, $path )
    {
        if ( !self::isPath( $path ) )
            throw new InvalidArgumentException( 'not a path' );
        return array_merge( array( 'diff', '--no-color', '--no-ext-diff', '--no-renames', '--unified=3', '--end-of-options' ),
                            self::revisions( $from, $to ), array( '--', ':(literal)' . $path ) );
    }

    private static function pathArgument( $path )
    {
        if ( $path === '' || $path === null )
            return array();
        if ( !self::isPath( $path ) )
            throw new InvalidArgumentException( 'not a path' );
        return array( ':(literal)' . rtrim( $path, '/' ) );
    }

    /** "12\t3\tpath\0" records: path => array( added, removed, binary ). A binary file is "-\t-\t". */
    public static function parseNumstatZ( $output )
    {
        $files = array();
        foreach ( explode( "\0", (string)$output ) as $record )
        {
            if ( !preg_match( '/^(-|\d+)\t(-|\d+)\t(.+)$/sD', ltrim( $record, "\n" ), $m ) )
                continue;
            $binary = $m[1] === '-' && $m[2] === '-';
            $files[$m[3]] = array( 'added' => $binary ? 0 : (int)$m[1], 'removed' => $binary ? 0 : (int)$m[2], 'binary' => $binary );
        }
        return $files;
    }

    /** "M\0path\0A\0path\0" as path => status letter. */
    public static function parseNameStatusZ( $output )
    {
        $parts = explode( "\0", (string)$output );
        $files = array();
        for ( $i = 0; $i + 1 < count( $parts ); $i += 2 )
        {
            $status = ltrim( $parts[$i], "\n" );
            if ( !preg_match( '/^[ACDMTUXB]\d*$/D', $status ) || $parts[$i + 1] === '' )
                continue;
            $files[$parts[$i + 1]] = $status[0];
        }
        return $files;
    }

    /**
     * The file list of a diff: path, status, added, removed, binary, text (shown as a text diff), secret (a file that
     * usually holds secrets, GitManagerSecretMask::isSecretPath()). Sorted by path.
     */
    public static function files( $numstat, $nameStatus )
    {
        $stats = self::parseNumstatZ( $numstat );
        $out = array();
        foreach ( self::parseNameStatusZ( $nameStatus ) + array_fill_keys( array_keys( $stats ), 'M' ) as $path => $status )
        {
            $s = isset( $stats[$path] ) ? $stats[$path] : array( 'added' => 0, 'removed' => 0, 'binary' => false );
            $out[] = array( 'path' => (string)$path, 'status' => $status, 'added' => $s['added'], 'removed' => $s['removed'],
                            'binary' => $s['binary'], 'text' => !$s['binary'] && self::isTextPath( $path ),
                            'secret' => class_exists( 'GitManagerSecretMask' ) && GitManagerSecretMask::isSecretPath( $path ) );
        }
        usort( $out, function ( $a, $b ) { return strcmp( $a['path'], $b['path'] ); } );
        return $out;
    }

    /**
     * A unified diff of one file as lines: kind (hunk, add, del, ctx, meta) and text without its marker; at most
     * $maxLines lines (truncated tells). The text is masked when $mask (GitManagerSecretMask).
     */
    public static function parsePatch( $output, $maxLines = 2000, $mask = true )
    {
        $lines = array();
        $truncated = false;
        $inHunk = false;
        $masker = $mask ? GitManagerSecretMask::maskLines( preg_split( '/\r?\n/', rtrim( (string)$output, "\n" ) ), true ) : preg_split( '/\r?\n/', rtrim( (string)$output, "\n" ) );
        foreach ( $masker as $line )
        {
            if ( count( $lines ) >= $maxLines )
            {
                $truncated = true;
                break;
            }
            if ( strncmp( $line, '@@', 2 ) === 0 )
            {
                $inHunk = true;
                $lines[] = array( 'kind' => 'hunk', 'text' => $line );
            }
            else if ( !$inHunk )
                continue; // diff --git, index, ---, +++: the file list says it
            else if ( $line !== '' && $line[0] === '+' )
                $lines[] = array( 'kind' => 'add', 'text' => (string)substr( $line, 1 ) );
            else if ( $line !== '' && $line[0] === '-' )
                $lines[] = array( 'kind' => 'del', 'text' => (string)substr( $line, 1 ) );
            else if ( $line !== '' && $line[0] === '\\' )
                $lines[] = array( 'kind' => 'meta', 'text' => $line );
            else
                $lines[] = array( 'kind' => 'ctx', 'text' => (string)substr( $line, 1 ) );
        }
        return array( 'lines' => $lines, 'truncated' => $truncated );
    }

    // ---------------------------------------------------------------------------------------------- database repositories

    /**
     * A database repository's file list summed per table, from paths and line counts alone (one row is one line of an
     * INSERT dump): table, rows_added, rows_removed, schema (its schema file changed), status (added, removed,
     * changed). Files that name no table (TABLES, _sequences ...) count as "other". Never any content.
     */
    public static function tableSummary( array $files )
    {
        $tables = array();
        $other = 0;
        foreach ( $files as $f )
        {
            $p = (string)$f['path'];
            if ( preg_match( '#^data/([^/]+?)(?:\.sql|/part-[^/]+\.sql)$#D', $p, $m ) && $m[1][0] !== '_' )
                $kind = 'data';
            else if ( preg_match( '#^schema/([^/]+?)\.sql$#D', $p, $m ) && $m[1][0] !== '_' )
                $kind = 'schema';
            else
            {
                $other++;
                continue;
            }
            $t = $m[1];
            if ( !isset( $tables[$t] ) )
                $tables[$t] = array( 'table' => $t, 'rows_added' => 0, 'rows_removed' => 0, 'schema' => false, 'status' => 'changed' );
            if ( $kind === 'data' )
            {
                $tables[$t]['rows_added'] += (int)$f['added'];
                $tables[$t]['rows_removed'] += (int)$f['removed'];
            }
            else
                $tables[$t]['schema'] = true;
            if ( $f['status'] === 'A' && $tables[$t]['status'] !== 'removed' )
                $tables[$t]['status'] = $kind === 'schema' || $tables[$t]['status'] === 'added' ? 'added' : 'changed';
            if ( $f['status'] === 'D' )
                $tables[$t]['status'] = 'removed';
        }
        ksort( $tables );
        return array( 'tables' => array_values( $tables ), 'other_files' => $other );
    }
}
