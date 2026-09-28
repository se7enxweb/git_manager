<?php
/**
 * @package GitManager
 * @author  7x <info@se7enx.com>
 * @date    28 Sep 2026
 *
 * git_manager/dump was the address of the backup view before 2.0.4. It now
 * sends every request on to git_manager/backup, so bookmarks and links keep
 * working. It only redirects: forms post to git_manager/backup.
 **/

return $Params['Module']->redirectTo( 'git_manager/backup' );
