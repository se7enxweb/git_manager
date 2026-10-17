{* git_manager/dashboard: where the installation stands against its remote (parts/upstream.tpl), where HEAD is, the
   checkouts, the remotes with fetch and push, the submodules and the commit log. Built from the administration's
   shared components (.a4-card, .a4-btn, .a4-badge, .a4-notice, .a4-filterbar, .a4-field, .a4-empty ...); what they
   cannot do is in design/standard/stylesheets/modules/git_manager.css (.a4-gm-*), loaded through design.ini
   [StylesheetSettings] BackendCSSFileList. The behaviours are git_manager.js (parts/script.tpl), with its texts as
   data-gm-* attributes of the root (parts/data.tpl). Every form posts to git_manager/dashboard with the same names as
   before (ezformtoken adds its token). *}
{def $local_branches  = $git_manager.local_branches
     $remote_branches = $git_manager.remote_branches
     $head_branch     = $git_manager.current_branch
     $head_commit     = $git_manager.current_commit
     $detached        = or( $head_branch|contains( 'detached' ), $head_branch|contains( 'HEAD' ) )}

<div class="context-block a4-page a4-gm a4-gm-dashboard" {include uri='design:git_manager/parts/data.tpl'}>

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Git Manager'|i18n( 'extension/git_manager' )}</h1>
<div class="header-mainline"></div>
</div></div>

<div class="box-ml"><div class="box-mr"><div class="box-content">

{if $error}
<div class="a4-notice is-bad" role="alert"><p>{$error|wash}</p></div>
{/if}
{if $message}
<div class="a4-notice is-ok" role="status"><p>{$message|wash}</p></div>
{/if}

{* Where the installation stands against its branch on the remote. *}
{if $upstream}{include uri='design:git_manager/parts/upstream.tpl'}{/if}

{* Where HEAD is. *}
<section class="a4-card a4-gm-status" aria-label="{'HEAD'|i18n( 'extension/git_manager' )|wash}">
    <div class="a4-gm-status-branch">
        <span class="a4-meta">{'HEAD'|i18n( 'extension/git_manager' )}</span>
        <span class="a4-status {if $detached}is-warn{else}is-ok{/if} a4-gm-branch"><span class="a4-dot" aria-hidden="true"></span>{$head_branch|wash}</span>
        <span class="a4-gm-hash"><a class="a4-code" href={concat( 'git_manager/commit_details/', $head_commit )|ezurl} title="{$head_commit|wash}">{$head_commit|shorten( 10, '' )|wash}</a><button type="button" class="a4-btn a4-btn-ghost a4-btn-small a4-gm-copy" data-copy="{$head_commit|wash}" title="{'Copy the full hash'|i18n( 'extension/git_manager' )|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>
    </div>
    <p class="a4-gm-facts">
        <span><strong>{$local_branches|count}</strong> {'local branches'|i18n( 'extension/git_manager' )}</span>
        <span><strong>{$remote_branches|count}</strong> {'remote branches'|i18n( 'extension/git_manager' )}</span>
        {if $head_commit_date}<span>{'Last commit'|i18n( 'extension/git_manager' )} <time class="a4-gm-when" datetime="{$head_commit_date|wash}" title="{$head_commit_date|wash}">{$head_commit_date|wash}</time></span>{/if}
    </p>
</section>

{* The checkouts: the same forms and names as always, as cards. *}
<div class="a4-grid a4-gm-checkouts">
    <form class="a4-card" action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Check out the local branch chosen here and pull it from origin? The installation then runs that branch\'s code.'|i18n( 'extension/git_manager' )|wash}">
        <div class="a4-card-head"><h2 class="a4-h3">{'Local branch'|i18n( 'extension/git_manager' )}</h2></div>
        <div class="a4-card-body a4-stack">
            <div class="a4-field">
                <label for="gm-local-branch">{'Branch'|i18n( 'extension/git_manager' )}</label>
                <select id="gm-local-branch" name="branch" required="required">
                    <option value="">{'- Select -'|i18n( 'extension/git_manager' )}</option>
                    {foreach $local_branches as $branch}
                    <option value="{$branch|wash}">{$branch|wash}{if eq( $branch, $head_branch )} ({'current'|i18n( 'extension/git_manager' )}){/if}</option>
                    {/foreach}
                </select>
            </div>
            <label class="a4-check"><input type="checkbox" name="regenerate" value="regenerate" /> <span>{'Regenerate autoloads'|i18n( 'extension/git_manager' )}</span></label>
        </div>
        <div class="a4-card-foot">
            <input class="a4-btn a4-btn-primary" type="submit" name="CheckoutLocalBranch" value="{'Checkout'|i18n( 'extension/git_manager' )}" />
        </div>
    </form>

    <form class="a4-card" action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Check out the remote branch chosen here and pull it from origin? The installation then runs that branch\'s code.'|i18n( 'extension/git_manager' )|wash}">
        <div class="a4-card-head"><h2 class="a4-h3">{'Remote branch'|i18n( 'extension/git_manager' )}</h2></div>
        <div class="a4-card-body a4-stack">
            <div class="a4-field">
                <label for="gm-remote-branch">{'Branch'|i18n( 'extension/git_manager' )}</label>
                <select id="gm-remote-branch" name="branch" required="required">
                    <option value="">{'- Select -'|i18n( 'extension/git_manager' )}</option>
                    {foreach $remote_branches as $branch}
                    <option value="{$branch|wash}">{$branch|wash}</option>
                    {/foreach}
                </select>
            </div>
            <label class="a4-check"><input type="checkbox" name="regenerate" value="regenerate" /> <span>{'Regenerate autoloads'|i18n( 'extension/git_manager' )}</span></label>
        </div>
        <div class="a4-card-foot">
            <input class="a4-btn a4-btn-primary" type="submit" name="CheckoutRemoteBranch" value="{'Checkout'|i18n( 'extension/git_manager' )}" />
        </div>
    </form>
</div>

{* Remotes: each one, how the checked out branch stands against it, fetch and push. *}
<section class="a4-card a4-gm-section" aria-labelledby="gm-remotes-title">
    <div class="a4-card-head">
        <h2 class="a4-h3" id="gm-remotes-title">{'Remotes and push'|i18n( 'extension/git_manager' )}</h2>
        <p>{'A push is never forced: when the remote has commits this branch does not, it is refused and the output says so. Fetch first to see where a remote stands.'|i18n( 'extension/git_manager' )}</p>
    </div>
    <div class="a4-card-body">
    {if $remotes|count}
    <ul class="a4-gm-items">
    {foreach $remotes as $remote}
        <li class="a4-gm-item">
            <div class="a4-gm-item-name">
                <strong>{$remote.name|wash}</strong>
                <code class="a4-gm-url">{$remote.url|wash}</code>
            </div>
            <div class="a4-badges">
                {if $remote.state}
                    {if and( eq( $remote.state.ahead, 0 ), eq( $remote.state.behind, 0 ) )}
                        <span class="a4-badge is-ok">{'%branch is up to date'|i18n( 'extension/git_manager',, hash( '%branch', $head_branch ) )|wash}</span>
                    {else}
                        {if gt( $remote.state.ahead, 0 )}<span class="a4-badge is-info">{'%count to push'|i18n( 'extension/git_manager',, hash( '%count', $remote.state.ahead ) )}</span>{/if}
                        {if gt( $remote.state.behind, 0 )}<span class="a4-badge is-warn">{'%count behind'|i18n( 'extension/git_manager',, hash( '%count', $remote.state.behind ) )}</span>{/if}
                    {/if}
                {else}
                    <span class="a4-badge">{'%branch is not on this remote yet'|i18n( 'extension/git_manager',, hash( '%branch', $head_branch ) )|wash}</span>
                {/if}
            </div>
            <div class="a4-actions a4-gm-item-actions">
                <form action={'git_manager/dashboard'|ezurl} method="post">
                    <input type="hidden" name="remote" value="{$remote.name|wash}" />
                    <input class="a4-btn" type="submit" name="FetchRemote" value="{'Fetch'|i18n( 'extension/git_manager' )}" />
                </form>
                {if $can_push}
                <form action={'git_manager/dashboard'|ezurl} method="post" class="a4-gm-push-form"
                      data-gm-push="{'Push %branch to %remote (%url)? The commits go out to everyone who uses that remote.'|i18n( 'extension/git_manager' )|wash}"
                      data-remote="{$remote.name|wash}" data-url="{$remote.url|wash}"
                      {if $remote.state}data-ahead="{$remote.state.ahead}" data-behind="{$remote.state.behind}"{/if}>
                    <input type="hidden" name="remote" value="{$remote.name|wash}" />
                    <select class="a4-input is-auto" name="branch" aria-label="{'Branch to push'|i18n( 'extension/git_manager' )|wash}">
                        {foreach $local_branches as $branch}
                        <option value="{$branch|wash}"{if eq( $branch, $head_branch )} selected="selected"{/if}>{$branch|wash}</option>
                        {/foreach}
                    </select>
                    <input class="a4-btn a4-btn-primary" type="submit" name="PushBranch" value="{'Push'|i18n( 'extension/git_manager' )}" />
                </form>
                {/if}
            </div>
            {if $can_manage_remotes}
            <details class="a4-fold a4-gm-item-edit">
                <summary>{'Edit'|i18n( 'extension/git_manager' )}</summary>
                <div class="a4-fold-body">
                    <form action={'git_manager/dashboard'|ezurl} method="post" class="a4-gm-form">
                        <input type="hidden" name="remote" value="{$remote.name|wash}" />
                        <div class="a4-field">
                            <label for="gm-remote-name-{$remote.name|wash}">{'Name'|i18n( 'extension/git_manager' )}</label>
                            <input type="text" id="gm-remote-name-{$remote.name|wash}" name="new_name" value="{$remote.name|wash}" required="required" pattern="[A-Za-z0-9_][A-Za-z0-9._\-]*" />
                        </div>
                        <div class="a4-field a4-gm-form-url">
                            <label for="gm-remote-url-{$remote.name|wash}">{'Address'|i18n( 'extension/git_manager' )}</label>
                            <input type="text" id="gm-remote-url-{$remote.name|wash}" name="url" value="{$remote.url|wash}" required="required" spellcheck="false" />
                        </div>
                        <div class="a4-actions">
                            <input class="a4-btn a4-btn-primary" type="submit" name="UpdateRemote" value="{'Save'|i18n( 'extension/git_manager' )}" />
                            <input class="a4-btn a4-btn-outline-danger" type="submit" name="RemoveRemote" value="{'Remove'|i18n( 'extension/git_manager' )}" formnovalidate="formnovalidate"
                                   data-gm-confirm-button="{'Remove the remote %remote? Its remote-tracking branches go with it; the commits stay.'|i18n( 'extension/git_manager',, hash( '%remote', $remote.name ) )|wash}" />
                        </div>
                    </form>
                    <p class="a4-help">{'An address shown without its user name, password or token is left as it is when saved unchanged.'|i18n( 'extension/git_manager' )}</p>
                </div>
            </details>
            {/if}
        </li>
    {/foreach}
    </ul>
    {if $can_push|not}<p class="a4-help">{'Pushing needs the git_manager/push policy.'|i18n( 'extension/git_manager' )}</p>{/if}
    {else}
    <p class="a4-empty">{'This repository has no remotes.'|i18n( 'extension/git_manager' )}</p>
    {/if}
    {if $can_manage_remotes}
    <details class="a4-fold a4-gm-add"{if $remotes|count|not} open="open"{/if}>
        <summary>{'Add a remote'|i18n( 'extension/git_manager' )}</summary>
        <div class="a4-fold-body">
            <form action={'git_manager/dashboard'|ezurl} method="post" class="a4-gm-form">
                <div class="a4-field">
                    <label for="gm-add-remote-name">{'Name'|i18n( 'extension/git_manager' )}</label>
                    <input type="text" id="gm-add-remote-name" name="remote" required="required" placeholder="upstream" pattern="[A-Za-z0-9_][A-Za-z0-9._\-]*" />
                </div>
                <div class="a4-field a4-gm-form-url">
                    <label for="gm-add-remote-url">{'Address'|i18n( 'extension/git_manager' )}</label>
                    <input type="text" id="gm-add-remote-url" name="url" required="required" spellcheck="false" placeholder="https://github.com/owner/repository.git" />
                </div>
                <div class="a4-actions">
                    <input class="a4-btn a4-btn-primary" type="submit" name="AddRemote" value="{'Add'|i18n( 'extension/git_manager' )}" />
                </div>
            </form>
            <p class="a4-help">{'https://, ssh://, git:// or file:// addresses, user@host:path, or an absolute path.'|i18n( 'extension/git_manager' )}</p>
        </div>
    </details>
    {else}
    <p class="a4-help">{'Adding, changing and removing remotes needs the git_manager/remotes policy.'|i18n( 'extension/git_manager' )}</p>
    {/if}
    </div>
</section>

{* Submodules: the list, and adding, changing, updating and removing them. *}
<section class="a4-card a4-gm-section" aria-labelledby="gm-submodules-title">
    <div class="a4-card-head">
        <h2 class="a4-h3" id="gm-submodules-title">{'Submodules'|i18n( 'extension/git_manager' )} <span class="a4-badge">{$submodules|count}</span></h2>
        {if $submodules|count}
        <div class="a4-card-actions">
            <form action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Update the submodules to the commits this branch records?'|i18n( 'extension/git_manager' )|wash}">
                <input class="a4-btn" type="submit" name="CheckoutUpdateSubmodules" value="{'Update all submodules'|i18n( 'extension/git_manager' )}" />
            </form>
        </div>
        {/if}
        <p>{'Adding, changing and removing a submodule changes the working tree and stages the change; it is kept once it is committed.'|i18n( 'extension/git_manager' )}</p>
    </div>
    <div class="a4-card-body">
    {if $submodules|count}
    <ul class="a4-gm-items">
    {foreach $submodules as $sub}
        <li class="a4-gm-item">
            <div class="a4-gm-item-name">
                <strong>{$sub.path|wash}</strong>
                <code class="a4-gm-url">{$sub.url|wash}</code>
            </div>
            <div class="a4-badges">
                {if $sub.branch}<span class="a4-badge" title="{'Follows this branch'|i18n( 'extension/git_manager' )|wash}">{$sub.branch|wash}</span>{/if}
                {if eq( $sub.state, 'current' )}<span class="a4-badge is-ok">{'up to date'|i18n( 'extension/git_manager' )}</span>
                {elseif eq( $sub.state, 'not_initialized' )}<span class="a4-badge is-info">{'not checked out'|i18n( 'extension/git_manager' )}</span>
                {elseif eq( $sub.state, 'changed' )}<span class="a4-badge is-warn" title="{'The checked out commit is not the one this branch records'|i18n( 'extension/git_manager' )|wash}">{'other commit'|i18n( 'extension/git_manager' )}</span>
                {elseif eq( $sub.state, 'conflict' )}<span class="a4-badge is-bad">{'conflict'|i18n( 'extension/git_manager' )}</span>
                {else}<span class="a4-badge is-bad">{'not in the index'|i18n( 'extension/git_manager' )}</span>{/if}
                {if $sub.commit}<span class="a4-gm-hash"><span class="a4-code">{$sub.commit|shorten( 10, '' )|wash}</span><button type="button" class="a4-btn a4-btn-ghost a4-btn-small a4-gm-copy" data-copy="{$sub.commit|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>{/if}
            </div>
            <div class="a4-actions a4-gm-item-actions">
                <form action={'git_manager/dashboard'|ezurl} method="post">
                    <input type="hidden" name="submodule" value="{$sub.name|wash}" />
                    <input class="a4-btn" type="submit" name="UpdateSubmodule" value="{'Update'|i18n( 'extension/git_manager' )}" />
                </form>
            </div>
            {if $can_manage_submodules}
            <details class="a4-fold a4-gm-item-edit">
                <summary>{'Edit'|i18n( 'extension/git_manager' )}</summary>
                <div class="a4-fold-body">
                    <form action={'git_manager/dashboard'|ezurl} method="post" class="a4-gm-form">
                        <input type="hidden" name="submodule" value="{$sub.name|wash}" />
                        <div class="a4-field a4-gm-form-url">
                            <label for="gm-sub-url-{$sub.name|wash}">{'Address'|i18n( 'extension/git_manager' )}</label>
                            <input type="text" id="gm-sub-url-{$sub.name|wash}" name="url" value="{$sub.url|wash}" required="required" spellcheck="false" />
                        </div>
                        <div class="a4-field">
                            <label for="gm-sub-branch-{$sub.name|wash}">{'Branch'|i18n( 'extension/git_manager' )}</label>
                            <input type="text" id="gm-sub-branch-{$sub.name|wash}" name="submodule_branch" value="{$sub.branch|wash}" placeholder="{'none'|i18n( 'extension/git_manager' )|wash}" />
                        </div>
                        <div class="a4-actions">
                            <input class="a4-btn a4-btn-primary" type="submit" name="EditSubmodule" value="{'Save'|i18n( 'extension/git_manager' )}" />
                            <input class="a4-btn a4-btn-outline-danger" type="submit" name="RemoveSubmodule" value="{'Remove'|i18n( 'extension/git_manager' )}" formnovalidate="formnovalidate"
                                   data-gm-confirm-button="{'Remove the submodule %name? Its files leave the working tree and the removal is staged for a commit.'|i18n( 'extension/git_manager',, hash( '%name', $sub.path ) )|wash}" />
                        </div>
                    </form>
                    <p class="a4-help">{'Name in .gitmodules'|i18n( 'extension/git_manager' )}: <code class="a4-code">{$sub.name|wash}</code></p>
                </div>
            </details>
            {/if}
        </li>
    {/foreach}
    </ul>
    {else}
    <p class="a4-empty">{'This repository has no submodules.'|i18n( 'extension/git_manager' )}</p>
    {/if}
    {if $can_manage_submodules}
    {* Closed unless this user opened it, or the add just failed validation, so the error is never hidden. Kept as
       the user preference admin_git_manager_add_submodule; unset means closed. *}
    <details class="a4-fold a4-gm-add" id="gm-add-submodule-card"{if or( eq( ezpreference( 'admin_git_manager_add_submodule' ), '1' ), $submodule_add_failed )} open="open"{/if}
             data-preference-url={'/user/preferences/set_and_exit/admin_git_manager_add_submodule'|ezurl}>
        <summary>{'Add a submodule'|i18n( 'extension/git_manager' )}</summary>
        <div class="a4-fold-body">
            <form action={'git_manager/dashboard'|ezurl} method="post" class="a4-gm-form"
                  data-gm-confirm="{'Clone the repository into this path and stage it as a submodule?'|i18n( 'extension/git_manager' )|wash}">
                <div class="a4-field a4-gm-form-url">
                    <label for="gm-add-sub-url">{'Address'|i18n( 'extension/git_manager' )}</label>
                    <input type="text" id="gm-add-sub-url" name="url" required="required" spellcheck="false" placeholder="https://github.com/owner/repository.git" />
                </div>
                <div class="a4-field">
                    <label for="gm-add-sub-path">{'Path'|i18n( 'extension/git_manager' )}</label>
                    <input type="text" id="gm-add-sub-path" name="path" required="required" placeholder="extension/example" />
                </div>
                <div class="a4-field">
                    <label for="gm-add-sub-branch">{'Branch'|i18n( 'extension/git_manager' )}</label>
                    <input type="text" id="gm-add-sub-branch" name="submodule_branch" placeholder="{'optional'|i18n( 'extension/git_manager' )|wash}" />
                </div>
                <div class="a4-actions">
                    <input class="a4-btn a4-btn-primary" type="submit" name="AddSubmodule" value="{'Add'|i18n( 'extension/git_manager' )}" />
                </div>
            </form>
            <p class="a4-help">{'The path is relative to the installation, and must not exist yet. The address may also be relative to this repository\'s remote: ../name.git.'|i18n( 'extension/git_manager' )}</p>
        </div>
    </details>
    {else}
    <p class="a4-help">{'Adding, changing and removing submodules needs the git_manager/submodules policy.'|i18n( 'extension/git_manager' )}</p>
    {/if}
    </div>
</section>

{if $output}
<section class="a4-gm-output" aria-labelledby="gm-output-title">
    <div class="a4-gm-output-head">
        <h2 class="a4-h3" id="gm-output-title">{'Output'|i18n( 'extension/git_manager' )}</h2>
        <button type="button" class="a4-btn a4-btn-small a4-gm-copy" data-copy-from="gm-output-text">{'Copy'|i18n( 'extension/git_manager' )}</button>
    </div>
    <pre id="gm-output-text" tabindex="0">{$output|wash}</pre>
</section>
{/if}

{* The commit log, one page at a time: folded unless this user opened it (the user preference
   admin_git_manager_commit_log) or the address asks for a page or a filter. The page, its size and the filters are
   view parameters (/(offset)/50/(limit)/25/(ref)/main/(author)/jane/(grep)/fix/(since)/2026-01-01/(until)/...), so a
   link reproduces the view; the page size is also kept as the preference admin_git_manager_log_limit. The pages are
   the shared pager (design:navigator/google.tpl) with first and last next to it. The summary says what is waiting to
   be pushed among the commits shown. *}
<section class="a4-section a4-gm-log-section" aria-labelledby="gm-commits-title">
<h2 class="a4-h2" id="gm-commits-title">{'Commits log'|i18n( 'extension/git_manager' )} <span class="a4-badge" title="{'Commits in the log with these filters'|i18n( 'extension/git_manager' )|wash}">{$log_paging.total}</span></h2>
<details class="a4-fold a4-gm-log-card" id="gm-log-card"{if or( eq( ezpreference( 'admin_git_manager_commit_log' ), '1' ), $log_open )} open="open"{/if}
         data-preference-url={'/user/preferences/set_and_exit/admin_git_manager_commit_log'|ezurl}>
    <summary>
        <span class="a4-gm-log-summary-title">{if $log_paging.total}{'Showing %from–%to of %total commits'|i18n( 'extension/git_manager',, hash( '%from', $log_paging.from, '%to', $log_paging.to, '%total', $log_paging.total ) )}{else}{'No commits'|i18n( 'extension/git_manager' )}{/if}</span>
        {if $local_only_count}<span class="a4-badge is-warn">{'%count not pushed anywhere'|i18n( 'extension/git_manager',, hash( '%count', $local_only_count ) )}</span>{/if}
        {foreach $unpushed_counts as $remote_name => $count}{if $count}<span class="a4-badge is-info">{'%count not on %remote'|i18n( 'extension/git_manager',, hash( '%count', $count, '%remote', $remote_name ) )|wash}</span>{/if}{/foreach}
        {if and( $remotes|count, $commits|count, eq( $unpushed_total, 0 ) )}<span class="a4-badge is-ok">{'Everything here is on every remote'|i18n( 'extension/git_manager' )}</span>{/if}
    </summary>
    <div class="a4-fold-body">

    {if $log_unknown_ref}
    <div class="a4-notice is-warn" role="status"><p>{'This repository has no branch or tag %ref; the log shows HEAD.'|i18n( 'extension/git_manager',, hash( '%ref', $log_unknown_ref ) )|wash}</p></div>
    {/if}

    <form class="a4-filterbar" id="gm-filter" action={'git_manager/dashboard'|ezurl} method="post">
        <input type="hidden" name="log_limit" value="{$log_paging.limit}" />
        <div class="a4-field">
            <label for="gm-filter-ref">{'Branch'|i18n( 'extension/git_manager' )}</label>
            <select class="a4-input is-auto" id="gm-filter-ref" name="filter[ref]">
                <option value="">{'HEAD (checked out)'|i18n( 'extension/git_manager' )}</option>
                {if $log_branches.local|count}
                <optgroup label="{'Local branches'|i18n( 'extension/git_manager' )|wash}">
                {foreach $log_branches.local as $branch_name}<option value="{$branch_name|wash}"{if eq( $branch_name, $filter.ref )} selected="selected"{/if}>{$branch_name|wash}</option>{/foreach}
                </optgroup>
                {/if}
                {if $log_branches.remote|count}
                <optgroup label="{'Remote branches'|i18n( 'extension/git_manager' )|wash}">
                {foreach $log_branches.remote as $branch_name}<option value="{$branch_name|wash}"{if eq( $branch_name, $filter.ref )} selected="selected"{/if}>{$branch_name|wash}</option>{/foreach}
                </optgroup>
                {/if}
                {if and( $filter.ref, $log_branches.local|contains( $filter.ref )|not, $log_branches.remote|contains( $filter.ref )|not )}
                <option value="{$filter.ref|wash}" selected="selected">{$filter.ref|wash}</option>
                {/if}
            </select>
        </div>
        <div class="a4-field">
            <label for="gm-filter-author">{'Author'|i18n( 'extension/git_manager' )}</label>
            <input type="text" id="gm-filter-author" name="filter[author]" maxlength="200" value="{$filter.author|wash}" placeholder="{'Name or e-mail'|i18n( 'extension/git_manager' )|wash}" />
        </div>
        <div class="a4-field">
            <label for="gm-filter-grep">{'Message contains'|i18n( 'extension/git_manager' )}</label>
            <input type="text" id="gm-filter-grep" name="filter[grep]" maxlength="200" value="{$filter.grep|wash}" placeholder="{'Text, any case'|i18n( 'extension/git_manager' )|wash}" />
        </div>
        <div class="a4-field">
            <label for="gm-filter-start">{'Start date'|i18n( 'extension/git_manager' )}</label>
            <input type="date" id="gm-filter-start" name="filter[start_date]" value="{$filter.start_date|wash}" />
        </div>
        <div class="a4-field">
            <label for="gm-filter-end">{'End date'|i18n( 'extension/git_manager' )}</label>
            <input type="date" id="gm-filter-end" name="filter[end_date]" value="{$filter.end_date|wash}" />
        </div>
        <div class="a4-actions">
            <input class="a4-btn a4-btn-primary" type="submit" name="SetCommitsFilter" value="{'Filter'|i18n( 'extension/git_manager' )}" />
            {if $log_filtered}
            <a class="a4-btn" href={$log_clear_url|ezurl}>{'Clear'|i18n( 'extension/git_manager' )}</a>
            {/if}
        </div>
        <div class="a4-field">
            <label for="gm-log-find">{'Find on this page'|i18n( 'extension/git_manager' )}</label>
            <input type="search" id="gm-log-find" placeholder="{'Title, author or hash'|i18n( 'extension/git_manager' )|wash}" />
        </div>
    </form>

    {if $remotes|count}
    <div class="a4-gm-legend">
        <span><i class="a4-gm-swatch is-local" aria-hidden="true"></i>{'not pushed to any remote'|i18n( 'extension/git_manager' )}</span>
        <span><i class="a4-gm-swatch is-partial" aria-hidden="true"></i>{'missing on some remotes'|i18n( 'extension/git_manager' )}</span>
        <span><i class="a4-gm-swatch is-head" aria-hidden="true"></i>{'checked out (HEAD)'|i18n( 'extension/git_manager' )}</span>
        <label class="a4-check a4-gm-legend-check"><input type="checkbox" id="gm-only-unpushed" /> <span>{'Only commits to push'|i18n( 'extension/git_manager' )}</span></label>
    </div>
    {/if}

    {if $commits|count}
    <ul class="a4-gm-log" id="gm-log">
    {foreach $commits as $commit}
        <li class="{if eq( $commit.hash, $head_commit )}is-head {/if}{if $commit.local_only}is-local{elseif $commit.missing|count}is-partial{/if}"
            data-find="{$commit.title|wash} {$commit.author|wash} {$commit.hash|wash}"{if $commit.missing|count} data-unpushed="1"{/if}>
            <span class="a4-gm-avatar" data-name="{$commit.author|wash}" aria-hidden="true"></span>
            <div class="a4-gm-log-main">
                <a class="a4-gm-log-title" href={concat( 'git_manager/commit_details/', $commit.hash )|ezurl} title="{$commit.title|wash}">{$commit.title|wash}</a>
                <span class="a4-meta">{$commit.author|wash} &middot; <time class="a4-gm-when" datetime="{$commit.date|wash}" title="{$commit.date|wash}">{$commit.date|wash}</time></span>
            </div>
            <div class="a4-gm-log-side">
                {if $commit.local_only}
                    <span class="a4-badge is-warn" title="{'No remote has this commit yet'|i18n( 'extension/git_manager' )|wash}">{'not pushed'|i18n( 'extension/git_manager' )}</span>
                {else}
                    {foreach $commit.missing as $remote_name}<span class="a4-badge is-info">{'not on %remote'|i18n( 'extension/git_manager',, hash( '%remote', $remote_name ) )|wash}</span>{/foreach}
                {/if}
                {if eq( $commit.hash, $head_commit )}<span class="a4-badge is-ok">{'HEAD'|i18n( 'extension/git_manager' )}</span>{/if}
                <span class="a4-gm-hash"><a class="a4-code" href={concat( 'git_manager/commit_details/', $commit.hash )|ezurl}>{$commit.hash|shorten( 10, '' )|wash}</a><button type="button" class="a4-btn a4-btn-ghost a4-btn-small a4-gm-copy" data-copy="{$commit.hash|wash}" title="{'Copy the full hash'|i18n( 'extension/git_manager' )|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>
            </div>
        </li>
    {/foreach}
    </ul>
    <p class="a4-empty" id="gm-log-none" hidden="hidden">{'No commit on this page matches.'|i18n( 'extension/git_manager' )}</p>
    {else}
    <p class="a4-empty">{'No commits match the filter.'|i18n( 'extension/git_manager' )}</p>
    {/if}

    {* The page size (10, 25, 50, 100; kept as a preference), where this page stands, and the pages: first and
       last, and the shared pager with previous, next and the page numbers. *}
    <div class="a4-listfoot a4-gm-log-foot">
        <p class="a4-sizes">
            <span>{'Per page'|i18n( 'extension/git_manager' )}:</span>
        {foreach $log_sizes as $size}
            {if $size.current}
            <span class="current" aria-current="true">{$size.size}</span>
            {else}
            <a href={$size.url|ezurl} title="{'Show %count commits per page.'|i18n( 'extension/git_manager',, hash( '%count', $size.size ) )|wash}">{$size.size}</a>
            {/if}
        {/foreach}
        </p>
        {if $log_paging.total}<p class="a4-meta a4-gm-log-range" aria-live="polite">{'Showing %from–%to of %total commits'|i18n( 'extension/git_manager',, hash( '%from', $log_paging.from, '%to', $log_paging.to, '%total', $log_paging.total ) )}</p>{/if}
        {if gt( $log_paging.page_count, 1 )}
        <div class="a4-gm-log-pages">
            {if $log_links.first}<a class="a4-btn a4-btn-small" href={$log_links.first|ezurl}>{'First'|i18n( 'extension/git_manager' )}</a>{else}<span class="a4-btn a4-btn-small is-disabled" aria-disabled="true">{'First'|i18n( 'extension/git_manager' )}</span>{/if}
            <div class="a4-pager">
            {include name=navigator
                     uri='design:navigator/google.tpl'
                     page_uri='/git_manager/dashboard'
                     item_count=$log_paging.total
                     view_parameters=$log_navigator_parameters
                     item_limit=$log_paging.limit}
            </div>
            {if $log_links.last}<a class="a4-btn a4-btn-small" href={$log_links.last|ezurl}>{'Last'|i18n( 'extension/git_manager' )}</a>{else}<span class="a4-btn a4-btn-small is-disabled" aria-disabled="true">{'Last'|i18n( 'extension/git_manager' )}</span>{/if}
        </div>
        {/if}
    </div>
    </div>
</details>
</section>

</div></div></div>
</div>

{include uri='design:git_manager/parts/script.tpl'}
{undef $local_branches $remote_branches $head_branch $head_commit $detached}
