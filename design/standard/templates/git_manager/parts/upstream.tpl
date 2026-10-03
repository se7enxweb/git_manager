{* The Upstream card: where the installation stands against its branch on the
   remote, the commits missing either way, what is not committed, Fetch now.
   $upstream from GitManagerUpstream::status(), $upstream_fetch the result of
   the last "Fetch now" (shown once) or false. *}
{def $u_state = $upstream.state
     $u_badge = 'is-even'}
{switch match=$u_state}
    {case match='current'}{set $u_badge = 'is-current'}{/case}
    {case match='behind'}{set $u_badge = 'is-behind'}{/case}
    {case match='ahead'}{set $u_badge = 'is-ahead'}{/case}
    {case match='diverged'}{set $u_badge = 'is-diverged'}{/case}
    {case}{set $u_badge = 'is-new'}{/case}
{/switch}
<section class="gm-upstream is-{$u_state|wash}" id="gm-upstream" aria-labelledby="gm-upstream-title">
    <div class="gm-upstream-head">
        <div class="gm-upstream-title">
            <h2 id="gm-upstream-title">{'Upstream (%remote)'|i18n( 'extension/git_manager',, hash( '%remote', $upstream.remote ) )|wash}</h2>
            <span class="gm-badge {$u_badge}" id="gm-upstream-badge" data-state="{$u_state|wash}">
            {switch match=$u_state}
                {case match='current'}{'Up to date'|i18n( 'extension/git_manager' )}{/case}
                {case match='behind'}{'Behind by %count'|i18n( 'extension/git_manager',, hash( '%count', $upstream.behind ) )}{/case}
                {case match='ahead'}{'Ahead by %count'|i18n( 'extension/git_manager',, hash( '%count', $upstream.ahead ) )}{/case}
                {case match='diverged'}{'Diverged: %ahead ahead, %behind behind'|i18n( 'extension/git_manager',, hash( '%ahead', $upstream.ahead, '%behind', $upstream.behind ) )}{/case}
                {case match='no_remote'}{'No remote %remote'|i18n( 'extension/git_manager',, hash( '%remote', $upstream.remote ) )|wash}{/case}
                {case match='not_fetched'}{'%branch not fetched yet'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.tracking ) )|wash}{/case}
                {case}{'Unknown'|i18n( 'extension/git_manager' )}{/case}
            {/switch}
            </span>
        </div>
        {if $upstream.remote_exists}
        <form action={'git_manager/dashboard'|ezurl} method="post" class="gm-upstream-fetch" data-gm-busy="{'Fetching...'|i18n( 'extension/git_manager' )|wash}">
            <input class="defaultbutton" type="submit" name="FetchUpstream" value="{'Fetch now'|i18n( 'extension/git_manager' )}"
                   title="{'git fetch --prune %remote: updates what the page knows of the remote, never the installation\'s files'|i18n( 'extension/git_manager',, hash( '%remote', $upstream.remote ) )|wash}" />
        </form>
        {/if}
    </div>

    <dl class="gm-upstream-facts">
        <div><dt>{'Remote'|i18n( 'extension/git_manager' )}</dt>
            <dd><code class="gm-remote-url" title="{$upstream.url|wash}">{if $upstream.url}{$upstream.url|wash}{else}-{/if}</code>
                {if $upstream.web_url}<a class="gm-ext" href="{$upstream.web_url|wash}" target="_blank" rel="noopener noreferrer">{'Open'|i18n( 'extension/git_manager' )}</a>{/if}</dd></div>
        <div><dt>{'Compared with'|i18n( 'extension/git_manager' )}</dt>
            <dd><span class="gm-mono" id="gm-upstream-tracking">{$upstream.tracking|wash}</span>
                {if $upstream.detached}<span class="gm-muted">{'(HEAD is detached)'|i18n( 'extension/git_manager' )}</span>
                {elseif $upstream.tracking_set|not}<span class="gm-muted" title="{'git branch --set-upstream-to would make it the branch\'s upstream'|i18n( 'extension/git_manager' )|wash}">{'(same name; no upstream is set for %branch)'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.branch ) )|wash}</span>{/if}
                {if $upstream.remote_head}<span class="gm-hash">{if $upstream.remote_url}<a class="gm-mono" href="{$upstream.remote_url|wash}" target="_blank" rel="noopener noreferrer" title="{$upstream.remote_subject|wash}">{$upstream.remote_head|shorten( 10, '' )|wash}</a>{else}<span class="gm-mono" title="{$upstream.remote_subject|wash}">{$upstream.remote_head|shorten( 10, '' )|wash}</span>{/if}</span>{/if}</dd></div>
        <div><dt>{'Last fetch'|i18n( 'extension/git_manager' )}</dt>
            <dd>{if $upstream.fetched}<time class="gm-when" id="gm-upstream-fetched" datetime="{$upstream.fetched_iso|wash}" title="{$upstream.fetched|l10n( 'shortdatetime' )|wash}">{$upstream.fetched|l10n( 'shortdatetime' )|wash}</time>
                {else}<span class="gm-muted">{'never'|i18n( 'extension/git_manager' )}</span>{/if}</dd></div>
        <div><dt>{'Ahead / behind'|i18n( 'extension/git_manager' )}</dt>
            <dd><strong id="gm-upstream-ahead">{$upstream.ahead}</strong> / <strong id="gm-upstream-behind">{$upstream.behind}</strong>
                <span class="gm-muted">{'local commits / commits to get'|i18n( 'extension/git_manager' )}</span></dd></div>
        <div><dt>{'Not committed'|i18n( 'extension/git_manager' )}</dt>
            <dd id="gm-upstream-worktree">
                {if $upstream.worktree.total|eq( 0 )}<span class="gm-muted">{'nothing, the working tree is clean'|i18n( 'extension/git_manager' )}</span>
                {else}
                <span><strong>{$upstream.worktree.modified}</strong> {'modified'|i18n( 'extension/git_manager' )}{if $upstream.worktree.staged} ({'%count staged'|i18n( 'extension/git_manager',, hash( '%count', $upstream.worktree.staged ) )}){/if},</span>
                <span><strong>{$upstream.worktree.untracked}</strong> {'untracked'|i18n( 'extension/git_manager' )}</span>
                {if $upstream.worktree.conflicts}<span class="gm-pill is-local"><strong>{$upstream.worktree.conflicts}</strong> {'in conflict'|i18n( 'extension/git_manager' )}</span>{/if}
                {/if}</dd></div>
    </dl>

    {if $upstream_fetch}
    <div class="gm-upstream-result {if $upstream_fetch.exit|eq( 0 )}is-ok{else}is-failed{/if}" id="gm-upstream-result" role="status">
        <strong>{if $upstream_fetch.exit|eq( 0 )}{'Fetched %remote in %seconds s.'|i18n( 'extension/git_manager',, hash( '%remote', $upstream_fetch.remote, '%seconds', $upstream_fetch.seconds ) )|wash}
                {else}{'Fetching %remote failed (git exit %exit).'|i18n( 'extension/git_manager',, hash( '%remote', $upstream_fetch.remote, '%exit', $upstream_fetch.exit ) )|wash}{/if}</strong>
        {if $upstream_fetch.via}<span class="gm-muted">{'As %user, over %via.'|i18n( 'extension/git_manager',, hash( '%user', $upstream_fetch.user, '%via', $upstream_fetch.via|upcase ) )|wash}</span>{/if}
        {if $upstream_fetch.output}<pre>{$upstream_fetch.output|wash}</pre>{/if}
    </div>
    {/if}

    {if $upstream.error}<p class="gm-upstream-error">{$upstream.error|wash}</p>{/if}

    <div class="gm-upstream-lists">
        <div class="gm-upstream-list" id="gm-upstream-missing">
            <h3>{'Missing in this installation'|i18n( 'extension/git_manager' )} <span class="gm-count">{$upstream.behind}</span></h3>
            {if $upstream.behind_commits|count}
            <ol class="gm-ucommits">
            {foreach $upstream.behind_commits as $c}
                <li>
                    {if $c.url}<a class="gm-mono gm-uhash" href="{$c.url|wash}" target="_blank" rel="noopener noreferrer">{$c.short|wash}</a>{else}<span class="gm-mono gm-uhash">{$c.short|wash}</span>{/if}
                    <span class="gm-usubject" title="{$c.subject|wash}">{$c.subject|wash}</span>
                    <span class="gm-umeta">{$c.author|wash} &middot; <time class="gm-when" datetime="{$c.date|wash}" title="{$c.date|wash}">{$c.date|wash}</time></span>
                </li>
            {/foreach}
            </ol>
            {if gt( $upstream.behind, $upstream.behind_commits|count )}<p class="gm-muted">{'The newest %shown of %count.'|i18n( 'extension/git_manager',, hash( '%shown', $upstream.behind_commits|count, '%count', $upstream.behind ) )}</p>{/if}
            {else}
            <p class="gm-empty">{if $upstream.tracking_exists}{'None: everything on %branch is here.'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.tracking ) )|wash}{else}{'Fetch to see what the remote has.'|i18n( 'extension/git_manager' )}{/if}</p>
            {/if}
        </div>
        <div class="gm-upstream-list" id="gm-upstream-local">
            <h3>{'Only in this installation'|i18n( 'extension/git_manager' )} <span class="gm-count">{$upstream.ahead}</span></h3>
            {if $upstream.ahead_commits|count}
            <ol class="gm-ucommits">
            {foreach $upstream.ahead_commits as $c}
                <li>
                    <a class="gm-mono gm-uhash" href={concat( 'git_manager/commit_details/', $c.hash )|ezurl}>{$c.short|wash}</a>
                    <span class="gm-usubject" title="{$c.subject|wash}">{$c.subject|wash}</span>
                    <span class="gm-umeta">{$c.author|wash} &middot; <time class="gm-when" datetime="{$c.date|wash}" title="{$c.date|wash}">{$c.date|wash}</time></span>
                </li>
            {/foreach}
            </ol>
            {if gt( $upstream.ahead, $upstream.ahead_commits|count )}<p class="gm-muted">{'The newest %shown of %count.'|i18n( 'extension/git_manager',, hash( '%shown', $upstream.ahead_commits|count, '%count', $upstream.ahead ) )}</p>{/if}
            {else}
            <p class="gm-empty">{'None: every commit here is on %branch.'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.tracking ) )|wash}</p>
            {/if}
        </div>
    </div>
    <p class="gm-upstream-foot gm-muted">{'Counted from what the last fetch brought; Fetch now to see the remote as it is. Status of %time.'|i18n( 'extension/git_manager',, hash( '%time', $upstream.computed|l10n( 'shortdatetime' ) ) )|wash}</p>
</section>
{undef $u_state $u_badge}
