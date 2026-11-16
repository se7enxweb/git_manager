<?php
/**
 * GitManagerDiff: the argument lists (never an option from a value), the parsers of history, numstat, name-status and
 * patches, and the per-table summary of a database repository (paths and counts only), plus one run against a real
 * throwaway repository through GitManager::gitAt().
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package tests
 */

class DiffTest extends PHPUnit\Framework\TestCase
{
    public function testPathsAndCommits()
    {
        foreach ( array( 'design/a.tpl', 'settings/', 'a b/c.php' ) as $p )
            $this->assertTrue( GitManagerDiff::isPath( $p ), $p );
        foreach ( array( '', '/etc/passwd', '../x', 'a/../b', '-rf', '.git/config', "a\nb", '.' ) as $p )
            $this->assertFalse( GitManagerDiff::isPath( $p ), json_encode( $p ) );
        $this->assertTrue( GitManagerDiff::isCommit( 'abcdef1' ) );
        $this->assertFalse( GitManagerDiff::isCommit( '--output=x' ) );
        $this->assertFalse( GitManagerDiff::isCommit( 'ABCDEF1' ) );
    }

    public function testArgumentsNeverTakeAnOptionFromAValue()
    {
        $a = GitManagerDiff::numstatArguments( 'abcdef1', 'now', 'design/x.tpl' );
        $this->assertSame( array( 'diff', '--no-color', '--no-ext-diff', '--no-renames', '--numstat', '-z', '--end-of-options', 'abcdef1', '--', ':(literal)design/x.tpl' ), $a );
        $this->assertSame( 'bcdef12', GitManagerDiff::patchArguments( 'abcdef1', 'bcdef12', 'a.tpl' )[7] );
        $this->expectException( InvalidArgumentException::class );
        GitManagerDiff::patchArguments( '--exec=x', 'now', 'a.tpl' );
    }

    public function testHistoryArgumentsUseTheLogFilters()
    {
        $a = GitManagerDiff::historyArguments( array( 'since' => '2026-11-01', 'until' => 'bad' ), '--all', 20, 500 );
        $this->assertContains( '--since=2026-11-01 00:00:00', $a );
        $this->assertContains( '--max-count=200', $a );
        $this->assertSame( array( '--end-of-options', 'HEAD', '--' ), array_slice( $a, -3 ) );
    }

    public function testParseHistory()
    {
        $h1 = str_repeat( 'a', 40 );
        $h2 = str_repeat( 'b', 40 );
        $out = "\x1e$h1\x1f7x\x1f1763290000\x1fUpdated: Hourly state\n\n 3 files changed, 10 insertions(+), 2 deletions(-)\n"
             . "\x1e$h2\x1f7x\x1f1763280000\x1fAdded: x\n\n 1 file changed, 1 insertion(+)\n";
        $c = GitManagerDiff::parseHistory( $out );
        $this->assertCount( 2, $c );
        $this->assertSame( array( 'hash' => $h1, 'author' => '7x', 'time' => 1763290000, 'title' => 'Updated: Hourly state', 'files' => 3, 'insertions' => 10, 'deletions' => 2 ), $c[0] );
        $this->assertSame( 0, $c[1]['deletions'] );
    }

    public function testFilesMergeNumstatAndStatus()
    {
        $numstat = "3\t1\tdesign/a.tpl\0-\t-\tvar/storage/x.jpg\0" . "0\t5\tsettings/override/site.ini.append.php\0";
        $status = "M\0design/a.tpl\0A\0var/storage/x.jpg\0D\0settings/override/site.ini.append.php\0";
        $f = GitManagerDiff::files( $numstat, $status );
        $this->assertSame( array( 'design/a.tpl', 'settings/override/site.ini.append.php', 'var/storage/x.jpg' ), array_column( $f, 'path' ) );
        $this->assertTrue( $f[0]['text'] );
        $this->assertTrue( $f[1]['secret'] );
        $this->assertSame( 'D', $f[1]['status'] );
        $this->assertTrue( $f[2]['binary'] );
        $this->assertFalse( $f[2]['text'] );
    }

    public function testPatchIsParsedAndMasked()
    {
        $patch = "diff --git a/x.ini b/x.ini\nindex 1..2 100644\n--- a/x.ini\n+++ b/x.ini\n@@ -1,2 +1,2 @@\n [DatabaseSettings]\n-Password=oldpw\n+Password=newpw\n\\ No newline at end of file\n";
        $p = GitManagerDiff::parsePatch( $patch );
        $this->assertSame( array( 'hunk', 'ctx', 'del', 'add', 'meta' ), array_column( $p['lines'], 'kind' ) );
        $this->assertSame( 'Password=' . GitManagerSecretMask::MASK, $p['lines'][2]['text'] );
        $this->assertStringNotContainsString( 'newpw', json_encode( $p ) );
        $this->assertTrue( GitManagerDiff::parsePatch( $patch, 2 )['truncated'] );
    }

    public function testTableSummaryHasCountsOnly()
    {
        $files = array(
            array( 'path' => 'data/ezuser.sql', 'status' => 'M', 'added' => 2, 'removed' => 1 ),
            array( 'path' => 'data/ezcontentobject/part-0001.sql', 'status' => 'M', 'added' => 5, 'removed' => 0 ),
            array( 'path' => 'data/ezcontentobject/part-0002.sql', 'status' => 'A', 'added' => 3, 'removed' => 0 ),
            array( 'path' => 'schema/newtable.sql', 'status' => 'A', 'added' => 9, 'removed' => 0 ),
            array( 'path' => 'data/newtable.sql', 'status' => 'A', 'added' => 1, 'removed' => 0 ),
            array( 'path' => 'data/_sequences.sql', 'status' => 'M', 'added' => 1, 'removed' => 1 ),
            array( 'path' => 'TABLES', 'status' => 'M', 'added' => 1, 'removed' => 0 ),
        );
        $s = GitManagerDiff::tableSummary( $files );
        $this->assertSame( 2, $s['other_files'] );
        $by = array_column( $s['tables'], null, 'table' );
        $this->assertSame( 8, $by['ezcontentobject']['rows_added'] );
        $this->assertSame( 'changed', $by['ezuser']['status'] );
        $this->assertSame( 'added', $by['newtable']['status'] );
        $this->assertTrue( $by['newtable']['schema'] );
        $this->assertSame( array( 'table', 'rows_added', 'rows_removed', 'schema', 'status' ), array_keys( $by['ezuser'] ) );
    }

    public function testRealRepository()
    {
        $dir = sys_get_temp_dir() . '/gm-diff-' . getmypid() . '-' . mt_rand();
        mkdir( $dir );
        $env = array( 'GIT_AUTHOR_NAME' => 't', 'GIT_AUTHOR_EMAIL' => 't@example.com', 'GIT_COMMITTER_NAME' => 't', 'GIT_COMMITTER_EMAIL' => 't@example.com' );
        GitManager::gitAt( $dir, array( 'init', '-q', '-b', 'main' ), 30, 0, $env );
        file_put_contents( "$dir/page.tpl", "<h1>One</h1>\n" );
        file_put_contents( "$dir/logo.png", "\x89PNG\0\0binary" );
        GitManager::gitAt( $dir, array( 'add', '-A' ), 30, 0, $env );
        GitManager::gitAt( $dir, array( 'commit', '-q', '-m', 'Added: one' ), 30, 0, $env );
        $first = trim( GitManager::gitAt( $dir, array( 'rev-parse', 'HEAD' ) )['output'] );
        file_put_contents( "$dir/page.tpl", "<h1>Two</h1>\n" );
        file_put_contents( "$dir/logo.png", "\x89PNG\0\0binary2" );
        GitManager::gitAt( $dir, array( 'commit', '-q', '-am', 'Updated: two' ), 30, 0, $env );
        $h = GitManagerDiff::parseHistory( GitManager::gitAt( $dir, GitManagerDiff::historyArguments( array(), 'HEAD', 0, 10 ) )['output'] );
        $this->assertSame( array( 'Updated: two', 'Added: one' ), array_column( $h, 'title' ) );
        $this->assertSame( 2, $h[0]['files'] );
        $f = GitManagerDiff::files( GitManager::gitAt( $dir, GitManagerDiff::numstatArguments( $first, 'HEAD' ) )['output'],
                                    GitManager::gitAt( $dir, GitManagerDiff::nameStatusArguments( $first, 'HEAD' ) )['output'] );
        $this->assertSame( array( 'logo.png', 'page.tpl' ), array_column( $f, 'path' ) );
        $this->assertTrue( $f[0]['binary'] );
        $p = GitManagerDiff::parsePatch( GitManager::gitAt( $dir, GitManagerDiff::patchArguments( $first, 'now', 'page.tpl' ) )['output'] );
        $this->assertSame( array( 'hunk', 'del', 'add' ), array_column( $p['lines'], 'kind' ) );
        $cut = GitManager::gitAt( $dir, array( 'log', '-p' ), 30, 10 );
        $this->assertTrue( $cut['truncated'] );
        $this->assertSame( 10, strlen( $cut['output'] ) );
        $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $items as $item )
        {
            if ( $item->isDir() )
                rmdir( $item->getPathname() );
            else
            {
                chmod( $item->getPathname(), 0644 );
                unlink( $item->getPathname() );
            }
        }
        rmdir( $dir );
        $this->assertDirectoryDoesNotExist( $dir );
    }
}
