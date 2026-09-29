{ezcss_require( 'git_manager.css' )}

{if $error}
<div class="message-error">
    <h2>{$error|wash}</h2>
</div>
{/if}

{if $message}
<div class="message-feedback">
    <h2>{$message|wash}</h2>
</div>
{/if}

{def $local_branches  = $git_manager.local_branches
     $remote_branches = $git_manager.remote_branches
     $head_branch     = $git_manager.current_branch
     $head_commit     = $git_manager.current_commit}

<div class="context-block gm">
    <div class="box-header">
        <h1 class="context-title">{'Git Manager'|i18n( 'extension/git_manager' )}</h1>
        <div class="header-mainline"></div>
    </div>
    <div class="box-content">

        {* Where HEAD is. *}
        <div class="gm-status">
            <div class="gm-status-branch">
                <span class="gm-muted">{'HEAD'|i18n( 'extension/git_manager' )}</span>
                <span class="gm-branch{if or( $head_branch|contains( 'detached' ), $head_branch|contains( 'HEAD' ) )} is-detached{/if}">{$head_branch|wash}</span>
                <span class="gm-hash"><a class="gm-mono" href={concat( 'git_manager/commit_details/', $head_commit )|ezurl} title="{$head_commit|wash}">{$head_commit|shorten( 10, '' )|wash}</a><button type="button" class="gm-copy" data-copy="{$head_commit|wash}" title="{'Copy the full hash'|i18n( 'extension/git_manager' )|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>
            </div>
            <div class="gm-status-facts">
                <span><strong>{$local_branches|count}</strong> {'local branches'|i18n( 'extension/git_manager' )}</span>
                <span><strong>{$remote_branches|count}</strong> {'remote branches'|i18n( 'extension/git_manager' )}</span>
                {if $commits|count}<span>{'Last commit'|i18n( 'extension/git_manager' )} <time class="gm-when" datetime="{$commits[0].date|wash}" title="{$commits[0].date|wash}">{$commits[0].date|wash}</time></span>{/if}
            </div>
        </div>

        {* The actions: the same forms and names as always, laid out as cards. *}
        <div class="gm-actions">
            <form class="gm-card" action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Check out the local branch chosen here and pull it from origin? The installation then runs that branch\'s code.'|i18n( 'extension/git_manager' )|wash}">
                <h2>{'Local branch'|i18n( 'extension/git_manager' )}</h2>
                <select name="branch" required="required" aria-label="{'Local branch'|i18n( 'extension/git_manager' )|wash}">
                    <option value="">{'- Select -'|i18n( 'extension/git_manager' )}</option>
                    {foreach $local_branches as $branch}
                    <option value="{$branch|wash}">{$branch|wash}{if eq( $branch, $head_branch )} ({'current'|i18n( 'extension/git_manager' )}){/if}</option>
                    {/foreach}
                </select>
                <div class="gm-row">
                    <label class="gm-check"><input type="checkbox" name="regenerate" value="regenerate" /> {'Regenerate autoloads'|i18n( 'extension/git_manager' )}</label>
                    <input class="defaultbutton" type="submit" name="CheckoutLocalBranch" value="{'Checkout'|i18n( 'extension/git_manager' )}" />
                </div>
            </form>

            <form class="gm-card" action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Check out the remote branch chosen here and pull it from origin? The installation then runs that branch\'s code.'|i18n( 'extension/git_manager' )|wash}">
                <h2>{'Remote branch'|i18n( 'extension/git_manager' )}</h2>
                <select name="branch" required="required" aria-label="{'Remote branch'|i18n( 'extension/git_manager' )|wash}">
                    <option value="">{'- Select -'|i18n( 'extension/git_manager' )}</option>
                    {foreach $remote_branches as $branch}
                    <option value="{$branch|wash}">{$branch|wash}</option>
                    {/foreach}
                </select>
                <div class="gm-row">
                    <label class="gm-check"><input type="checkbox" name="regenerate" value="regenerate" /> {'Regenerate autoloads'|i18n( 'extension/git_manager' )}</label>
                    <input class="defaultbutton" type="submit" name="CheckoutRemoteBranch" value="{'Checkout'|i18n( 'extension/git_manager' )}" />
                </div>
            </form>

        </div>

        {* Push: each remote, how the checked out branch stands against it, fetch and push. *}
        <section class="gm-push">
            <h2>{'Remotes and push'|i18n( 'extension/git_manager' )}</h2>
            <p class="gm-muted">{'A push is never forced: when the remote has commits this branch does not, it is refused and the output says so. Fetch first to see where a remote stands.'|i18n( 'extension/git_manager' )}</p>
            {if $remotes|count}
            <ul class="gm-remotes">
            {foreach $remotes as $remote}
                <li class="gm-remote">
                    <div class="gm-remote-name">
                        <strong>{$remote.name|wash}</strong>
                        <code class="gm-remote-url">{$remote.url|wash}</code>
                    </div>
                    <div class="gm-remote-state">
                        {if $remote.state}
                            {if and( eq( $remote.state.ahead, 0 ), eq( $remote.state.behind, 0 ) )}
                                <span class="gm-pill is-even">{'%branch is up to date'|i18n( 'extension/git_manager',, hash( '%branch', $head_branch ) )|wash}</span>
                            {else}
                                {if gt( $remote.state.ahead, 0 )}<span class="gm-pill is-ahead">{'%count to push'|i18n( 'extension/git_manager',, hash( '%count', $remote.state.ahead ) )}</span>{/if}
                                {if gt( $remote.state.behind, 0 )}<span class="gm-pill is-behind">{'%count behind'|i18n( 'extension/git_manager',, hash( '%count', $remote.state.behind ) )}</span>{/if}
                            {/if}
                        {else}
                            <span class="gm-pill is-new">{'%branch is not on this remote yet'|i18n( 'extension/git_manager',, hash( '%branch', $head_branch ) )|wash}</span>
                        {/if}
                    </div>
                    <div class="gm-remote-actions">
                        <form action={'git_manager/dashboard'|ezurl} method="post">
                            <input type="hidden" name="remote" value="{$remote.name|wash}" />
                            <input class="button" type="submit" name="FetchRemote" value="{'Fetch'|i18n( 'extension/git_manager' )}" />
                        </form>
                        {if $can_push}
                        <form action={'git_manager/dashboard'|ezurl} method="post" class="gm-push-form"
                              data-gm-push="{'Push %branch to %remote (%url)? The commits go out to everyone who uses that remote.'|i18n( 'extension/git_manager' )|wash}"
                              data-remote="{$remote.name|wash}" data-url="{$remote.url|wash}"
                              {if $remote.state}data-ahead="{$remote.state.ahead}" data-behind="{$remote.state.behind}"{/if}>
                            <input type="hidden" name="remote" value="{$remote.name|wash}" />
                            <select name="branch" aria-label="{'Branch to push'|i18n( 'extension/git_manager' )|wash}">
                                {foreach $local_branches as $branch}
                                <option value="{$branch|wash}"{if eq( $branch, $head_branch )} selected="selected"{/if}>{$branch|wash}</option>
                                {/foreach}
                            </select>
                            <input class="defaultbutton" type="submit" name="PushBranch" value="{'Push'|i18n( 'extension/git_manager' )}" />
                        </form>
                        {/if}
                    </div>
                    {if $can_manage_remotes}
                    <details class="gm-remote-edit">
                        <summary>{'Edit'|i18n( 'extension/git_manager' )}</summary>
                        <form action={'git_manager/dashboard'|ezurl} method="post" class="gm-remote-form">
                            <input type="hidden" name="remote" value="{$remote.name|wash}" />
                            <label>{'Name'|i18n( 'extension/git_manager' )}
                                <input type="text" name="new_name" value="{$remote.name|wash}" required="required" pattern="[A-Za-z0-9_][A-Za-z0-9._\-]*" /></label>
                            <label class="gm-remote-form-url">{'Address'|i18n( 'extension/git_manager' )}
                                <input type="text" name="url" value="{$remote.url|wash}" required="required" spellcheck="false" /></label>
                            <input class="defaultbutton" type="submit" name="UpdateRemote" value="{'Save'|i18n( 'extension/git_manager' )}" />
                            <input class="button gm-danger" type="submit" name="RemoveRemote" value="{'Remove'|i18n( 'extension/git_manager' )}" formnovalidate="formnovalidate"
                                   data-gm-confirm-button="{'Remove the remote %remote? Its remote-tracking branches go with it; the commits stay.'|i18n( 'extension/git_manager',, hash( '%remote', $remote.name ) )|wash}" />
                        </form>
                        <p class="gm-muted">{'An address shown without its user name, password or token is left as it is when saved unchanged.'|i18n( 'extension/git_manager' )}</p>
                    </details>
                    {/if}
                </li>
            {/foreach}
            </ul>
            {if $can_push|not}<p class="gm-muted">{'Pushing needs the git_manager/push policy.'|i18n( 'extension/git_manager' )}</p>{/if}
            {else}
            <p class="gm-muted">{'This repository has no remotes.'|i18n( 'extension/git_manager' )}</p>
            {/if}
            {if $can_manage_remotes}
            <details class="gm-remote-add"{if $remotes|count|not} open="open"{/if}>
                <summary>{'Add a remote'|i18n( 'extension/git_manager' )}</summary>
                <form action={'git_manager/dashboard'|ezurl} method="post" class="gm-remote-form">
                    <label>{'Name'|i18n( 'extension/git_manager' )}
                        <input type="text" name="remote" required="required" placeholder="upstream" pattern="[A-Za-z0-9_][A-Za-z0-9._\-]*" /></label>
                    <label class="gm-remote-form-url">{'Address'|i18n( 'extension/git_manager' )}
                        <input type="text" name="url" required="required" spellcheck="false" placeholder="https://github.com/owner/repository.git" /></label>
                    <input class="defaultbutton" type="submit" name="AddRemote" value="{'Add'|i18n( 'extension/git_manager' )}" />
                </form>
                <p class="gm-muted">{'https://, ssh://, git:// or file:// addresses, user@host:path, or an absolute path.'|i18n( 'extension/git_manager' )}</p>
            </details>
            {else}
            <p class="gm-muted">{'Adding, changing and removing remotes needs the git_manager/remotes policy.'|i18n( 'extension/git_manager' )}</p>
            {/if}
        </section>

        {* Submodules: the list, and adding, changing, updating and removing them. *}
        <section class="gm-push gm-submodules">
            <div class="gm-section-head">
                <h2>{'Submodules'|i18n( 'extension/git_manager' )} <span class="gm-muted">({$submodules|count})</span></h2>
                {if $submodules|count}
                <form action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Update the submodules to the commits this branch records?'|i18n( 'extension/git_manager' )|wash}">
                    <input class="button" type="submit" name="CheckoutUpdateSubmodules" value="{'Update all submodules'|i18n( 'extension/git_manager' )}" />
                </form>
                {/if}
            </div>
            <p class="gm-muted">{'Adding, changing and removing a submodule changes the working tree and stages the change; it is kept once it is committed.'|i18n( 'extension/git_manager' )}</p>
            {if $submodules|count}
            <ul class="gm-remotes">
            {foreach $submodules as $sub}
                <li class="gm-remote">
                    <div class="gm-remote-name">
                        <strong>{$sub.path|wash}</strong>
                        <code class="gm-remote-url">{$sub.url|wash}</code>
                    </div>
                    <div class="gm-remote-state">
                        {if $sub.branch}<span class="gm-pill is-even" title="{'Follows this branch'|i18n( 'extension/git_manager' )|wash}">{$sub.branch|wash}</span>{/if}
                        {if eq( $sub.state, 'current' )}<span class="gm-pill is-even">{'up to date'|i18n( 'extension/git_manager' )}</span>
                        {elseif eq( $sub.state, 'not_initialized' )}<span class="gm-pill is-new">{'not checked out'|i18n( 'extension/git_manager' )}</span>
                        {elseif eq( $sub.state, 'changed' )}<span class="gm-pill is-behind" title="{'The checked out commit is not the one this branch records'|i18n( 'extension/git_manager' )|wash}">{'other commit'|i18n( 'extension/git_manager' )}</span>
                        {elseif eq( $sub.state, 'conflict' )}<span class="gm-pill is-local">{'conflict'|i18n( 'extension/git_manager' )}</span>
                        {else}<span class="gm-pill is-local">{'not in the index'|i18n( 'extension/git_manager' )}</span>{/if}
                        {if $sub.commit}<span class="gm-hash"><span class="gm-mono">{$sub.commit|shorten( 10, '' )|wash}</span><button type="button" class="gm-copy" data-copy="{$sub.commit|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>{/if}
                    </div>
                    <div class="gm-remote-actions">
                        <form action={'git_manager/dashboard'|ezurl} method="post">
                            <input type="hidden" name="submodule" value="{$sub.name|wash}" />
                            <input class="button" type="submit" name="UpdateSubmodule" value="{'Update'|i18n( 'extension/git_manager' )}" />
                        </form>
                    </div>
                    {if $can_manage_submodules}
                    <details class="gm-remote-edit">
                        <summary>{'Edit'|i18n( 'extension/git_manager' )}</summary>
                        <form action={'git_manager/dashboard'|ezurl} method="post" class="gm-remote-form">
                            <input type="hidden" name="submodule" value="{$sub.name|wash}" />
                            <label class="gm-remote-form-url">{'Address'|i18n( 'extension/git_manager' )}
                                <input type="text" name="url" value="{$sub.url|wash}" required="required" spellcheck="false" /></label>
                            <label>{'Branch'|i18n( 'extension/git_manager' )}
                                <input type="text" name="submodule_branch" value="{$sub.branch|wash}" placeholder="{'none'|i18n( 'extension/git_manager' )|wash}" /></label>
                            <input class="defaultbutton" type="submit" name="EditSubmodule" value="{'Save'|i18n( 'extension/git_manager' )}" />
                            <input class="button gm-danger" type="submit" name="RemoveSubmodule" value="{'Remove'|i18n( 'extension/git_manager' )}" formnovalidate="formnovalidate"
                                   data-gm-confirm-button="{'Remove the submodule %name? Its files leave the working tree and the removal is staged for a commit.'|i18n( 'extension/git_manager',, hash( '%name', $sub.path ) )|wash}" />
                        </form>
                        <p class="gm-muted">{'Name in .gitmodules'|i18n( 'extension/git_manager' )}: <code>{$sub.name|wash}</code></p>
                    </details>
                    {/if}
                </li>
            {/foreach}
            </ul>
            {else}
            <p class="gm-muted">{'This repository has no submodules.'|i18n( 'extension/git_manager' )}</p>
            {/if}
            {if $can_manage_submodules}
            {* Closed unless this user opened it, or the add just failed
               validation, so the error is never hidden. Kept as the user
               preference admin_git_manager_add_submodule; unset means closed. *}
            <details class="gm-remote-add" id="gm-add-submodule-card"{if or( eq( ezpreference( 'admin_git_manager_add_submodule' ), '1' ), $submodule_add_failed )} open="open"{/if}
                     data-preference-url={'/user/preferences/set_and_exit/admin_git_manager_add_submodule'|ezurl}>
                <summary>{'Add a submodule'|i18n( 'extension/git_manager' )}</summary>
                <form action={'git_manager/dashboard'|ezurl} method="post" class="gm-remote-form"
                      data-gm-confirm="{'Clone the repository into this path and stage it as a submodule?'|i18n( 'extension/git_manager' )|wash}">
                    <label class="gm-remote-form-url">{'Address'|i18n( 'extension/git_manager' )}
                        <input type="text" name="url" required="required" spellcheck="false" placeholder="https://github.com/owner/repository.git" /></label>
                    <label>{'Path'|i18n( 'extension/git_manager' )}
                        <input type="text" name="path" required="required" placeholder="extension/example" /></label>
                    <label>{'Branch'|i18n( 'extension/git_manager' )}
                        <input type="text" name="submodule_branch" placeholder="{'optional'|i18n( 'extension/git_manager' )|wash}" /></label>
                    <input class="defaultbutton" type="submit" name="AddSubmodule" value="{'Add'|i18n( 'extension/git_manager' )}" />
                </form>
                <p class="gm-muted">{'The path is relative to the installation, and must not exist yet. The address may also be relative to this repository\'s remote: ../name.git.'|i18n( 'extension/git_manager' )}</p>
            </details>
            {else}
            <p class="gm-muted">{'Adding, changing and removing submodules needs the git_manager/submodules policy.'|i18n( 'extension/git_manager' )}</p>
            {/if}
        </section>

        {if $output}
        <div class="gm-output">
            <div class="gm-output-head">
                <span>{'Output'|i18n( 'extension/git_manager' )}</span>
                <button type="button" class="gm-copy" data-copy-from="gm-output-text">{'Copy'|i18n( 'extension/git_manager' )}</button>
            </div>
            <pre id="gm-output-text">{$output|wash}</pre>
        </div>
        {/if}
    </div>
</div>

<div class="context-block gm">
    <div class="box-header">
        <h1 class="context-title">{'Commits log'|i18n( 'extension/git_manager' )} <span class="gm-muted">({$commits|count})</span></h1>
        <div class="header-mainline"></div>
    </div>
    <div class="box-content">
        {* Closed unless this user opened it; kept as the user preference
           admin_git_manager_commit_log. The summary says what is waiting to
           be pushed, open or closed. *}
        <details class="gm-log-card" id="gm-log-card"{if eq( ezpreference( 'admin_git_manager_commit_log' ), '1' )} open="open"{/if}
                 data-preference-url={'/user/preferences/set_and_exit/admin_git_manager_commit_log'|ezurl}>
            <summary class="gm-log-summary">
                <span class="gm-log-summary-title">{'The latest %count commits'|i18n( 'extension/git_manager',, hash( '%count', $commits|count ) )}</span>
                {if $local_only_count}<span class="gm-pill is-local">{'%count not pushed anywhere'|i18n( 'extension/git_manager',, hash( '%count', $local_only_count ) )}</span>{/if}
                {foreach $unpushed_counts as $remote_name => $count}{if $count}<span class="gm-pill is-ahead">{'%count not on %remote'|i18n( 'extension/git_manager',, hash( '%count', $count, '%remote', $remote_name ) )|wash}</span>{/if}{/foreach}
                {if and( $remotes|count, eq( $unpushed_total, 0 ) )}<span class="gm-pill is-even">{'Everything here is on every remote'|i18n( 'extension/git_manager' )}</span>{/if}
            </summary>

            <form class="gm-filter" id="gm-filter" action={'git_manager/dashboard'|ezurl} method="post">
                <label>{'Author'|i18n( 'extension/git_manager' )}
                    <input type="text" name="filter[author]" value="{$filter.author|wash}" placeholder="{'Name or e-mail'|i18n( 'extension/git_manager' )|wash}" /></label>
                <label>{'Start date'|i18n( 'extension/git_manager' )}
                    <input type="date" name="filter[start_date]" value="{$filter.start_date|wash}" /></label>
                <label>{'End date'|i18n( 'extension/git_manager' )}
                    <input type="date" name="filter[end_date]" value="{$filter.end_date|wash}" /></label>
                <input class="button" type="submit" name="SetCommitsFilter" value="{'Filter'|i18n( 'extension/git_manager' )}" />
                {if or( $filter.author, $filter.start_date, $filter.end_date )}
                <button class="button" type="submit" name="SetCommitsFilter" value="1" data-gm-clear="1">{'Clear'|i18n( 'extension/git_manager' )}</button>
                {/if}
                <label class="gm-filter-quick">{'Find on this page'|i18n( 'extension/git_manager' )}
                    <input type="search" id="gm-log-find" placeholder="{'Title, author or hash'|i18n( 'extension/git_manager' )|wash}" /></label>
            </form>

            {if $remotes|count}
            <div class="gm-legend">
                <span><i class="gm-swatch is-local"></i>{'not pushed to any remote'|i18n( 'extension/git_manager' )}</span>
                <span><i class="gm-swatch is-partial"></i>{'missing on some remotes'|i18n( 'extension/git_manager' )}</span>
                <span><i class="gm-swatch is-head"></i>{'checked out (HEAD)'|i18n( 'extension/git_manager' )}</span>
                <label class="gm-check"><input type="checkbox" id="gm-only-unpushed" /> {'Only commits to push'|i18n( 'extension/git_manager' )}</label>
            </div>
            {/if}

            {if $commits|count}
            <ul class="gm-log" id="gm-log">
            {foreach $commits as $commit}
                <li class="{if eq( $commit.hash, $head_commit )}is-head {/if}{if $commit.local_only}is-local{elseif $commit.missing|count}is-partial{/if}"
                    data-find="{$commit.title|wash} {$commit.author|wash} {$commit.hash|wash}"{if $commit.missing|count} data-unpushed="1"{/if}>
                    <span class="gm-avatar" data-name="{$commit.author|wash}" aria-hidden="true"></span>
                    <div class="gm-log-main">
                        <a class="gm-log-title" href={concat( 'git_manager/commit_details/', $commit.hash )|ezurl} title="{$commit.title|wash}">{$commit.title|wash}</a>
                        <span class="gm-log-meta">{$commit.author|wash} &middot; <time class="gm-when" datetime="{$commit.date|wash}" title="{$commit.date|wash}">{$commit.date|wash}</time></span>
                    </div>
                    <div class="gm-log-side">
                        {if $commit.local_only}
                            <span class="gm-pill is-local" title="{'No remote has this commit yet'|i18n( 'extension/git_manager' )|wash}">{'not pushed'|i18n( 'extension/git_manager' )}</span>
                        {else}
                            {foreach $commit.missing as $remote_name}<span class="gm-pill is-ahead">{'not on %remote'|i18n( 'extension/git_manager',, hash( '%remote', $remote_name ) )|wash}</span>{/foreach}
                        {/if}
                        {if eq( $commit.hash, $head_commit )}<span class="gm-tag-head">{'HEAD'|i18n( 'extension/git_manager' )}</span>{/if}
                        <span class="gm-hash"><a class="gm-mono" href={concat( 'git_manager/commit_details/', $commit.hash )|ezurl}>{$commit.hash|shorten( 10, '' )|wash}</a><button type="button" class="gm-copy" data-copy="{$commit.hash|wash}" title="{'Copy the full hash'|i18n( 'extension/git_manager' )|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>
                    </div>
                </li>
            {/foreach}
            </ul>
            <p class="gm-empty" id="gm-log-none" hidden="hidden">{'No commit on this page matches.'|i18n( 'extension/git_manager' )}</p>
            {else}
            <p class="gm-empty">{'No commits match the filter.'|i18n( 'extension/git_manager' )}</p>
            {/if}
        </details>
    </div>
</div>

{include uri='design:git_manager/parts/script.tpl'}
