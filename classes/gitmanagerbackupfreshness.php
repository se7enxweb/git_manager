<?php
/**
 * How fresh the backups are: one answer, taken from the NEWEST backup.
 *
 * The question an administrator asks is "would I lose much if the site broke
 * now?". That is answered by the newest backup alone: an old backup next to a
 * recent one is no reason for a warning. (Before 2.0.15 the page took the
 * age of the oldest one, so it kept warning about a backup from months ago
 * however recent the newest was.)
 *
 * States, from the newest backup's age:
 *   none    no backup at all
 *   fresh   age <= WarnAfterDays
 *   ageing  WarnAfterDays < age <= StaleAfterDays
 *   stale   age > StaleAfterDays
 *   future  every backup is dated ahead of the clock by more than the
 *           tolerance, so no age can be judged (wrong clock or time zone)
 *
 * A backup dated in the future (beyond the tolerance) never counts as the
 * newest: a wrong clock must not hide that the real backups are old. Ages are
 * whole seconds between two Unix times, so the time zone plays no part here;
 * it matters only when a caption's name is read (GitManagerBackupCatalogue).
 *
 * No database, no INI, no clock of its own: the caller passes "now". Tested in
 * tests/unit/BackupFreshnessTest.php.
 *
 * @package GitManager
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

class GitManagerBackupFreshness
{
    const STATE_NONE = 'none';
    const STATE_FRESH = 'fresh';
    const STATE_AGEING = 'ageing';
    const STATE_STALE = 'stale';
    const STATE_FUTURE = 'future';

    const DAY = 86400;

    /** The defaults, also when the settings are missing or wrong. */
    const DEFAULT_WARN_AFTER_DAYS = 7;
    const DEFAULT_STALE_AFTER_DAYS = 30;
    const DEFAULT_FUTURE_TOLERANCE_MINUTES = 5;

    /** The colours of the ages, as the command line scripts expect them. */
    private static $colors = array(
        self::STATE_FRESH  => '#27ae60',
        self::STATE_AGEING => '#f39c12',
        self::STATE_STALE  => '#e74c3c',
        self::STATE_FUTURE => '#999999',
        self::STATE_NONE   => '#999999',
    );

    /** @var int */
    private $warnAfter;
    /** @var int */
    private $staleAfter;
    /** @var int */
    private $futureTolerance;

    /**
     * @param int $warnAfterSeconds an age above this is "ageing"
     * @param int $staleAfterSeconds an age above this is "stale" (at least $warnAfterSeconds)
     * @param int $futureToleranceSeconds how far ahead of the clock a backup may be dated and still count
     */
    public function __construct( $warnAfterSeconds, $staleAfterSeconds, $futureToleranceSeconds = 300 )
    {
        $this->warnAfter = max( 0, (int)$warnAfterSeconds );
        $this->staleAfter = max( $this->warnAfter, (int)$staleAfterSeconds );
        $this->futureTolerance = max( 0, (int)$futureToleranceSeconds );
    }

    /**
     * From [BackupFreshnessSettings] of git_manager.ini: WarnAfterDays,
     * StaleAfterDays, FutureToleranceMinutes. A missing, empty or negative
     * value takes its default.
     *
     * @param eZINI|null $ini
     */
    public static function fromIni( $ini = null )
    {
        $read = function ( $name, $default ) use ( $ini )
        {
            if ( !$ini || !$ini->hasVariable( 'BackupFreshnessSettings', $name ) )
            {
                return $default;
            }
            $value = trim( (string)$ini->variable( 'BackupFreshnessSettings', $name ) );
            return ( $value !== '' && is_numeric( $value ) && (float)$value >= 0 ) ? (float)$value : $default;
        };
        return new self(
            (int)round( $read( 'WarnAfterDays', self::DEFAULT_WARN_AFTER_DAYS ) * self::DAY ),
            (int)round( $read( 'StaleAfterDays', self::DEFAULT_STALE_AFTER_DAYS ) * self::DAY ),
            (int)round( $read( 'FutureToleranceMinutes', self::DEFAULT_FUTURE_TOLERANCE_MINUTES ) * 60 )
        );
    }

    /** @return array( 'warn_after' => seconds, 'stale_after' => seconds, 'future_tolerance' => seconds ) */
    public function thresholds()
    {
        return array(
            'warn_after'       => $this->warnAfter,
            'stale_after'      => $this->staleAfter,
            'future_tolerance' => $this->futureTolerance,
        );
    }

    /** The state of a single backup made at $created, seen at $now. */
    public function stateOfTime( $created, $now )
    {
        if ( $created === null )
        {
            return self::STATE_NONE;
        }
        $age = (int)$now - (int)$created;
        if ( $age < -$this->futureTolerance )
        {
            return self::STATE_FUTURE;
        }
        if ( $age > $this->staleAfter )
        {
            return self::STATE_STALE;
        }
        if ( $age > $this->warnAfter )
        {
            return self::STATE_AGEING;
        }
        return self::STATE_FRESH;
    }

    /**
     * The freshness of a list of captions (in any order), seen at $now.
     *
     * @param array $captions hashes with 'created' (Unix time or null) and 'timestamp' (name)
     * @param int $now
     * @return array
     *   state          one of the STATE_* constants
     *   count          how many captions there are
     *   newest         the caption the state is taken from (null for none and future)
     *   age_seconds    its age (null when there is none)
     *   age            its age as value + unit (see age())
     *   future         the captions dated ahead of the clock beyond the tolerance, newest first
     *   undated        how many captions have no time at all
     *   thresholds     see thresholds()
     */
    public function evaluate( array $captions, $now )
    {
        $now = (int)$now;
        $sorted = GitManagerBackupCatalogue::sortNewestFirst( $captions );
        $newest = null;
        $future = array();
        $undated = 0;
        foreach ( $sorted as $caption )
        {
            $created = isset( $caption['created'] ) ? $caption['created'] : null;
            if ( $created === null )
            {
                $undated++;
                continue;
            }
            if ( $this->stateOfTime( $created, $now ) === self::STATE_FUTURE )
            {
                $future[] = $caption;
                continue;
            }
            if ( $newest === null )
            {
                $newest = $caption;
            }
        }

        if ( $newest !== null )
        {
            $state = $this->stateOfTime( $newest['created'], $now );
            $ageSeconds = max( 0, $now - (int)$newest['created'] );
        }
        elseif ( $future )
        {
            $state = self::STATE_FUTURE;
            $ageSeconds = null;
        }
        else
        {
            $state = self::STATE_NONE;
            $ageSeconds = null;
        }

        return array(
            'state'       => $state,
            'count'       => count( $captions ),
            'newest'      => $newest,
            'age_seconds' => $ageSeconds,
            'age'         => $ageSeconds === null ? null : self::age( $ageSeconds ),
            'ahead'       => $future ? self::age( (int)$future[0]['created'] - $now ) : null,
            'future'      => $future,
            'undated'     => $undated,
            'thresholds'  => $this->thresholds(),
        );
    }

    /**
     * A duration in the largest whole unit that fits: years (365 days),
     * months (30 days), days, hours, minutes, seconds. Negative durations are
     * taken as their size.
     *
     * @return array( 'value' => int, 'unit' => 'year'|'years'|'month'|..., 'base_unit' => 'year'|'month'|..., 'seconds' => int )
     */
    public static function age( $seconds )
    {
        $seconds = abs( (int)$seconds );
        $days = intdiv( $seconds, self::DAY );
        if ( $days >= 365 )
        {
            $value = intdiv( $days, 365 );
            $base = 'year';
        }
        elseif ( $days >= 30 )
        {
            $value = intdiv( $days, 30 );
            $base = 'month';
        }
        elseif ( $days >= 1 )
        {
            $value = $days;
            $base = 'day';
        }
        elseif ( $seconds >= 3600 )
        {
            $value = intdiv( $seconds, 3600 );
            $base = 'hour';
        }
        elseif ( $seconds >= 60 )
        {
            $value = intdiv( $seconds, 60 );
            $base = 'minute';
        }
        else
        {
            $value = $seconds;
            $base = 'second';
        }
        return array(
            'value'     => $value,
            'unit'      => $value === 1 ? $base : $base . 's',
            'base_unit' => $base,
            'seconds'   => $seconds,
        );
    }

    /**
     * The "time_ago" hash of a caption as the page and the scripts had it
     * before 2.0.15 (value, unit, display, color), plus its state. display is
     * English; the page shows GitManagerBackupMessages::ageText() instead.
     */
    public function timeAgo( $created, $now )
    {
        if ( $created === null )
        {
            return array( 'value' => 0, 'unit' => 'unknown', 'display' => 'unknown age',
                          'color' => self::$colors[self::STATE_NONE], 'state' => self::STATE_NONE, 'seconds' => null );
        }
        $state = $this->stateOfTime( $created, $now );
        $age = self::age( (int)$now - (int)$created );
        $display = $age['value'] . ' ' . $age['unit'] . ( $state === self::STATE_FUTURE ? ' ahead' : ' old' );
        return array(
            'value'   => $age['value'],
            'unit'    => $age['unit'],
            'display' => $display,
            'color'   => self::$colors[$state],
            'state'   => $state,
            'seconds' => (int)$now - (int)$created,
        );
    }

    /** The colour of a state, for the command line scripts. */
    public static function color( $state )
    {
        return isset( self::$colors[$state] ) ? self::$colors[$state] : self::$colors[self::STATE_NONE];
    }
}
