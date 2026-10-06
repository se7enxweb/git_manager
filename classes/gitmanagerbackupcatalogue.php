<?php
/**
 * The backups (captions) in the backup folder, read from the disk only.
 *
 * A caption is a folder of the backup folder. Its name is the moment it was
 * made, Y-m-d_H-i-s in the installation's time zone (config.php sets it; the
 * names are written by date() in the same time zone). That name is the one
 * source of the caption's time: it does not change when a file in the folder
 * is added, copied or restored, as the folder's mtime does. A folder whose
 * name is not such a moment (made by hand, renamed) is dated by its mtime and
 * marked so.
 *
 * captions() lists them newest first, always: by time, then by name. Nothing
 * here needs the database, a siteaccess or an INI file, so the class is tested
 * on its own (tests/unit/BackupCatalogueTest.php).
 *
 * @package GitManager
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

class GitManagerBackupCatalogue
{
    /** The name of a caption folder: the moment it was made. */
    const NAME_PATTERN = '/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/';

    /** The format of that name, for date() and DateTime. */
    const NAME_FORMAT = 'Y-m-d_H-i-s';

    /** An archive a caption holds and the download offers. */
    const ARCHIVE_PATTERN = '/^(sql_agpl|sql|var|site)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.tar\.gz(\.gpg)?$/';

    /** Files of a caption that describe it and are not archives. */
    private static $metaFiles = array( 'description.txt', 'agpl_compatible.txt', 'index.html', '.htaccess' );

    /** @var string */
    private $root;

    /** @var DateTimeZone */
    private $timeZone;

    /**
     * @param string $root the backup folder (absolute)
     * @param DateTimeZone|null $timeZone the time zone the names are written in; default: PHP's default
     */
    public function __construct( $root, ?DateTimeZone $timeZone = null )
    {
        $this->root = rtrim( (string)$root, '/' );
        $this->timeZone = $timeZone ?: new DateTimeZone( date_default_timezone_get() );
    }

    /** @return string the backup folder */
    public function root()
    {
        return $this->root;
    }

    /** @return DateTimeZone */
    public function timeZone()
    {
        return $this->timeZone;
    }

    /** Whether $name is the name of a caption folder (and so safe to put in a path). */
    public static function isValidName( $name )
    {
        return is_string( $name ) && preg_match( self::NAME_PATTERN, $name ) === 1;
    }

    /** Whether $file is the name of an archive of a caption (and so safe to put in a path). */
    public static function isArchiveName( $file )
    {
        return is_string( $file ) && preg_match( self::ARCHIVE_PATTERN, $file ) === 1;
    }

    /** The name a caption made at $epoch gets. */
    public function nameFor( $epoch )
    {
        $dt = new DateTime( '@' . (int)$epoch );
        $dt->setTimezone( $this->timeZone );
        return $dt->format( self::NAME_FORMAT );
    }

    /**
     * The moment a caption name stands for, or null when it is not one. A name
     * that is not a real date (2026-02-30) is not one either.
     */
    public function timeOfName( $name )
    {
        if ( !self::isValidName( $name ) )
        {
            return null;
        }
        $dt = DateTime::createFromFormat( '!' . self::NAME_FORMAT, $name, $this->timeZone );
        if ( !$dt || $dt->format( self::NAME_FORMAT ) !== $name )
        {
            // Overflowed (month 13, day 30 of February) or a time that does
            // not exist in this zone (the hour skipped in spring): take what
            // PHP made of it only for the latter, which is off by that hour.
            $errors = DateTime::getLastErrors();
            if ( !$dt || ( is_array( $errors ) && $errors['warning_count'] > 0 ) )
            {
                return null;
            }
        }
        return $dt->getTimestamp();
    }

    /**
     * The path of a caption folder, or null when $name is not a caption name.
     * Never leaves the backup folder.
     */
    public function captionPath( $name )
    {
        if ( !self::isValidName( $name ) )
        {
            return null;
        }
        return $this->root . '/' . $name;
    }

    /**
     * The path of an archive of a caption, or null when either name is not
     * safe, the archive is not in that caption or it is not a readable file.
     * Symbolic links that lead out of the backup folder are refused.
     */
    public function archivePath( $name, $file )
    {
        if ( !self::isValidName( $name ) || !self::isArchiveName( $file ) )
        {
            return null;
        }
        $path = $this->root . '/' . $name . '/' . $file;
        if ( !is_file( $path ) || !is_readable( $path ) )
        {
            return null;
        }
        $real = realpath( $path );
        $realRoot = realpath( $this->root );
        if ( $real === false || $realRoot === false || strpos( $real, $realRoot . '/' ) !== 0 )
        {
            return null;
        }
        return $real;
    }

    /**
     * The kind of a file of a caption: database, agpl, var, site (archives),
     * or other. Archives may be encrypted (.gpg).
     *
     * @return array( 'type' => string, 'encrypted' => bool, 'archive' => bool )
     */
    public static function classify( $file )
    {
        $encrypted = substr( $file, -4 ) === '.gpg';
        if ( preg_match( self::ARCHIVE_PATTERN, $file, $m ) )
        {
            $types = array( 'sql_agpl' => 'agpl', 'sql' => 'database', 'var' => 'var', 'site' => 'site' );
            return array( 'type' => $types[$m[1]], 'encrypted' => $encrypted, 'archive' => true );
        }
        return array( 'type' => 'other', 'encrypted' => $encrypted, 'archive' => false );
    }

    /**
     * Every caption, newest first.
     *
     * Each is a hash:
     *   timestamp        the folder's name (kept under this key for the templates and scripts that use it)
     *   created          the moment it was made (Unix time) or null when not even the folder's mtime is known
     *   created_source   'name' (from the name), 'mtime' (the folder's mtime) or 'unknown'
     *   valid_name       whether the name is a caption name (only those can be downloaded or removed)
     *   readable         whether its folder could be read
     *   description      the text of its description.txt, '' when none
     *   agpl_compatible  whether it has an AGPL compatible dump
     *   files            its files: name, size (null when unknown), type, encrypted, archive (downloadable)
     *   total_size       the sum of the sizes known
     *
     * @return array
     */
    public function captions()
    {
        if ( !is_dir( $this->root ) )
        {
            return array();
        }
        $entries = @scandir( $this->root );
        if ( $entries === false )
        {
            return array();
        }

        $captions = array();
        foreach ( $entries as $entry )
        {
            if ( $entry === '' || $entry[0] === '.' )
            {
                continue;
            }
            $path = $this->root . '/' . $entry;
            if ( !is_dir( $path ) || is_link( $path ) )
            {
                continue;
            }
            $captions[] = $this->readCaption( $entry, $path );
        }
        return self::sortNewestFirst( $captions );
    }

    /** The newest caption, or null when there is none. */
    public function newest()
    {
        $captions = $this->captions();
        return $captions ? $captions[0] : null;
    }

    /**
     * Captions newest first: by created (unknown last), then by name, newest
     * name first, so equal times come out in one order every time.
     */
    public static function sortNewestFirst( array $captions )
    {
        usort( $captions, function ( $a, $b )
        {
            $ta = isset( $a['created'] ) ? $a['created'] : null;
            $tb = isset( $b['created'] ) ? $b['created'] : null;
            if ( $ta !== $tb )
            {
                if ( $ta === null ) return 1;
                if ( $tb === null ) return -1;
                return $tb <=> $ta;
            }
            return strcmp( (string)$b['timestamp'], (string)$a['timestamp'] );
        } );
        return $captions;
    }

    private function readCaption( $name, $path )
    {
        $created = $this->timeOfName( $name );
        $source = 'name';
        if ( $created === null )
        {
            $mtime = @filemtime( $path );
            $created = $mtime === false ? null : (int)$mtime;
            $source = $mtime === false ? 'unknown' : 'mtime';
        }

        $caption = array(
            'timestamp'       => $name,
            'created'         => $created,
            'created_source'  => $source,
            'valid_name'      => self::isValidName( $name ),
            'readable'        => true,
            'description'     => '',
            'agpl_compatible' => false,
            'files'           => array(),
            'total_size'      => 0,
        );

        $files = is_readable( $path ) ? @scandir( $path ) : false;
        if ( $files === false )
        {
            $caption['readable'] = false;
            return $caption;
        }

        $descFile = $path . '/description.txt';
        if ( is_file( $descFile ) && is_readable( $descFile ) )
        {
            $caption['description'] = trim( (string)file_get_contents( $descFile, false, null, 0, 4096 ) );
        }
        $caption['agpl_compatible'] = is_file( $path . '/agpl_compatible.txt' );

        sort( $files, SORT_STRING );
        foreach ( $files as $file )
        {
            if ( $file === '' || $file[0] === '.' || in_array( $file, self::$metaFiles, true ) )
            {
                continue;
            }
            $filePath = $path . '/' . $file;
            if ( !is_file( $filePath ) )
            {
                continue;
            }
            $size = @filesize( $filePath );
            $kind = self::classify( $file );
            $caption['files'][] = array(
                'name'      => $file,
                'size'      => $size === false ? null : $size,
                'type'      => $kind['type'],
                'encrypted' => $kind['encrypted'],
                'archive'   => $kind['archive'] && $caption['valid_name'],
            );
            if ( $size !== false )
            {
                $caption['total_size'] += $size;
            }
        }
        return $caption;
    }
}
