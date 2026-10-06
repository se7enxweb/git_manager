<?php
/**
 * Throwaway backup folders for the tests: made under tests/.tmp of this
 * checkout (or GIT_MANAGER_TEST_TMP), never in a real backup folder, and
 * removed again by remove().
 */

class GitManagerBackupFixture
{
    /** A new, empty backup folder. */
    public static function root()
    {
        $base = getenv( 'GIT_MANAGER_TEST_TMP' );
        if ( !is_string( $base ) || $base === '' )
        {
            $base = dirname( __DIR__ ) . '/.tmp';
        }
        $root = rtrim( $base, '/' ) . '/backups-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );
        if ( !mkdir( $root, 0700, true ) )
        {
            throw new RuntimeException( "Cannot create $root" );
        }
        return $root;
    }

    /** A caption folder with files: array( name => content ). */
    public static function caption( $root, $name, array $files = array(), $mtime = null )
    {
        $dir = $root . '/' . $name;
        mkdir( $dir, 0700, true );
        foreach ( $files as $file => $content )
        {
            file_put_contents( $dir . '/' . $file, $content );
        }
        if ( $mtime !== null )
        {
            touch( $dir, $mtime );
        }
        return $dir;
    }

    /** Removes a folder made by root(), with everything in it. */
    public static function remove( $path )
    {
        if ( !is_string( $path ) || $path === '' || strpos( $path, '/backups-' ) === false || !file_exists( $path ) )
        {
            return;
        }
        if ( is_dir( $path ) && !is_link( $path ) )
        {
            @chmod( $path, 0700 );
            foreach ( scandir( $path ) as $entry )
            {
                if ( $entry !== '.' && $entry !== '..' )
                {
                    self::removeEntry( $path . '/' . $entry );
                }
            }
            rmdir( $path );
        }
    }

    private static function removeEntry( $path )
    {
        if ( is_dir( $path ) && !is_link( $path ) )
        {
            @chmod( $path, 0700 );
            foreach ( scandir( $path ) as $entry )
            {
                if ( $entry !== '.' && $entry !== '..' )
                {
                    self::removeEntry( $path . '/' . $entry );
                }
            }
            rmdir( $path );
            return;
        }
        unlink( $path );
    }
}
