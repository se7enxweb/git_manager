<?php
/**
 * GitManagerBackupFreshness: the state comes from the NEWEST backup, at the
 * edges of the thresholds, with backups in the future and without a date.
 */

use PHPUnit\Framework\TestCase;

class BackupFreshnessTest extends TestCase
{
    const DAY = 86400;
    const NOW = 1791331200; // 2026-10-06 00:00:00 UTC

    private function freshness()
    {
        return new GitManagerBackupFreshness( 7 * self::DAY, 30 * self::DAY, 300 );
    }

    private function caption( $name, $agoSeconds )
    {
        return array( 'timestamp' => $name, 'created' => $agoSeconds === null ? null : self::NOW - $agoSeconds );
    }

    public function testNoBackups()
    {
        $e = $this->freshness()->evaluate( array(), self::NOW );
        $this->assertSame( 'none', $e['state'] );
        $this->assertSame( 0, $e['count'] );
        $this->assertNull( $e['newest'] );
        $this->assertNull( $e['age'] );
    }

    public function testOneFreshBackup()
    {
        $e = $this->freshness()->evaluate( array( $this->caption( 'a', 3600 ) ), self::NOW );
        $this->assertSame( 'fresh', $e['state'] );
        $this->assertSame( 'a', $e['newest']['timestamp'] );
        $this->assertSame( 3600, $e['age_seconds'] );
        $this->assertSame( array( 'value' => 1, 'unit' => 'hour', 'base_unit' => 'hour', 'seconds' => 3600 ), $e['age'] );
    }

    /**
     * The owner's report: a backup 107 days old next to one 22 days old. The
     * state and the age are the 22 days of the newest one, in whatever order.
     */
    public function testTheNewestBackupDecidesNotTheOldest()
    {
        $old = $this->caption( '2026-06-21_23-50-23', 107 * self::DAY );
        $new = $this->caption( '2026-09-13_21-34-34', 22 * self::DAY );
        foreach ( array( array( $new, $old ), array( $old, $new ) ) as $list )
        {
            $e = $this->freshness()->evaluate( $list, self::NOW );
            $this->assertSame( 'ageing', $e['state'] );
            $this->assertSame( '2026-09-13_21-34-34', $e['newest']['timestamp'] );
            $this->assertSame( 22, $e['age']['value'] );
            $this->assertSame( 'days', $e['age']['unit'] );
        }
    }

    public function testARecentBackupNextToAnOldOneIsFresh()
    {
        $e = $this->freshness()->evaluate( array( $this->caption( 'old', 400 * self::DAY ), $this->caption( 'new', 2 * self::DAY ) ), self::NOW );
        $this->assertSame( 'fresh', $e['state'] );
        $this->assertSame( 'new', $e['newest']['timestamp'] );
    }

    public function testEqualTimesTakeTheLastNameInOrder()
    {
        $e = $this->freshness()->evaluate( array( $this->caption( 'b', 10 ), $this->caption( 'c', 10 ), $this->caption( 'a', 10 ) ), self::NOW );
        $this->assertSame( 'c', $e['newest']['timestamp'] );
        $this->assertSame( 3, $e['count'] );
    }

    public static function edges()
    {
        return array(
            'just made'             => array( 0, 'fresh' ),
            'exactly 7 days'        => array( 7 * self::DAY, 'fresh' ),
            '7 days and a second'   => array( 7 * self::DAY + 1, 'ageing' ),
            'exactly 30 days'       => array( 30 * self::DAY, 'ageing' ),
            '30 days and a second'  => array( 30 * self::DAY + 1, 'stale' ),
            'a year'                => array( 365 * self::DAY, 'stale' ),
            'tolerated future'      => array( -300, 'fresh' ),
            'beyond the tolerance'  => array( -301, 'future' ),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider( 'edges' )]
    public function testThresholdEdges( $age, $state )
    {
        $this->assertSame( $state, $this->freshness()->evaluate( array( $this->caption( 'x', $age ) ), self::NOW )['state'] );
        $this->assertSame( $state, $this->freshness()->stateOfTime( self::NOW - $age, self::NOW ) );
    }

    public function testAFutureBackupDoesNotHideOldOnes()
    {
        $e = $this->freshness()->evaluate( array( $this->caption( 'future', -90 * self::DAY ), $this->caption( 'real', 45 * self::DAY ) ), self::NOW );
        $this->assertSame( 'stale', $e['state'] );
        $this->assertSame( 'real', $e['newest']['timestamp'] );
        $this->assertCount( 1, $e['future'] );
        $this->assertSame( 'future', $e['future'][0]['timestamp'] );
    }

    public function testOnlyFutureBackups()
    {
        $e = $this->freshness()->evaluate( array( $this->caption( 'f1', -2 * 3600 ), $this->caption( 'f2', -5 * 3600 ) ), self::NOW );
        $this->assertSame( 'future', $e['state'] );
        $this->assertNull( $e['newest'] );
        $this->assertNull( $e['age'] );
        $this->assertSame( 'f2', $e['future'][0]['timestamp'] );
        $this->assertSame( 5, $e['ahead']['value'] );
    }

    public function testUndatedBackupsAreCountedButNeverNewest()
    {
        $e = $this->freshness()->evaluate( array( $this->caption( 'undated', null ), $this->caption( 'dated', 40 * self::DAY ) ), self::NOW );
        $this->assertSame( 'stale', $e['state'] );
        $this->assertSame( 1, $e['undated'] );
        $this->assertSame( 2, $e['count'] );
        $e = $this->freshness()->evaluate( array( $this->caption( 'undated', null ) ), self::NOW );
        $this->assertSame( 'none', $e['state'] );
    }

    /**
     * Ages are Unix time differences: the same two moments give the same state
     * whatever the default time zone, including across a change of daylight
     * saving time.
     */
    public function testTimeZoneDoesNotChangeTheAge()
    {
        $made = gmmktime( 9, 0, 0, 10, 30, 2026 );   // before the clocks go back in Los Angeles (Nov 1)
        $now = gmmktime( 9, 0, 0, 11, 6, 2026 );     // 7 days later, after it
        $old = date_default_timezone_get();
        foreach ( array( 'UTC', 'America/Los_Angeles', 'Pacific/Kiritimati' ) as $zone )
        {
            date_default_timezone_set( $zone );
            $e = $this->freshness()->evaluate( array( array( 'timestamp' => 'x', 'created' => $made ) ), $now );
            $this->assertSame( 'fresh', $e['state'], $zone );
            $this->assertSame( 7 * self::DAY, $e['age_seconds'], $zone );
        }
        date_default_timezone_set( $old );
    }

    public static function ages()
    {
        return array(
            array( 0, 0, 'seconds' ), array( 1, 1, 'second' ), array( 59, 59, 'seconds' ),
            array( 60, 1, 'minute' ), array( 3599, 59, 'minutes' ), array( 3600, 1, 'hour' ), array( 7200, 2, 'hours' ),
            array( self::DAY - 1, 23, 'hours' ), array( self::DAY, 1, 'day' ), array( 29 * self::DAY, 29, 'days' ),
            array( 30 * self::DAY, 1, 'month' ), array( 107 * self::DAY, 3, 'months' ), array( 364 * self::DAY, 12, 'months' ),
            array( 365 * self::DAY, 1, 'year' ), array( 548 * self::DAY, 1, 'year' ), array( 730 * self::DAY, 2, 'years' ),
            array( -7200, 2, 'hours' ),
        );
    }

    /** Whole units, singular for one (before 2.0.15 "1 months", and 18 months for a year and a half). */
    #[\PHPUnit\Framework\Attributes\DataProvider( 'ages' )]
    public function testAge( $seconds, $value, $unit )
    {
        $age = GitManagerBackupFreshness::age( $seconds );
        $this->assertSame( $value, $age['value'] );
        $this->assertSame( $unit, $age['unit'] );
        $this->assertIsInt( $age['value'] );
    }

    public function testTimeAgoKeepsTheOldKeys()
    {
        $t = $this->freshness()->timeAgo( self::NOW - 22 * self::DAY, self::NOW );
        $this->assertSame( 22, $t['value'] );
        $this->assertSame( 'days', $t['unit'] );
        $this->assertSame( '22 days old', $t['display'] );
        $this->assertSame( '#f39c12', $t['color'] );
        $this->assertSame( 'ageing', $t['state'] );
        $u = $this->freshness()->timeAgo( null, self::NOW );
        $this->assertSame( 'unknown', $u['unit'] );
    }

    public function testFromIni()
    {
        $ini = new class
        {
            public $values = array( 'WarnAfterDays' => '1.5', 'StaleAfterDays' => '3', 'FutureToleranceMinutes' => '' );
            public function hasVariable( $group, $name ) { return $group === 'BackupFreshnessSettings' && array_key_exists( $name, $this->values ); }
            public function variable( $group, $name ) { return $this->values[$name]; }
        };
        $f = GitManagerBackupFreshness::fromIni( $ini );
        $this->assertSame( array( 'warn_after' => 129600, 'stale_after' => 3 * self::DAY, 'future_tolerance' => 300 ), $f->thresholds() );

        $ini->values = array( 'WarnAfterDays' => '-1', 'StaleAfterDays' => 'soon' );
        $this->assertSame( array( 'warn_after' => 7 * self::DAY, 'stale_after' => 30 * self::DAY, 'future_tolerance' => 300 ),
                           GitManagerBackupFreshness::fromIni( $ini )->thresholds() );
        $this->assertSame( 7 * self::DAY, GitManagerBackupFreshness::fromIni( null )->thresholds()['warn_after'] );
    }

    public function testStaleIsNeverBelowWarn()
    {
        $f = new GitManagerBackupFreshness( 10 * self::DAY, 2 * self::DAY );
        $this->assertSame( 10 * self::DAY, $f->thresholds()['stale_after'] );
        $this->assertSame( 'stale', $f->stateOfTime( self::NOW - 10 * self::DAY - 1, self::NOW ) );
    }
}
