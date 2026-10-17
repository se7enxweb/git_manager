{* The texts of git_manager.js as data-gm-* attributes of the page's root (.a4-gm), printed inside its tag:
   <div class="a4-gm ..." {include uri='design:git_manager/parts/data.tpl'}>. No inline script (CSP). *}
data-gm-copied="{'Copied'|i18n( 'extension/git_manager' )|wash}"
     data-gm-choose="{'Choose a branch first.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-head="{if is_set( $git_manager )}{$git_manager.current_branch|wash}{/if}"
     data-gm-state="{'It is %ahead commits ahead of the remote and %behind behind.'|i18n( 'extension/git_manager' )|wash}"
