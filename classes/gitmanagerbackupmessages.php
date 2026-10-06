<?php
/**
 * The texts of the backup status, each chosen from the state alone.
 *
 * GitManagerBackupFreshness says what the state is; this class says it in
 * words. Every text is a whole sentence of its own in the translation (no
 * pieces glued together), and every amount has a singular and a plural text
 * ("1 day", "2 days"), so a translation can say each properly.
 *
 * The translator is a callable ( $source, array $params ) => string; the
 * default is ezpI18n::tr() in the context extension/git_manager. Tests pass
 * their own (tests/unit/BackupMessagesTest.php). The results are plain text:
 * the template washes them.
 *
 * @package GitManager
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

class GitManagerBackupMessages
{
    /** @var callable */
    private $translate;

    public function __construct( $translate = null )
    {
        $this->translate = $translate ?: function ( $source, array $params )
        {
            if ( class_exists( 'ezpI18n' ) )
            {
                return ezpI18n::tr( 'extension/git_manager', $source, null, $params );
            }
            return strtr( $source, $params );
        };
    }

    private function tr( $source, array $params = array() )
    {
        return call_user_func( $this->translate, $source, $params );
    }

    /**
     * An age or a duration in words: "1 day", "3 months".
     *
     * @param array $age as GitManagerBackupFreshness::age() gives it
     */
    public function ageText( array $age )
    {
        $n = (int)$age['value'];
        $p = array( '%count' => $n );
        switch ( $age['base_unit'] )
        {
            case 'year':   return $n === 1 ? $this->tr( '%count year', $p )   : $this->tr( '%count years', $p );
            case 'month':  return $n === 1 ? $this->tr( '%count month', $p )  : $this->tr( '%count months', $p );
            case 'day':    return $n === 1 ? $this->tr( '%count day', $p )    : $this->tr( '%count days', $p );
            case 'hour':   return $n === 1 ? $this->tr( '%count hour', $p )   : $this->tr( '%count hours', $p );
            case 'minute': return $n === 1 ? $this->tr( '%count minute', $p ) : $this->tr( '%count minutes', $p );
            default:       return $n === 1 ? $this->tr( '%count second', $p ) : $this->tr( '%count seconds', $p );
        }
    }

    /** A number of backups in words: "1 backup", "2 backups". */
    public function countText( $count )
    {
        $count = (int)$count;
        return $count === 1 ? $this->tr( '%count backup', array( '%count' => $count ) )
                            : $this->tr( '%count backups', array( '%count' => $count ) );
    }

    /** A caption's age as the list shows it: "22 days old", "made just now", "3 hours ahead of the clock". */
    public function captionAgeText( array $timeAgo )
    {
        if ( !isset( $timeAgo['seconds'] ) || $timeAgo['seconds'] === null )
        {
            return $this->tr( 'Age unknown' );
        }
        $age = GitManagerBackupFreshness::age( $timeAgo['seconds'] );
        if ( $timeAgo['state'] === GitManagerBackupFreshness::STATE_FUTURE )
        {
            return $this->tr( '%age ahead of the clock', array( '%age' => $this->ageText( $age ) ) );
        }
        if ( $age['seconds'] < 60 || $timeAgo['seconds'] < 0 )
        {
            return $this->tr( 'Made just now' );
        }
        return $this->tr( '%age old', array( '%age' => $this->ageText( $age ) ) );
    }

    /**
     * The status block of the page.
     *
     * @param array $evaluation GitManagerBackupFreshness::evaluate()
     * @return array
     *   state     the state
     *   severity  ok | warn | bad (for the colour)
     *   title     one line
     *   text      a sentence or two
     *   notes     further sentences: backups dated ahead of the clock, backups without a time
     */
    public function status( array $evaluation )
    {
        $state = $evaluation['state'];
        $newest = $evaluation['newest'];
        $name = $newest ? (string)$newest['timestamp'] : '';
        $age = $evaluation['age'] ? $this->ageText( $evaluation['age'] ) : '';
        $warn = $this->ageText( GitManagerBackupFreshness::age( $evaluation['thresholds']['warn_after'] ) );
        $stale = $this->ageText( GitManagerBackupFreshness::age( $evaluation['thresholds']['stale_after'] ) );

        switch ( $state )
        {
            case GitManagerBackupFreshness::STATE_NONE:
                $severity = 'bad';
                $title = $this->tr( 'There is no backup of this site' );
                $text = $this->tr( 'Nothing could be restored if the site were lost. Create a full site backup now: it holds the database, the var directory and the site\'s own files.' );
                break;

            case GitManagerBackupFreshness::STATE_FUTURE:
                $severity = 'warn';
                $title = $this->tr( 'The backups are dated ahead of the server clock' );
                $text = $this->tr( 'The newest backup, %name, is dated %ahead ahead of the clock, so its age cannot be judged. Check the server\'s time and time zone, then create a new backup.',
                                   array( '%name' => (string)$evaluation['future'][0]['timestamp'], '%ahead' => $this->ageText( $evaluation['ahead'] ) ) );
                break;

            case GitManagerBackupFreshness::STATE_STALE:
                $severity = 'bad';
                $title = $this->tr( 'The newest backup is %age old', array( '%age' => $age ) );
                $text = $this->tr( 'The newest backup, %name, is older than %limit. Everything changed since then would be lost. Create a new backup now.',
                                   array( '%name' => $name, '%limit' => $stale ) );
                break;

            case GitManagerBackupFreshness::STATE_AGEING:
                $severity = 'warn';
                $title = $this->tr( 'The newest backup is %age old', array( '%age' => $age ) );
                $text = $this->tr( 'The newest backup, %name, is older than %limit. Create a new backup soon.',
                                   array( '%name' => $name, '%limit' => $warn ) );
                break;

            default:
                $severity = 'ok';
                $title = $this->tr( 'The backups are up to date' );
                $text = $evaluation['age'] && $evaluation['age']['seconds'] < 60
                    ? $this->tr( 'The newest backup, %name, was made just now.', array( '%name' => $name ) )
                    : $this->tr( 'The newest backup, %name, is %age old.', array( '%name' => $name, '%age' => $age ) );
                break;
        }

        $notes = array();
        if ( $state !== GitManagerBackupFreshness::STATE_FUTURE && !empty( $evaluation['future'] ) )
        {
            $notes[] = $this->tr( '%backups dated ahead of the server clock: not counted as the newest. Check the server\'s time and time zone.',
                                  array( '%backups' => $this->countText( count( $evaluation['future'] ) ) ) );
        }
        if ( !empty( $evaluation['undated'] ) )
        {
            $notes[] = $this->tr( '%backups without a date: not counted.',
                                  array( '%backups' => $this->countText( $evaluation['undated'] ) ) );
        }

        return array(
            'state'    => $state,
            'severity' => $severity,
            'title'    => $title,
            'text'     => $text,
            'notes'    => $notes,
        );
    }
}
