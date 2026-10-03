<?php
/**
 * git_manager's branch of the audit taxonomy, registered in
 * settings/audit.ini.append.php ([AuditEventSettings] Branches[git_manager]).
 * The kernel's catalogue has no name for a fetch of the repository, so the
 * subject starts with the extension's name, as the taxonomy asks.
 *
 * @copyright Copyright (C) 1998 - 2026 7x and the Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package GitManager
 */

class gitManagerAuditBranch implements expAuditTaxonomyBranch
{
	public function events() {
		return array(
			// The dashboard's "Fetch now": object = the remote, after = how
			// (ssh, https, configured), as whom, how long, the ahead/behind
			// counts after it; result failed with the reason when it failed.
			'system.git_manager.fetch' => array( 'label' => 'Git fetch', 'severity' => 'notice', 'channel' => 'system', 'default' => 'on' )
		);
	}
}

?>
