<?php
/**
 * PHPUnit bootstrap for the git_manager unit tests.
 *
 * Loads only the plain PHP classes under test from this checkout. Nothing of
 * Exponential is booted: no database, no siteaccess, no INI, no session. The
 * default time zone is UTC, so a test that needs another one says so.
 */

date_default_timezone_set( 'UTC' );

$checkout = dirname( __DIR__ );
require_once $checkout . '/classes/gitmanagerbackupcatalogue.php';
require_once $checkout . '/classes/gitmanagerbackupfreshness.php';
require_once $checkout . '/classes/gitmanagerbackupmessages.php';
require_once __DIR__ . '/unit/BackupFixture.php';
