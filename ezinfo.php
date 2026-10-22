<?php
/**
 * @package GitManager
 * @class   git_managerInfo
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    26 Sep 2013
 **/

class git_managerInfo
{
    public static function info()
    {
        return array(
            'Name'      => '<a href="https://github.com/se7enxweb/git_manager">Git Manager</a>',
            'Version'   => '2.0.22',
            'Author'    => 'Serhey Dolgushev and 7x',
            'Copyright' => 'Copyright &copy; 2013 - 2014 <a href="https://ua.linkedin.com/in/serheydolgushev">Serhey Dolgushev</a> and 1998 - ' . date( 'Y' ) . ' 7x',
            'License'   => 'GNU General Public License v2.0 (or any later version)',
            'Info_url'  => 'https://github.com/se7enxweb/git_manager'
        );
    }
}
