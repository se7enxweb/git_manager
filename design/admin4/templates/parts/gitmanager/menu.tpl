{*
  The left menu of the Git tab in admin4 (and the designs built on it, such as admin4l): the links, then admin4's own
  width switch (parts/left_width_control.tpl), the same markup, script and preference as every kernel module's menu.
  The navigation part gitmanagernavigationpart names it (parts/<name>/menu.tpl); the views also set it as their
  left menu.
*}
{include uri='design:parts/gitmanager/menu_items.tpl'}

{include uri='design:parts/left_width_control.tpl'}
