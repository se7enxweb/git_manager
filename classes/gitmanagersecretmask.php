<?php
/**
 * Masks secrets in text that is about to be shown: the lines of a diff, a settings file, a log line. A value that is
 * a password, a secret, a token, a private key or a known API key format is replaced with MASK; the name of the
 * setting stays, so a reader still sees WHAT changed ("Password=********" was changed), never the value.
 *
 * Covered: INI settings (settings/override/*.ini.append.php ...), PHP arrays, define() and variables, .env and shell
 * assignments, JSON and YAML keys, user:password@ in URLs, PEM private key blocks, and the key formats the AGPL dump
 * sanitizer knows (Stripe, SendGrid, AWS) plus GitLab and GitHub tokens. A whole line is masked when it is inside a
 * private key block.
 *
 * Plain PHP with no Exponential calls (the unit tests and the hosting tools use it without a bootstrap).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package GitManager
 */

class GitManagerSecretMask
{
    const MASK = '********';

    /** The end of a setting name that marks its value as a secret (case is ignored). */
    const NAME_PATTERN = '(?:[A-Za-z0-9_.\-\[\]]*(?:password|passwd|passphrase|pass|pwd|secret|token|apikey|api_key|api-key|privatekey|private_key|secretkey|secret_key|signingkey|signing_key|accesskey|access_key|salt|credentials|dsn|auth|_key|-key)|key)(?:\[[^\]]*\])?';

    /** Known key formats, anywhere in a line. */
    const TOKEN_PATTERNS = array(
        '/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]{16,99}\b/',
        '/\bwhsec_[A-Za-z0-9]{16,99}\b/',
        '/\bSG\.[A-Za-z0-9_\-]{16,40}\.[A-Za-z0-9_\-]{16,60}\b/',
        '/\bAKIA[A-Z0-9]{16}\b/',
        '/\bglpat-[A-Za-z0-9_\-]{20,}\b/',
        '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
        '/\bgithub_pat_[A-Za-z0-9_]{30,}\b/',
        '/\bxox[abposr]-[A-Za-z0-9\-]{10,}\b/',
    );

    /** @var bool inside a "-----BEGIN ... PRIVATE KEY-----" block (maskText() and maskLines() keep it per text) */
    private $inKey = false;

    /** Whether a path is a file that usually holds secrets (the settings overrides, .env, config.php, keys). */
    public static function isSecretPath( $path )
    {
        $p = strtolower( str_replace( '\\', '/', (string)$path ) );
        return preg_match( '#(^|/)settings/(override|siteaccess/[^/]+)/[^/]+\.ini(\.append)?(\.php)?$#', $p ) === 1
            || preg_match( '#(^|/)(\.env(\..*)?|config\.php|config\.cluster\.php|auth\.json|\.npmrc|\.pgpass|\.my\.cnf|wp-config\.php|parameters\.ya?ml)$#', $p ) === 1
            || preg_match( '#\.(pem|key|p12|pfx|jks|kdbx)$#', $p ) === 1
            || preg_match( '#(^|/)(id_rsa|id_ed25519|id_ecdsa)(\.pub)?$#', $p ) === 1;
    }

    /** One line, masked (stateless: a private key block is only seen by maskLines()/maskText()). */
    public static function maskLine( $line )
    {
        $m = new self();
        return $m->line( (string)$line );
    }

    /** A whole text, masked line by line. */
    public static function maskText( $text )
    {
        $m = new self();
        $lines = preg_split( '/(\r\n|\n|\r)/', (string)$text, -1, PREG_SPLIT_DELIM_CAPTURE );
        $out = '';
        foreach ( $lines as $i => $part )
            $out .= $i % 2 ? $part : $m->line( $part );
        return $out;
    }

    /**
     * Lines as a diff has them, each with its marker (' ', '+', '-'): the marker kept, the rest masked; the key
     * block state is carried from line to line.
     */
    public static function maskLines( array $lines, $markers = false )
    {
        $m = new self();
        $out = array();
        foreach ( $lines as $line )
        {
            $line = (string)$line;
            if ( $markers && $line !== '' && strpos( ' +-', $line[0] ) !== false )
                $out[] = $line[0] . $m->line( substr( $line, 1 ) );
            else
                $out[] = $m->line( $line );
        }
        return $out;
    }

    /** Whether a line holds anything this class masks (for counting). */
    public static function hasSecret( $line )
    {
        return self::maskLine( $line ) !== (string)$line;
    }

    private function line( $line )
    {
        if ( preg_match( '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY( BLOCK)?-----/', $line ) )
        {
            $this->inKey = strpos( $line, '-----END' ) === false;
            return preg_replace( '/-----BEGIN ([A-Z0-9 ]*PRIVATE KEY( BLOCK)?)-----.*$/', '-----BEGIN $1----- ' . self::MASK, $line );
        }
        if ( $this->inKey )
        {
            if ( preg_match( '/-----END [A-Z0-9 ]*PRIVATE KEY( BLOCK)?-----/', $line ) )
            {
                $this->inKey = false;
                return $line;
            }
            return self::MASK;
        }
        $n = self::NAME_PATTERN;
        // user:password@ in a URL (database DSNs, git remotes, SMTP)
        $line = preg_replace( '#\b([a-z][a-z0-9+.\-]*://[^\s:/@\'"]*):([^\s@/\'"]+)@#i', '$1:' . self::MASK . '@', $line );
        // PHP: 'password' => 'x', "db_pass" => "x"
        $line = preg_replace( '/([\'"]' . $n . '[\'"]\s*=>\s*)([\'"])((?:\\\\.|(?!\2).)*)\2/i', '$1$2' . self::MASK . '$2', $line );
        // PHP: define( 'DB_PASSWORD', 'x' )
        $line = preg_replace( '/(define\s*\(\s*[\'"]' . $n . '[\'"]\s*,\s*)([\'"])((?:\\\\.|(?!\2).)*)\2/i', '$1$2' . self::MASK . '$2', $line );
        // PHP: $password = 'x';
        $line = preg_replace( '/(\$' . $n . '\s*=\s*)([\'"])((?:\\\\.|(?!\2).)*)\2/i', '$1$2' . self::MASK . '$2', $line );
        // JSON: "password": "x"
        $line = preg_replace( '/("' . $n . '"\s*:\s*)"((?:\\\\.|[^"\\\\])*)"/i', '$1"' . self::MASK . '"', $line );
        // INI, .env, shell, YAML: Password=x, DB_PASSWORD="x", export API_TOKEN=x, password: x (not PHP code lines)
        if ( !preg_match( '/^\s*(\$|define\s*\(|[\'"][^\'"]*[\'"]\s*=>)/', $line ) )
            $line = preg_replace( '/^(\s*(?:export\s+|#\s*|;\s*)?' . $n . '\s*(?:=|:(?!:))\s*)(?!' . preg_quote( self::MASK, '/' ) . ')(\S.*?)\s*$/i', '$1' . self::MASK, $line );
        foreach ( self::TOKEN_PATTERNS as $p )
            $line = preg_replace( $p, self::MASK, $line );
        return $line;
    }
}
