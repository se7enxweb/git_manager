<?php
/**
 * Where BackupManager keeps its backups. The folder gets a "Require all
 * denied" .htaccess and an empty index.html, so it must never be the
 * installation root: with git_manager.ini not loaded (git_manager installed
 * but not an active extension) the setting read as empty, the root got both
 * files, and the site answered every request with an empty page or a 403.
 */

use PHPUnit\Framework\TestCase;

class BackupPathTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname( __DIR__, 2 ) . '/classes/backupmanager.php';
    }

    public function testTheSettingIsBelowTheInstallation(): void
    {
        $this->assertSame( '/srv/site/var/site/backups/captions', BackupManager::backupPathFor( '/srv/site', 'var/site/backups/captions' ) );
        $this->assertSame( '/srv/site/var/backups', BackupManager::backupPathFor( '/srv/site/', './var/backups/' ) );
    }

    public function testAnEmptySettingOrTheRootFallsBackToTheDefault(): void
    {
        foreach ( array( '', ' ', '.', './', '/', false, null ) as $setting ) {
            $this->assertSame( '/srv/site/' . BackupManager::DEFAULT_BACKUP_PATH, BackupManager::backupPathFor( '/srv/site', $setting ),
                               var_export( $setting, true ) . ' never means the installation root' );
        }
    }
}
