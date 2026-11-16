<?php
/**
 * GitManagerSecretMask: every kind of secret a site's settings and code hold is masked, the name of the setting stays,
 * and ordinary lines are left alone.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

class SecretMaskTest extends PHPUnit\Framework\TestCase
{
    const M = GitManagerSecretMask::MASK;

    public static function secretLines()
    {
        return array(
            'ini password' => array( 'Password=hunter2secret', 'Password=' . self::M ),
            'ini spaced' => array( 'SMTPPassword = s3cr3t value', 'SMTPPassword = ' . self::M ),
            'ini array' => array( 'ApiKey[live]=abcdef123456', 'ApiKey[live]=' . self::M ),
            'ini key' => array( 'Key=0123456789abcdef', 'Key=' . self::M ),
            'ini commented' => array( '#Password=oldone', '#Password=' . self::M ),
            'env' => array( 'DB_PASSWORD="p@ss word"', 'DB_PASSWORD=' . self::M ),
            'export' => array( 'export GITLAB_TOKEN=abc123', 'export GITLAB_TOKEN=' . self::M ),
            'php array' => array( "    'password' => 'x\\'y',", "    'password' => '" . self::M . "'," ),
            'php array dq' => array( '"db_pass" => "abc"', '"db_pass" => "' . self::M . '"' ),
            'php define' => array( "define( 'DB_PASSWORD', 'abc' );", "define( 'DB_PASSWORD', '" . self::M . "' );" ),
            'php variable' => array( '$secret = "abc";', '$secret = "' . self::M . '";' ),
            'json' => array( '{"client_secret": "abc", "name": "x"}', '{"client_secret": "' . self::M . '", "name": "x"}' ),
            'yaml' => array( '  password: abc', '  password: ' . self::M ),
            'url' => array( 'Server=pgsql://user:pa55@localhost/db', 'Server=pgsql://user:' . self::M . '@localhost/db' ),
            'stripe' => array( 'x sk_live_' . str_repeat( 'a', 24 ) . ' y', 'x ' . self::M . ' y' ),
            'gitlab' => array( 'token glpat-' . str_repeat( 'b', 20 ), 'token ' . self::M ),
            'aws' => array( 'id AKIAABCDEFGHIJKLMNOP', 'id ' . self::M ),
        );
    }

    /** @dataProvider secretLines */
    #[PHPUnit\Framework\Attributes\DataProvider( 'secretLines' )]
    public function testSecretIsMasked( $line, $expected )
    {
        $this->assertSame( $expected, GitManagerSecretMask::maskLine( $line ) );
        $this->assertTrue( GitManagerSecretMask::hasSecret( $line ) );
    }

    public static function plainLines()
    {
        return array(
            array( 'SiteName=My site' ),
            array( 'SortKey=name' ),
            array( '{def $node = fetch( content, node, hash( node_id, 2 ) )}' ),
            array( 'echo Foo::bar();' ),
            array( 'Password=' ),
            array( '<input type="password" name="Password" />' ),
        );
    }

    /** @dataProvider plainLines */
    #[PHPUnit\Framework\Attributes\DataProvider( 'plainLines' )]
    public function testPlainLineIsKept( $line )
    {
        $this->assertSame( $line, GitManagerSecretMask::maskLine( $line ) );
    }

    public function testPrivateKeyBlockIsMaskedWhole()
    {
        $text = "a\n-----BEGIN RSA PRIVATE KEY-----\nMIIEow\nAAAA\n-----END RSA PRIVATE KEY-----\nb";
        $out = GitManagerSecretMask::maskText( $text );
        $this->assertStringNotContainsString( 'MIIEow', $out );
        $this->assertStringNotContainsString( 'AAAA', $out );
        $this->assertStringStartsWith( "a\n", $out );
        $this->assertStringEndsWith( "-----END RSA PRIVATE KEY-----\nb", $out );
    }

    public function testDiffMarkersAreKept()
    {
        $out = GitManagerSecretMask::maskLines( array( '-Password=old', '+Password=new', ' SiteName=x' ), true );
        $this->assertSame( array( '-Password=' . self::M, '+Password=' . self::M, ' SiteName=x' ), $out );
    }

    public function testSecretPaths()
    {
        foreach ( array( 'settings/override/site.ini.append.php', 'settings/siteaccess/site/site.ini.append.php', '.env', 'config.php', 'x/server.key' ) as $p )
            $this->assertTrue( GitManagerSecretMask::isSecretPath( $p ), $p );
        foreach ( array( 'design/x/templates/page.tpl', 'extension/a/settings/site.ini.append.php', 'README.md' ) as $p )
            $this->assertFalse( GitManagerSecretMask::isSecretPath( $p ), $p );
    }
}
