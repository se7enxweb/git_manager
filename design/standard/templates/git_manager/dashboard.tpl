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

            <form class="gm-card" action={'git_manager/dashboard'|ezurl} method="post" data-gm-confirm="{'Update the submodules to the commits this branch records?'|i18n( 'extension/git_manager' )|wash}">
                <h2>{'Submodules'|i18n( 'extension/git_manager' )}</h2>
                <p class="gm-muted">{'Initialises and updates every submodule, recursively, to the commit the checked out branch records.'|i18n( 'extension/git_manager' )}</p>
                <input class="button" type="submit" name="CheckoutUpdateSubmodules" value="{'Update submodules'|i18n( 'extension/git_manager' )}" />
            </form>
        </div>

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

        {if $commits|count}
        <ul class="gm-log" id="gm-log">
        {foreach $commits as $commit}
            <li{if eq( $commit.hash, $head_commit )} class="is-head"{/if} data-find="{$commit.title|wash} {$commit.author|wash} {$commit.hash|wash}">
                <span class="gm-avatar" data-name="{$commit.author|wash}" aria-hidden="true"></span>
                <div class="gm-log-main">
                    <a class="gm-log-title" href={concat( 'git_manager/commit_details/', $commit.hash )|ezurl} title="{$commit.title|wash}">{$commit.title|wash}</a>
                    <span class="gm-log-meta">{$commit.author|wash} &middot; <time class="gm-when" datetime="{$commit.date|wash}" title="{$commit.date|wash}">{$commit.date|wash}</time></span>
                </div>
                <div class="gm-log-side">
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
    </div>
</div>

{include uri='design:git_manager/parts/script.tpl'}
