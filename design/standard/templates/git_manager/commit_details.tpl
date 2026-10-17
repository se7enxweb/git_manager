{* git_manager/commit_details: one commit, its message, fields and the diff of each file, and "Checkout this commit".
   Shared components (.a4-card, .a4-facts, .a4-fold, .a4-btn) and design/standard/stylesheets/modules/git_manager.css
   (.a4-gm-*) for the diff. Variables: commit (parsed, or false), output, hash. *}
<div class="context-block a4-page a4-gm a4-gm-commit" {include uri='design:git_manager/parts/data.tpl'}>

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Commit details'|i18n( 'extension/git_manager' )}</h1>
<div class="header-mainline"></div>
</div></div>

<div class="box-ml"><div class="box-mr"><div class="box-content">
{if $commit}
    <section class="a4-card a4-gm-commit-head" aria-labelledby="gm-commit-subject">
        <div class="a4-card-head">
            {if $commit.message|count}<h2 class="a4-h2" id="gm-commit-subject">{$commit.message[0]|wash}</h2>{/if}
            <span class="a4-gm-hash"><span class="a4-code">{$commit.hash|wash}</span><button type="button" class="a4-btn a4-btn-ghost a4-btn-small a4-gm-copy" data-copy="{$commit.hash|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>
        </div>
        <div class="a4-card-body">
            <dl class="a4-facts is-rows">
                {foreach $commit.fields as $field}
                <div><dt>{$field.name|wash}</dt>
                <dd>{if eq( $field.name, 'Date' )}<time class="a4-gm-when" datetime="{$field.value|wash}" title="{$field.value|wash}">{$field.value|wash}</time>{else}{$field.value|wash}{/if}</dd></div>
                {/foreach}
                <div><dt>{'Changes'|i18n( 'extension/git_manager' )}</dt>
                <dd>{$commit.files|count} {'files'|i18n( 'extension/git_manager' )}, <span class="a4-ok-text">+{$commit.additions}</span> <span class="a4-bad-text">&minus;{$commit.deletions}</span></dd></div>
            </dl>
            {if gt( $commit.message|count, 1 )}
            <pre class="a4-gm-commit-body">{foreach $commit.message as $index => $line}{if gt( $index, 0 )}{$line|wash}
{/if}{/foreach}</pre>
            {/if}
        </div>
    </section>

    {if $commit.files|count}
    <div class="a4-gm-files">
    {foreach $commit.files as $file}
        <details class="a4-fold a4-gm-file"{if lt( $commit.files|count, 12 )} open="open"{/if}>
            <summary><span class="a4-code a4-gm-file-name">{$file.name|wash}</span>
                <span class="a4-gm-file-stats"><span class="a4-ok-text">+{$file.additions}</span> <span class="a4-bad-text">&minus;{$file.deletions}</span></span></summary>
            <div class="a4-gm-diff" tabindex="0" aria-label="{$file.name|wash}">{foreach $file.lines as $line}<div class="is-{$line.type}">{$line.text|wash}</div>{/foreach}</div>
            {if $file.truncated}<p class="a4-notice is-warn a4-gm-truncated">{'Only the first 500 lines of this file are shown.'|i18n( 'extension/git_manager' )}</p>{/if}
        </details>
    {/foreach}
    </div>
    {/if}
{else}
    <section class="a4-gm-output" aria-labelledby="gm-output-title">
        <div class="a4-gm-output-head"><h2 class="a4-h3" id="gm-output-title">{'Output'|i18n( 'extension/git_manager' )}</h2></div>
        <pre tabindex="0">{$output|wash}</pre>
    </section>
{/if}
</div></div></div>

<div class="controlbar">
    <form method="post" action={'git_manager/dashboard'|ezurl} data-gm-confirm="{'Check out this commit? The installation then runs its code, on a detached HEAD.'|i18n( 'extension/git_manager' )|wash}">
        <input type="hidden" name="hash" value="{$hash|wash}" />
        <div class="block a4-actions">
            <a class="a4-btn" href={'git_manager/dashboard'|ezurl}>{'Back to the dashboard'|i18n( 'extension/git_manager' )}</a>
            <input class="a4-btn a4-btn-primary" type="submit" name="CheckoutCommit" value="{'Checkout this commit'|i18n( 'extension/git_manager' )}" />
        </div>
    </form>
</div>
</div>

{include uri='design:git_manager/parts/script.tpl'}
