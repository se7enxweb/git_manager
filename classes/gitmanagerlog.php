<?php
/**
 * The commit log of git_manager/dashboard, one page at a time: the view parameters that say which page and which
 * commits, the paging maths, and the argument lists for git (never a shell command line).
 *
 * The whole history is never read: a page is `git log --skip=N --max-count=M`, the total is `git rev-list --count`
 * with the same filters. Every value from a request is checked here and handed to git as one argument of its own
 * (GitManager::git() runs git without a shell), so nothing a user types is ever read as a command or an option.
 *
 * Plain PHP with no Exponential calls, so the unit tests run it without a database.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package GitManager
 */

class GitManagerLog
{
    /** The page sizes the size selector offers; any other (limit) falls back to the default. */
    const LIMITS = array( 10, 25, 50, 100 );

    /** The page size before anyone chose one (the log showed 50 commits before it had pages). */
    const DEFAULT_LIMIT = 50;

    /** The longest author or message text a filter takes, in characters. */
    const MAX_TEXT = 200;

    /** The view parameters of the filters, in the order the links carry them. */
    const FILTER_KEYS = array( 'ref', 'author', 'grep', 'since', 'until' );

    /** The separator of the fields in a log line (the ASCII unit separator, which no commit title holds). */
    const FIELD_SEPARATOR = "\x1f";

    /**
     * A view parameter value as a link carries it: letters, digits, dot, dash, underscore and @ as they are, every
     * other byte as ~XX (hex). No "/" (it would end the parameter), no "%" or "+" (decoding them depends on the
     * installation's URI settings), so a link reads back the same on every installation.
     */
    public static function encodeParameter( $value )
    {
        return preg_replace_callback( '/[^A-Za-z0-9._@-]/', function ( $m ) {
            return '~' . strtoupper( bin2hex( $m[0] ) );
        }, (string)$value );
    }

    /** The value encodeParameter() made into a link, back as it was. */
    public static function decodeParameter( $value )
    {
        return preg_replace_callback( '/~([0-9A-Fa-f]{2})/', function ( $m ) {
            return chr( hexdec( $m[1] ) );
        }, (string)$value );
    }

    /**
     * Text for --author or --grep: control characters dropped, white space trimmed, at most MAX_TEXT characters.
     * Invalid UTF-8 is dropped as a whole ('').
     */
    public static function cleanText( $value )
    {
        if ( !is_string( $value ) && !is_int( $value ) )
        {
            return '';
        }
        $value = (string)$value;
        if ( preg_match( '//u', $value ) !== 1 )
        {
            return '';
        }
        $value = trim( preg_replace( '/[\x00-\x1f\x7f]+/', ' ', $value ) );
        if ( function_exists( 'mb_substr' ) )
        {
            return mb_substr( $value, 0, self::MAX_TEXT, 'UTF-8' );
        }
        return preg_replace( '/^(.{0,' . self::MAX_TEXT . '}).*$/us', '$1', $value );
    }

    /** A day as the date fields send it: YYYY-MM-DD, a real date. */
    public static function isDate( $value )
    {
        return is_string( $value ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) === 1
            && checkdate( (int)$m[2], (int)$m[3], (int)$m[1] );
    }

    /**
     * A branch or tag name as a filter may name it ("main", "origin/main", "v2.0.20", "HEAD"): what git allows in a
     * ref name and nothing a shell or git could read as an option. The name must still be one of the repository's
     * refs (resolveRef()) before it reaches git.
     */
    public static function isRefName( $ref )
    {
        return is_string( $ref ) && strlen( $ref ) <= 250
            && preg_match( '#^[A-Za-z0-9_][A-Za-z0-9._/+@-]*$#', $ref ) === 1
            && strpos( $ref, '..' ) === false && strpos( $ref, '//' ) === false && strpos( $ref, '@{' ) === false
            && substr( $ref, -1 ) !== '/' && substr( $ref, -1 ) !== '.' && substr( $ref, -5 ) !== '.lock';
    }

    /** A page size of LIMITS, else $fallback (when that is one), else DEFAULT_LIMIT. */
    public static function normalizeLimit( $value, $fallback = null )
    {
        if ( is_numeric( $value ) && in_array( (int)$value, self::LIMITS, true ) && (string)(int)$value === trim( (string)$value ) )
        {
            return (int)$value;
        }
        if ( $fallback !== null && $fallback !== $value )
        {
            return self::normalizeLimit( $fallback, null );
        }
        return self::DEFAULT_LIMIT;
    }

    /**
     * The log's state from the view's user parameters (/(offset)/50/(limit)/25/(author)/jane ...):
     * array( 'offset' => int >= 0, 'limit' => one of LIMITS, 'limit_given' => bool (a valid (limit) was in the
     * address), 'ref', 'author', 'grep', 'since', 'until' => string, '' when not filtered ). Anything invalid is
     * left out, never passed on. $preferredLimit is the user's stored page size (used when there is no (limit)).
     */
    public static function parseViewParameters( $parameters, $preferredLimit = null )
    {
        $parameters = is_array( $parameters ) ? $parameters : array();
        $get = function ( $key ) use ( $parameters ) {
            return isset( $parameters[$key] ) && is_scalar( $parameters[$key] ) ? self::decodeParameter( (string)$parameters[$key] ) : '';
        };

        $limitGiven = isset( $parameters['limit'] ) && is_scalar( $parameters['limit'] )
            && in_array( (int)$parameters['limit'], self::LIMITS, true ) && (string)(int)$parameters['limit'] === (string)$parameters['limit'];
        $limit = $limitGiven ? (int)$parameters['limit'] : self::normalizeLimit( $preferredLimit );

        $offset = $get( 'offset' );
        $offset = ctype_digit( $offset ) && strlen( $offset ) <= 9 ? (int)$offset : 0;

        $ref = $get( 'ref' );
        $since = $get( 'since' );
        $until = $get( 'until' );
        return array(
            'offset'      => $offset,
            'limit'       => $limit,
            'limit_given' => $limitGiven,
            'ref'         => self::isRefName( $ref ) ? $ref : '',
            'author'      => self::cleanText( $get( 'author' ) ),
            'grep'        => self::cleanText( $get( 'grep' ) ),
            'since'       => self::isDate( $since ) ? $since : '',
            'until'       => self::isDate( $until ) ? $until : '',
        );
    }

    /**
     * The filter form's fields (filter[ref], filter[author], filter[grep], filter[start_date], filter[end_date]) as
     * a state for the first page; $limit is kept.
     */
    public static function stateFromForm( $fields, $limit )
    {
        $fields = is_array( $fields ) ? $fields : array();
        $field = function ( $key ) use ( $fields ) {
            return isset( $fields[$key] ) && is_scalar( $fields[$key] ) ? trim( (string)$fields[$key] ) : '';
        };
        return array(
            'offset'      => 0,
            'limit'       => self::normalizeLimit( $limit ),
            'limit_given' => in_array( (int)$limit, self::LIMITS, true ),
            'ref'         => self::isRefName( $field( 'ref' ) ) ? $field( 'ref' ) : '',
            'author'      => self::cleanText( $field( 'author' ) ),
            'grep'        => self::cleanText( $field( 'grep' ) ),
            'since'       => self::isDate( $field( 'start_date' ) ) ? $field( 'start_date' ) : '',
            'until'       => self::isDate( $field( 'end_date' ) ) ? $field( 'end_date' ) : '',
        );
    }

    /** Whether the state filters the log at all (a branch, author, text or dates). */
    public static function isFiltered( array $state )
    {
        foreach ( self::FILTER_KEYS as $key )
        {
            if ( isset( $state[$key] ) && $state[$key] !== '' )
            {
                return true;
            }
        }
        return false;
    }

    /**
     * The view parameters of a state as a link path ("/(offset)/50/(limit)/25/(author)/jane"), with $changes
     * applied first. An offset of 0 is left out; the limit is always written, so the link shows the same page size
     * to whoever opens it.
     */
    public static function viewParameterPath( array $state, array $changes = array() )
    {
        $state = array_merge( $state, $changes );
        $path = '';
        if ( !empty( $state['offset'] ) && (int)$state['offset'] > 0 )
        {
            $path .= '/(offset)/' . (int)$state['offset'];
        }
        $path .= '/(limit)/' . self::normalizeLimit( isset( $state['limit'] ) ? $state['limit'] : null );
        foreach ( self::FILTER_KEYS as $key )
        {
            if ( isset( $state[$key] ) && $state[$key] !== '' )
            {
                $path .= '/(' . $key . ')/' . self::encodeParameter( $state[$key] );
            }
        }
        return $path;
    }

    /**
     * The view parameters of a state for the shared pager (design:navigator/google.tpl): key => value as the link
     * carries it, the offset included; empty filters left out.
     */
    public static function navigatorParameters( array $state )
    {
        $parameters = array( 'offset' => (int)$state['offset'], 'limit' => self::normalizeLimit( $state['limit'] ) );
        foreach ( self::FILTER_KEYS as $key )
        {
            if ( isset( $state[$key] ) && $state[$key] !== '' )
            {
                $parameters[$key] = self::encodeParameter( $state[$key] );
            }
        }
        return $parameters;
    }

    /**
     * The paging maths for $total commits shown $limit at a time from $offset. The offset is moved to the start of
     * its page and, past the end, to the last page. Returns array( 'offset', 'limit', 'total', 'page' (1-based),
     * 'page_count' (at least 1), 'from', 'to' (1-based, 0 and 0 when there is nothing), 'first_offset',
     * 'previous_offset', 'next_offset', 'last_offset' (false where there is no such page), 'is_first', 'is_last' ).
     */
    public static function paging( $total, $offset, $limit )
    {
        $total = max( 0, (int)$total );
        $limit = self::normalizeLimit( $limit );
        $offset = max( 0, (int)$offset );
        $pageCount = max( 1, (int)ceil( $total / $limit ) );
        $page = min( $pageCount, intdiv( $offset, $limit ) + 1 );
        $offset = ( $page - 1 ) * $limit;
        $lastOffset = ( $pageCount - 1 ) * $limit;
        return array(
            'offset'          => $offset,
            'limit'           => $limit,
            'total'           => $total,
            'page'            => $page,
            'page_count'      => $pageCount,
            'from'            => $total > 0 ? $offset + 1 : 0,
            'to'              => min( $total, $offset + $limit ),
            'first_offset'    => $page > 1 ? 0 : false,
            'previous_offset' => $page > 1 ? $offset - $limit : false,
            'next_offset'     => $page < $pageCount ? $offset + $limit : false,
            'last_offset'     => $page < $pageCount ? $lastOffset : false,
            'is_first'        => $page === 1,
            'is_last'         => $page === $pageCount,
        );
    }

    /**
     * Picks the full ref name for a branch or tag as the filter names it, from the repository's refs ($refs: full
     * names as `git for-each-ref --format=%(refname)` lists them). '' means HEAD. A name the repository does not
     * have gives null; it never reaches git.
     */
    public static function resolveRef( $ref, array $refs )
    {
        if ( $ref === '' || $ref === 'HEAD' )
        {
            return 'HEAD';
        }
        if ( !self::isRefName( $ref ) )
        {
            return null;
        }
        $known = array_flip( $refs );
        foreach ( array( 'refs/heads/', 'refs/remotes/', 'refs/tags/' ) as $prefix )
        {
            if ( isset( $known[$prefix . $ref] ) )
            {
                return $prefix . $ref;
            }
        }
        return null;
    }

    /**
     * The filter options of a state, each value one argument: --author and --grep match as fixed text, ignoring
     * case; --since and --until take the checked dates.
     */
    public static function filterArguments( array $state )
    {
        $arguments = array();
        $author = isset( $state['author'] ) ? self::cleanText( $state['author'] ) : '';
        $grep = isset( $state['grep'] ) ? self::cleanText( $state['grep'] ) : '';
        if ( $author !== '' || $grep !== '' )
        {
            $arguments[] = '--regexp-ignore-case';
            $arguments[] = '--fixed-strings';
        }
        if ( $author !== '' )
        {
            $arguments[] = '--author=' . $author;
        }
        if ( $grep !== '' )
        {
            $arguments[] = '--grep=' . $grep;
        }
        if ( isset( $state['since'] ) && self::isDate( $state['since'] ) )
        {
            $arguments[] = '--since=' . $state['since'] . ' 00:00:00';
        }
        if ( isset( $state['until'] ) && self::isDate( $state['until'] ) )
        {
            $arguments[] = '--until=' . $state['until'] . ' 23:59:59';
        }
        return $arguments;
    }

    /**
     * The arguments of `git log` for one page: hash, author, date and title of $limit commits after skipping
     * $offset, from $fullRef (a full ref name resolveRef() gave, or HEAD). The revision stands after
     * --end-of-options and before --, so it is read as a revision and nothing else.
     */
    public static function logArguments( array $state, $fullRef, $offset, $limit )
    {
        return array_merge(
            array( 'log', '--no-color', '--skip=' . max( 0, (int)$offset ), '--max-count=' . self::normalizeLimit( $limit ),
                   '--format=%H%x1f%an%x1f%cD%x1f%s' ),
            self::filterArguments( $state ),
            array( '--end-of-options', self::checkedRef( $fullRef ), '--' )
        );
    }

    /** The arguments of `git rev-list --count` for the same commits as logArguments(), all pages together. */
    public static function countArguments( array $state, $fullRef )
    {
        return array_merge(
            array( 'rev-list', '--count' ),
            self::filterArguments( $state ),
            array( '--end-of-options', self::checkedRef( $fullRef ), '--' )
        );
    }

    /** HEAD or a full ref name (refs/...), else HEAD: a revision argument is never anything else. */
    private static function checkedRef( $fullRef )
    {
        return is_string( $fullRef ) && strpos( $fullRef, 'refs/' ) === 0 && self::isRefName( substr( $fullRef, 5 ) ) ? $fullRef : 'HEAD';
    }

    /** The commits of a `git log` output made with logArguments(): array( hash, author, date, title ) each. */
    public static function parseLog( $output )
    {
        $commits = array();
        foreach ( explode( "\n", (string)$output ) as $line )
        {
            $fields = explode( self::FIELD_SEPARATOR, rtrim( $line, "\r" ) );
            if ( count( $fields ) !== 4 || preg_match( '/^[0-9a-f]{40}([0-9a-f]{24})?$/', $fields[0] ) !== 1 )
            {
                continue;
            }
            $commits[] = array( 'hash' => $fields[0], 'author' => $fields[1], 'date' => $fields[2], 'title' => $fields[3] );
        }
        return $commits;
    }
}
