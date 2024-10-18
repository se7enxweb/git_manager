<?php
/**
 * @package GitManager
 * @class   git_managerInfo
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 **/

class git_managerInfo
{
	public static function info() {
		return array(
			'Name'      => '<a href="https://github.com/se7enxweb/git_manager">Git Manager</a>',
			'Version'   => '1.0.1',
			'Author'    => '7x and Serhey Dolgushev',
			'Copyright' => 'Copyright &copy; ' . date( 'Y' ) . ' <a href="http://ua.linkedin.com/in/serheydolgushev" target="blank">Serhey Dolgushev</a> and 1999 - 2024 7x',
                        'License' => "GNU General Public License",
                        'info_url'  => 'https://github.com/se7enxweb/git_manager'
                        
		);
	}
}