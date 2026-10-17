{*
  The links of the Git tab's left menu, from menu.ini [Leftmenu_gitmanager]. Shared by parts/gitmanager/menu.tpl of every
  admin design, so the links are written once. A link is shown only to a user who may follow it.
*}
{include uri='design:parts/ini_menu.tpl' ini_section='Leftmenu_gitmanager' i18n_hash=hash(
    'Git', 'Git'|i18n( 'extension/git_manager' ),
    'dashboard', 'Git Manager'|i18n( 'extension/git_manager' ),
    'backup', 'Backup'|i18n( 'extension/git_manager' ) )}
