<?php
/**
 * The modes BackupManager and GitManagerUpstream give the files and folders
 * they make go through the limits of the kernel (eZFile::fileMode(),
 * eZDir::dirMode(), eZFile::creationUmask(); EZP_FILE_MODE_MAX and
 * EZP_DIR_MODE_MAX in config.php). This suite loads nothing of Exponential,
 * so the first test is the extension on a kernel without those helpers: the
 * modes are exactly those of before. The second one, in a process of its
 * own, gives the helpers as the kernel has them with the limits 0770 / 0660.
 */

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class FileModesTest extends TestCase
{
    private $root;
    private $umask;

    protected function setUp(): void
    {
        $checkout = dirname( __DIR__, 2 );
        require_once $checkout . '/classes/backupmanager.php';
        require_once $checkout . '/classes/gitmanagerupstream.php';
        $this->root = GitManagerBackupFixture::root();
        // the modes asked for are the modes the files get, unless the code sets a umask of its own
        $this->umask = umask( 0 );
    }

    protected function tearDown(): void
    {
        umask( $this->umask );
        GitManagerBackupFixture::remove( $this->root );
    }

    public function testWithoutTheKernelHelpersTheModesAreThoseOfBefore(): void
    {
        $this->assertFalse( class_exists( 'eZFile', false ), 'this suite runs without the kernel' );
        $this->assertSame( array( 'folder' => 0700, 'private' => 0600, 'umask' => 0077, 'cache' => 0666 ), $this->modes() );
    }

    #[RunInSeparateProcess]
    public function testTheKernelLimitsCapTheModes(): void
    {
        // eZFile and eZDir as the kernel has them with EZP_DIR_MODE_MAX = 0770, EZP_FILE_MODE_MAX = 0660
        eval( 'class eZFile {
                   static function fileMode( $mode ) { return (int)$mode & 0660; }
                   static function creationUmask( $keep = 0 ) { return ( (int)$keep | 0007 ) & 0777; }
               }
               class eZDir {
                   static function dirMode( $mode ) { return (int)$mode & 0770; }
               }' );
        // the private folder and files stay as narrow as they were, the status cache loses what others could do
        $this->assertSame( array( 'folder' => 0700, 'private' => 0600, 'umask' => 0077, 'cache' => 0660 ), $this->modes() );
    }

    /**
     * The backup folder protectBackupRoot() makes, a file written inside
     * withPrivateFiles() and the umask there, and the upstream status cache.
     */
    private function modes()
    {
        $backup = ( new ReflectionClass( 'BackupManager' ) )->newInstanceWithoutConstructor();
        $path = new ReflectionProperty( 'BackupManager', 'backupPath' );
        $path->setValue( $backup, $this->root . '/backups' );
        $this->assertTrue( $backup->protectBackupRoot() );

        $private = new ReflectionMethod( 'BackupManager', 'withPrivateFiles' );
        $file = $this->root . '/backups/private.txt';
        $umask = $private->invoke( $backup, function () use ( $file ) {
            file_put_contents( $file, 'secret' );
            return umask();
        } );
        $this->assertSame( 0, umask(), 'the umask is put back' );

        $upstream = ( new ReflectionClass( 'GitManagerUpstream' ) )->newInstanceWithoutConstructor();
        $write = new ReflectionMethod( 'GitManagerUpstream', 'writeCache' );
        $cache = $this->root . '/upstream.json';
        $this->assertTrue( $write->invoke( $upstream, $cache, array( 'ok' => true ) ) );

        clearstatcache();
        return array( 'folder' => fileperms( $this->root . '/backups' ) & 0777, 'private' => fileperms( $file ) & 0777,
                      'umask' => $umask, 'cache' => fileperms( $cache ) & 0777 );
    }
}
