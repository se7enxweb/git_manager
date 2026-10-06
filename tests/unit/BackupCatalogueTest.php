<?php
/**
 * GitManagerBackupCatalogue: which backups there are, their times, their
 * order (newest first) and the paths it hands out.
 */

use PHPUnit\Framework\TestCase;

class BackupCatalogueTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        $this->root = GitManagerBackupFixture::root();
    }

    protected function tearDown(): void
    {
        GitManagerBackupFixture::remove( $this->root );
    }

    private function catalogue( $zone = 'UTC' )
    {
        return new GitManagerBackupCatalogue( $this->root, new DateTimeZone( $zone ) );
    }

    private function names( array $captions )
    {
        return array_map( function ( $c ) { return $c['timestamp']; }, $captions );
    }

    public function testNoFolderIsNoBackups()
    {
        $catalogue = new GitManagerBackupCatalogue( $this->root . '/missing' );
        $this->assertSame( array(), $catalogue->captions() );
        $this->assertNull( $catalogue->newest() );
    }

    public function testEmptyFolderIsNoBackups()
    {
        $this->assertSame( array(), $this->catalogue()->captions() );
    }

    public function testOneBackup()
    {
        GitManagerBackupFixture::caption( $this->root, '2026-09-13_21-34-34', array( 'sql_2026-09-13_21-34-34.tar.gz' => 'x' ) );
        $captions = $this->catalogue()->captions();
        $this->assertCount( 1, $captions );
        $this->assertSame( '2026-09-13_21-34-34', $captions[0]['timestamp'] );
        $this->assertSame( gmmktime( 21, 34, 34, 9, 13, 2026 ), $captions[0]['created'] );
        $this->assertSame( 'name', $captions[0]['created_source'] );
        $this->assertTrue( $captions[0]['readable'] );
    }

    /** The owner's case: an old and a new backup; the new one comes first whatever order the disk gives. */
    public function testSeveralBackupsAreNewestFirstInAnyOrder()
    {
        foreach ( array( '2026-06-21_23-50-23', '2026-09-13_21-34-34', '2025-12-31_23-59-59', '2026-09-13_21-34-33' ) as $name )
        {
            // The folder mtimes say the opposite of the names: they must not matter.
            GitManagerBackupFixture::caption( $this->root, $name, array(), 1000000000 + crc32( $name ) % 1000 );
        }
        $this->assertSame(
            array( '2026-09-13_21-34-34', '2026-09-13_21-34-33', '2026-06-21_23-50-23', '2025-12-31_23-59-59' ),
            $this->names( $this->catalogue()->captions() ) );
        $this->assertSame( '2026-09-13_21-34-34', $this->catalogue()->newest()['timestamp'] );
    }

    public function testSortNewestFirstOrdersEqualTimesByName()
    {
        $captions = array(
            array( 'timestamp' => 'a', 'created' => 100 ),
            array( 'timestamp' => 'c', 'created' => 100 ),
            array( 'timestamp' => 'b', 'created' => 100 ),
            array( 'timestamp' => 'z', 'created' => null ),
            array( 'timestamp' => 'n', 'created' => 200 ),
        );
        $this->assertSame( array( 'n', 'c', 'b', 'a', 'z' ), $this->names( GitManagerBackupCatalogue::sortNewestFirst( $captions ) ) );
        // The same input in another order gives the same output.
        $this->assertSame( array( 'n', 'c', 'b', 'a', 'z' ), $this->names( GitManagerBackupCatalogue::sortNewestFirst( array_reverse( $captions ) ) ) );
    }

    public function testAFolderWithAnotherNameIsDatedByItsMtime()
    {
        GitManagerBackupFixture::caption( $this->root, 'before-upgrade', array(), 1700000000 );
        GitManagerBackupFixture::caption( $this->root, '2026-01-01_00-00-00' );
        $captions = $this->catalogue()->captions();
        $this->assertSame( array( '2026-01-01_00-00-00', 'before-upgrade' ), $this->names( $captions ) );
        $this->assertSame( 'mtime', $captions[1]['created_source'] );
        $this->assertSame( 1700000000, $captions[1]['created'] );
        $this->assertFalse( $captions[1]['valid_name'] );
    }

    public function testHiddenEntriesFilesAndLinksAreNotBackups()
    {
        GitManagerBackupFixture::caption( $this->root, '.trash' );
        file_put_contents( $this->root . '/.htaccess', 'Require all denied' );
        file_put_contents( $this->root . '/index.html', '' );
        GitManagerBackupFixture::caption( $this->root, '2026-01-01_00-00-00' );
        symlink( $this->root . '/2026-01-01_00-00-00', $this->root . '/2027-01-01_00-00-00' );
        $this->assertSame( array( '2026-01-01_00-00-00' ), $this->names( $this->catalogue()->captions() ) );
    }

    /** The name is read in the installation's time zone: the same name is another moment in another zone. */
    public function testNamesAreReadInTheGivenTimeZone()
    {
        $la = $this->catalogue( 'America/Los_Angeles' );
        $utc = $this->catalogue( 'UTC' );
        $this->assertSame( gmmktime( 4, 34, 34, 9, 14, 2026 ), $la->timeOfName( '2026-09-13_21-34-34' ) ); // PDT = UTC-7
        $this->assertSame( gmmktime( 21, 34, 34, 9, 13, 2026 ), $utc->timeOfName( '2026-09-13_21-34-34' ) );
        $this->assertSame( gmmktime( 8, 0, 0, 1, 15, 2026 ), $la->timeOfName( '2026-01-15_00-00-00' ) ); // PST = UTC-8
    }

    public function testNameForAndTimeOfNameAgree()
    {
        foreach ( array( 'UTC', 'America/Los_Angeles', 'Europe/Berlin', 'Asia/Kolkata' ) as $zone )
        {
            $catalogue = $this->catalogue( $zone );
            foreach ( array( 1789000000, gmmktime( 12, 0, 0, 3, 8, 2026 ), gmmktime( 12, 0, 0, 11, 1, 2026 ) ) as $t )
            {
                $this->assertSame( $t, $catalogue->timeOfName( $catalogue->nameFor( $t ) ), "$zone $t" );
            }
        }
    }

    public function testInvalidNamesHaveNoTime()
    {
        $c = $this->catalogue();
        foreach ( array( '2026-02-30_10-00-00', '2026-13-01_10-00-00', '2026-01-01_25-00-00', '2026-01-01', '../2026-01-01_00-00-00', '' ) as $name )
        {
            $this->assertNull( $c->timeOfName( $name ), $name );
        }
    }

    public function testTheHourSkippedInSpringStillHasATime()
    {
        // 2026-03-08 02:30 does not exist in Los Angeles; a backup cannot be named so, but a hand-made folder can.
        $t = $this->catalogue( 'America/Los_Angeles' )->timeOfName( '2026-03-08_02-30-00' );
        $this->assertIsInt( $t );
        $this->assertEqualsWithDelta( gmmktime( 10, 30, 0, 3, 8, 2026 ), $t, 3600 );
    }

    public function testFilesAreClassified()
    {
        $n = '2026-09-13_21-34-34';
        GitManagerBackupFixture::caption( $this->root, $n, array(
            "sql_$n.tar.gz" => 'aa', "sql_agpl_$n.tar.gz" => 'b', "var_$n.tar.gz.gpg" => 'ccc', "site_$n.tar.gz" => 'd',
            'agpl_compatible.txt' => 'marker', 'description.txt' => '  Before the upgrade  ', 'notes.txt' => 'n',
        ) );
        mkdir( $this->root . "/$n/licenses" );
        $caption = $this->catalogue()->captions()[0];
        $byName = array();
        foreach ( $caption['files'] as $f )
        {
            $byName[$f['name']] = $f;
        }
        $this->assertSame( array( 'notes.txt', "site_$n.tar.gz", "sql_$n.tar.gz", "sql_agpl_$n.tar.gz", "var_$n.tar.gz.gpg" ), array_keys( $byName ) );
        $this->assertSame( 'database', $byName["sql_$n.tar.gz"]['type'] );
        $this->assertSame( 'agpl', $byName["sql_agpl_$n.tar.gz"]['type'] );
        $this->assertSame( 'var', $byName["var_$n.tar.gz.gpg"]['type'] );
        $this->assertTrue( $byName["var_$n.tar.gz.gpg"]['encrypted'] );
        $this->assertSame( 'site', $byName["site_$n.tar.gz"]['type'] );
        $this->assertSame( 'other', $byName['notes.txt']['type'] );
        $this->assertFalse( $byName['notes.txt']['archive'] );
        $this->assertTrue( $byName["sql_agpl_$n.tar.gz"]['archive'] );
        $this->assertSame( 2 + 1 + 3 + 1 + 1, $caption['total_size'] );
        $this->assertSame( 'Before the upgrade', $caption['description'] );
        $this->assertTrue( $caption['agpl_compatible'] );
    }

    public function testUnreadableBackupIsListedAsUnreadable()
    {
        if ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 )
        {
            $this->markTestSkipped( 'root reads every folder; run the tests as the site user to cover this' );
        }
        $dir = GitManagerBackupFixture::caption( $this->root, '2026-01-01_00-00-00', array( 'sql_2026-01-01_00-00-00.tar.gz' => 'x' ) );
        chmod( $dir, 0000 );
        $captions = $this->catalogue()->captions();
        chmod( $dir, 0700 );
        $this->assertCount( 1, $captions );
        $this->assertFalse( $captions[0]['readable'] );
        $this->assertSame( array(), $captions[0]['files'] );
        $this->assertSame( gmmktime( 0, 0, 0, 1, 1, 2026 ), $captions[0]['created'] );
    }

    public function testUnreadableFileStillCounts()
    {
        if ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 )
        {
            $this->markTestSkipped( 'root reads every file; run the tests as the site user to cover this' );
        }
        $n = '2026-01-01_00-00-00';
        $dir = GitManagerBackupFixture::caption( $this->root, $n, array( "sql_$n.tar.gz" => 'xyz' ) );
        chmod( "$dir/sql_$n.tar.gz", 0000 );
        $captions = $this->catalogue()->captions();
        $this->assertSame( 3, $captions[0]['files'][0]['size'] );
        $this->assertNull( $this->catalogue()->archivePath( $n, "sql_$n.tar.gz" ) );
    }

    public function testArchivePathRefusesEverythingButAnArchiveInside()
    {
        $n = '2026-01-01_00-00-00';
        GitManagerBackupFixture::caption( $this->root, $n, array( "sql_$n.tar.gz" => 'x', 'description.txt' => 'd', "sql_agpl_$n.tar.gz" => 'a' ) );
        $c = $this->catalogue();
        $this->assertSame( realpath( "{$this->root}/$n/sql_$n.tar.gz" ), $c->archivePath( $n, "sql_$n.tar.gz" ) );
        $this->assertNotNull( $c->archivePath( $n, "sql_agpl_$n.tar.gz" ) );
        $this->assertNull( $c->archivePath( $n, 'description.txt' ) );
        $this->assertNull( $c->archivePath( $n, "var_$n.tar.gz" ) );                // not there
        $this->assertNull( $c->archivePath( '..', "sql_$n.tar.gz" ) );
        $this->assertNull( $c->archivePath( "$n/..", "sql_$n.tar.gz" ) );
        $this->assertNull( $c->archivePath( $n, "../$n/sql_$n.tar.gz" ) );
        $this->assertNull( $c->archivePath( $n, "sql_$n.tar.gz/../../x" ) );
        $this->assertNull( $c->captionPath( '../../etc' ) );
        $this->assertNull( $c->captionPath( "$n\0" ) );
    }

    public function testArchivePathRefusesALinkOutOfTheBackupFolder()
    {
        $n = '2026-01-01_00-00-00';
        GitManagerBackupFixture::caption( $this->root, $n );
        $outside = dirname( $this->root ) . '/outside-' . basename( $this->root );
        file_put_contents( $outside, 'secret' );
        symlink( $outside, "{$this->root}/$n/sql_$n.tar.gz" );
        $path = $this->catalogue()->archivePath( $n, "sql_$n.tar.gz" );
        unlink( $outside );
        $this->assertNull( $path );
    }
}
