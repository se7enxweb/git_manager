<?php
/**
 * GitManagerBackupMessages: each state has its own words, the warning names
 * the NEWEST backup and its age, singular and plural are separate texts, and
 * every text goes through the translator with its placeholders.
 */

use PHPUnit\Framework\TestCase;

class BackupMessagesTest extends TestCase
{
    const DAY = 86400;
    const NOW = 1791331200;

    /** Every source text the translator was asked for. */
    private $asked = array();

    private function messages()
    {
        $this->asked = array();
        return new GitManagerBackupMessages( function ( $source, array $params )
        {
            $this->asked[] = $source;
            return strtr( $source, $params );
        } );
    }

    private function statusOf( array $captions, $warnDays = 7, $staleDays = 30 )
    {
        $f = new GitManagerBackupFreshness( $warnDays * self::DAY, $staleDays * self::DAY, 300 );
        return $this->messages()->status( $f->evaluate( $captions, self::NOW ) );
    }

    private function caption( $name, $ago )
    {
        return array( 'timestamp' => $name, 'created' => self::NOW - $ago );
    }

    public function testNone()
    {
        $s = $this->statusOf( array() );
        $this->assertSame( 'none', $s['state'] );
        $this->assertSame( 'bad', $s['severity'] );
        $this->assertSame( 'There is no backup of this site', $s['title'] );
        $this->assertSame( array(), $s['notes'] );
    }

    /** The owner's page: 107 and 22 days. The warning is about the 22 days of the newest one. */
    public function testTheWarningNamesTheNewestBackup()
    {
        $s = $this->statusOf( array( $this->caption( '2026-06-21_23-50-23', 107 * self::DAY ), $this->caption( '2026-09-13_21-34-34', 22 * self::DAY ) ) );
        $this->assertSame( 'ageing', $s['state'] );
        $this->assertSame( 'warn', $s['severity'] );
        $this->assertSame( 'The newest backup is 22 days old', $s['title'] );
        $this->assertSame( 'The newest backup, 2026-09-13_21-34-34, is older than 7 days. Create a new backup soon.', $s['text'] );
        $this->assertStringNotContainsString( '2026-06-21', $s['title'] . $s['text'] );
        $this->assertStringNotContainsString( 'month', $s['title'] . $s['text'] );
        $this->assertStringNotContainsString( 'old old', $s['title'] . $s['text'] );
    }

    public function testStale()
    {
        $s = $this->statusOf( array( $this->caption( 'n', 45 * self::DAY ) ) );
        $this->assertSame( 'bad', $s['severity'] );
        $this->assertSame( 'The newest backup is 1 month old', $s['title'] );
        $this->assertSame( 'The newest backup, n, is older than 1 month. Everything changed since then would be lost. Create a new backup now.', $s['text'] );
    }

    public function testFresh()
    {
        $s = $this->statusOf( array( $this->caption( 'n', 1 * self::DAY ) ) );
        $this->assertSame( 'ok', $s['severity'] );
        $this->assertSame( 'The backups are up to date', $s['title'] );
        $this->assertSame( 'The newest backup, n, is 1 day old.', $s['text'] );
        $s = $this->statusOf( array( $this->caption( 'n', 5 ) ) );
        $this->assertSame( 'The newest backup, n, was made just now.', $s['text'] );
    }

    public function testThresholdsAreSaidInTheirOwnUnit()
    {
        $s = $this->statusOf( array( $this->caption( 'n', 2 * self::DAY ) ), 1, 30 );
        $this->assertSame( 'The newest backup, n, is older than 1 day. Create a new backup soon.', $s['text'] );
    }

    public function testFutureOnly()
    {
        $s = $this->statusOf( array( $this->caption( 'f', -3 * 3600 ) ) );
        $this->assertSame( 'future', $s['state'] );
        $this->assertSame( 'warn', $s['severity'] );
        $this->assertSame( 'The backups are dated ahead of the server clock', $s['title'] );
        $this->assertStringContainsString( 'f, is dated 3 hours ahead of the clock', $s['text'] );
    }

    public function testNotesForFutureAndUndatedBackups()
    {
        $s = $this->statusOf( array( $this->caption( 'f', -3 * 3600 ), $this->caption( 'n', 3600 ), array( 'timestamp' => 'u', 'created' => null ), array( 'timestamp' => 'v', 'created' => null ) ) );
        $this->assertSame( 'fresh', $s['state'] );
        $this->assertSame( array(
            '1 backup dated ahead of the server clock: not counted as the newest. Check the server\'s time and time zone.',
            '2 backups without a date: not counted.',
        ), $s['notes'] );
    }

    public static function ageTexts()
    {
        return array(
            array( 1, '1 second' ), array( 2, '2 seconds' ), array( 60, '1 minute' ), array( 120, '2 minutes' ),
            array( 3600, '1 hour' ), array( 7200, '2 hours' ), array( self::DAY, '1 day' ), array( 22 * self::DAY, '22 days' ),
            array( 30 * self::DAY, '1 month' ), array( 107 * self::DAY, '3 months' ), array( 365 * self::DAY, '1 year' ), array( 800 * self::DAY, '2 years' ),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider( 'ageTexts' )]
    public function testAgeTextSingularAndPlural( $seconds, $text )
    {
        $this->assertSame( $text, $this->messages()->ageText( GitManagerBackupFreshness::age( $seconds ) ) );
    }

    public function testCaptionAgeTexts()
    {
        $f = new GitManagerBackupFreshness( 7 * self::DAY, 30 * self::DAY, 300 );
        $m = $this->messages();
        $this->assertSame( '22 days old', $m->captionAgeText( $f->timeAgo( self::NOW - 22 * self::DAY, self::NOW ) ) );
        $this->assertSame( 'Made just now', $m->captionAgeText( $f->timeAgo( self::NOW - 3, self::NOW ) ) );
        $this->assertSame( 'Made just now', $m->captionAgeText( $f->timeAgo( self::NOW + 60, self::NOW ) ) );
        $this->assertSame( '2 hours ahead of the clock', $m->captionAgeText( $f->timeAgo( self::NOW + 7200, self::NOW ) ) );
        $this->assertSame( 'Age unknown', $m->captionAgeText( $f->timeAgo( null, self::NOW ) ) );
    }

    /** Every text is one whole source string with %placeholders, so a translation can reorder it. */
    public function testEveryTextGoesThroughTheTranslator()
    {
        $m = new GitManagerBackupMessages( function ( $source, array $params )
        {
            foreach ( array_keys( $params ) as $key )
            {
                $this->assertStringContainsString( $key, $source, "placeholder $key in '$source'" );
            }
            return '[' . strtr( $source, $params ) . ']';
        } );
        $f = new GitManagerBackupFreshness( 7 * self::DAY, 30 * self::DAY, 300 );
        foreach ( array( 0, 2 * self::DAY, 10 * self::DAY, 40 * self::DAY ) as $ago )
        {
            $captions = $ago ? array( $this->caption( 'n', $ago ) ) : array();
            $s = $m->status( $f->evaluate( $captions, self::NOW ) );
            $this->assertStringStartsWith( '[', $s['title'] );
            $this->assertStringStartsWith( '[', $s['text'] );
        }
    }

    /** The texts of the PHP side are in the translation files, German included. */
    public function testTheTextsAreInTheTranslations()
    {
        $file = dirname( __DIR__, 2 ) . '/translations/ger-DE/translation.ts';
        $this->assertFileExists( $file );
        $ts = file_get_contents( $file );
        $source = file_get_contents( dirname( __DIR__, 2 ) . '/classes/gitmanagerbackupmessages.php' );
        preg_match_all( "/->tr\\( '((?:[^'\\\\]|\\\\.)*)'/", $source, $m );
        $this->assertNotEmpty( $m[1] );
        foreach ( array_unique( $m[1] ) as $text )
        {
            $text = str_replace( "\\'", "'", $text );
            $xml = htmlspecialchars( $text, ENT_NOQUOTES | ENT_XML1 );
            $this->assertStringContainsString( '<source>' . $xml . '</source>', $ts, "missing: $text" );
        }
        $this->assertStringNotContainsString( 'type="unfinished"', $ts );
    }
}
