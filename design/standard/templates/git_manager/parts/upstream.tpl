{* The Upstream card: where the installation stands against its branch on the remote, the commits missing either way,
   what is not committed, Fetch now. $upstream from GitManagerUpstream::status(), $upstream_fetch the result of the
   last "Fetch now" (shown once) or false. An .a4-card whose edge and badge say the state (.a4-gm-upstream.is-<state>
   is kept for overrides and scripts). *}
{def $u_state = $upstream.state
     $u_tone = ''}
{switch match=$u_state}
    {case match='current'}{set $u_tone = 'is-ok'}{/case}
    {case match='behind'}{set $u_tone = 'is-warn'}{/case}
    {case match='ahead'}{set $u_tone = 'is-info'}{/case}
    {case match='diverged'}{set $u_tone = 'is-bad'}{/case}
    {case}{set $u_tone = ''}{/case}
{/switch}
<section class="a4-card {cond( eq( $u_tone, 'is-warn' ), 'is-attention', $u_tone )} a4-gm-upstream is-{$u_state|wash}" id="gm-upstream" aria-labelledby="gm-upstream-title">
    <div class="a4-card-head">
        <h2 class="a4-h3" id="gm-upstream-title">{'Upstream (%remote)'|i18n( 'extension/git_manager',, hash( '%remote', $upstream.remote ) )|wash}</h2>
        <span class="a4-badge {$u_tone}" id="gm-upstream-badge" data-state="{$u_state|wash}">
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
        {if $upstream.remote_exists}
        <div class="a4-card-actions">
            <form action={'git_manager/dashboard'|ezurl} method="post" class="a4-gm-upstream-fetch" data-gm-busy="{'Fetching...'|i18n( 'extension/git_manager' )|wash}">
                <input class="a4-btn a4-btn-primary" type="submit" name="FetchUpstream" value="{'Fetch now'|i18n( 'extension/git_manager' )}"
                       title="{'git fetch --prune %remote: updates what the page knows of the remote, never the installation\'s files'|i18n( 'extension/git_manager',, hash( '%remote', $upstream.remote ) )|wash}" />
            </form>
        </div>
        {/if}
    </div>

    <div class="a4-card-body">
    <dl class="a4-facts a4-gm-upstream-facts">
        <div><dt>{'Remote'|i18n( 'extension/git_manager' )}</dt>
            <dd><code class="a4-code" title="{$upstream.url|wash}">{if $upstream.url}{$upstream.url|wash}{else}-{/if}</code>
                {if $upstream.web_url} <a href="{$upstream.web_url|wash}" target="_blank" rel="noopener noreferrer">{'Open'|i18n( 'extension/git_manager' )}</a>{/if}</dd></div>
        <div><dt>{'Compared with'|i18n( 'extension/git_manager' )}</dt>
            <dd><span class="a4-code" id="gm-upstream-tracking">{$upstream.tracking|wash}</span>
                {if $upstream.detached}<span class="a4-meta">{'(HEAD is detached)'|i18n( 'extension/git_manager' )}</span>
                {elseif $upstream.tracking_set|not}<span class="a4-meta" title="{'git branch --set-upstream-to would make it the branch\'s upstream'|i18n( 'extension/git_manager' )|wash}">{'(same name; no upstream is set for %branch)'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.branch ) )|wash}</span>{/if}
                {if $upstream.remote_head}<span class="a4-gm-hash">{if $upstream.remote_url}<a class="a4-code" href="{$upstream.remote_url|wash}" target="_blank" rel="noopener noreferrer" title="{$upstream.remote_subject|wash}">{$upstream.remote_head|shorten( 10, '' )|wash}</a>{else}<span class="a4-code" title="{$upstream.remote_subject|wash}">{$upstream.remote_head|shorten( 10, '' )|wash}</span>{/if}</span>{/if}</dd></div>
        <div><dt>{'Last fetch'|i18n( 'extension/git_manager' )}</dt>
            <dd>{if $upstream.fetched}<time class="a4-gm-when" id="gm-upstream-fetched" datetime="{$upstream.fetched_iso|wash}" title="{$upstream.fetched|l10n( 'shortdatetime' )|wash}">{$upstream.fetched|l10n( 'shortdatetime' )|wash}</time>
                {else}<span class="a4-meta">{'never'|i18n( 'extension/git_manager' )}</span>{/if}</dd></div>
        <div><dt>{'Ahead / behind'|i18n( 'extension/git_manager' )}</dt>
            <dd><strong id="gm-upstream-ahead">{$upstream.ahead}</strong> / <strong id="gm-upstream-behind">{$upstream.behind}</strong>
                <span class="a4-meta">{'local commits / commits to get'|i18n( 'extension/git_manager' )}</span></dd></div>
        <div><dt>{'Not committed'|i18n( 'extension/git_manager' )}</dt>
            <dd id="gm-upstream-worktree">
                {if $upstream.worktree.total|eq( 0 )}<span class="a4-meta">{'nothing, the working tree is clean'|i18n( 'extension/git_manager' )}</span>
                {else}
                <span><strong>{$upstream.worktree.modified}</strong> {'modified'|i18n( 'extension/git_manager' )}{if $upstream.worktree.staged} ({'%count staged'|i18n( 'extension/git_manager',, hash( '%count', $upstream.worktree.staged ) )}){/if},</span>
                <span><strong>{$upstream.worktree.untracked}</strong> {'untracked'|i18n( 'extension/git_manager' )}</span>
                {if $upstream.worktree.conflicts}<span class="a4-badge is-bad"><strong>{$upstream.worktree.conflicts}</strong> {'in conflict'|i18n( 'extension/git_manager' )}</span>{/if}
                {/if}</dd></div>
    </dl>

    {if $upstream_fetch}
    <div class="a4-notice {if $upstream_fetch.exit|eq( 0 )}is-ok{else}is-bad{/if} a4-gm-upstream-result" id="gm-upstream-result" role="{if $upstream_fetch.exit|eq( 0 )}status{else}alert{/if}">
        <p><strong>{if $upstream_fetch.exit|eq( 0 )}{'Fetched %remote in %seconds s.'|i18n( 'extension/git_manager',, hash( '%remote', $upstream_fetch.remote, '%seconds', $upstream_fetch.seconds ) )|wash}
                {else}{'Fetching %remote failed (git exit %exit).'|i18n( 'extension/git_manager',, hash( '%remote', $upstream_fetch.remote, '%exit', $upstream_fetch.exit ) )|wash}{/if}</strong>
        {if $upstream_fetch.via} {'As %user, over %via.'|i18n( 'extension/git_manager',, hash( '%user', $upstream_fetch.user, '%via', $upstream_fetch.via|upcase ) )|wash}{/if}</p>
        {if $upstream_fetch.output}<pre class="a4-gm-pre" tabindex="0">{$upstream_fetch.output|wash}</pre>{/if}
    </div>
    {/if}

    {if $upstream.error}<div class="a4-notice is-bad a4-gm-upstream-error" role="alert"><p>{$upstream.error|wash}</p></div>{/if}

    <div class="a4-grid is-wide a4-gm-upstream-lists">
        <div class="a4-gm-upstream-list" id="gm-upstream-missing">
            <h3 class="a4-h3" id="gm-upstream-missing-title">{'Missing in this installation'|i18n( 'extension/git_manager' )} <span class="a4-badge{if $upstream.behind} is-warn{/if}">{$upstream.behind}</span></h3>
            {if $upstream.behind_commits|count}
            <ol class="a4-gm-commits" tabindex="0" aria-labelledby="gm-upstream-missing-title">
            {foreach $upstream.behind_commits as $c}
                <li>
                    {if $c.url}<a class="a4-code a4-gm-uhash" href="{$c.url|wash}" target="_blank" rel="noopener noreferrer">{$c.short|wash}</a>{else}<span class="a4-code a4-gm-uhash">{$c.short|wash}</span>{/if}
                    <span class="a4-gm-usubject" title="{$c.subject|wash}">{$c.subject|wash}</span>
                    <span class="a4-meta">{$c.author|wash} &middot; <time class="a4-gm-when" datetime="{$c.date|wash}" title="{$c.date|wash}">{$c.date|wash}</time></span>
                </li>
            {/foreach}
            </ol>
            {if gt( $upstream.behind, $upstream.behind_commits|count )}<p class="a4-help">{'The newest %shown of %count.'|i18n( 'extension/git_manager',, hash( '%shown', $upstream.behind_commits|count, '%count', $upstream.behind ) )}</p>{/if}
            {else}
            <p class="a4-empty">{if $upstream.tracking_exists}{'None: everything on %branch is here.'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.tracking ) )|wash}{else}{'Fetch to see what the remote has.'|i18n( 'extension/git_manager' )}{/if}</p>
            {/if}
        </div>
        <div class="a4-gm-upstream-list" id="gm-upstream-local">
            <h3 class="a4-h3" id="gm-upstream-local-title">{'Only in this installation'|i18n( 'extension/git_manager' )} <span class="a4-badge{if $upstream.ahead} is-info{/if}">{$upstream.ahead}</span></h3>
            {if $upstream.ahead_commits|count}
            <ol class="a4-gm-commits" tabindex="0" aria-labelledby="gm-upstream-local-title">
            {foreach $upstream.ahead_commits as $c}
                <li>
                    <a class="a4-code a4-gm-uhash" href={concat( 'git_manager/commit_details/', $c.hash )|ezurl}>{$c.short|wash}</a>
                    <span class="a4-gm-usubject" title="{$c.subject|wash}">{$c.subject|wash}</span>
                    <span class="a4-meta">{$c.author|wash} &middot; <time class="a4-gm-when" datetime="{$c.date|wash}" title="{$c.date|wash}">{$c.date|wash}</time></span>
                </li>
            {/foreach}
            </ol>
            {if gt( $upstream.ahead, $upstream.ahead_commits|count )}<p class="a4-help">{'The newest %shown of %count.'|i18n( 'extension/git_manager',, hash( '%shown', $upstream.ahead_commits|count, '%count', $upstream.ahead ) )}</p>{/if}
            {else}
            <p class="a4-empty">{'None: every commit here is on %branch.'|i18n( 'extension/git_manager',, hash( '%branch', $upstream.tracking ) )|wash}</p>
            {/if}
        </div>
    </div>
    <p class="a4-help a4-gm-upstream-foot">{'Counted from what the last fetch brought; Fetch now to see the remote as it is. Status of %time.'|i18n( 'extension/git_manager',, hash( '%time', $upstream.computed|l10n( 'shortdatetime' ) ) )|wash}</p>
    </div>
</section>
{undef $u_state $u_tone}
