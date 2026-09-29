{ezcss_require( 'git_manager.css' )}

<div class="context-block gm">
    <div class="box-header">
        <h1 class="context-title">{'Commit details'|i18n( 'extension/git_manager' )}</h1>
        <div class="header-mainline"></div>
    </div>
    <div class="box-content">
    {if $commit}
        <div class="gm-commit-head">
            {if $commit.message|count}<h2 class="gm-commit-subject">{$commit.message[0]|wash}</h2>{/if}
            <span class="gm-hash"><span class="gm-mono">{$commit.hash|wash}</span><button type="button" class="gm-copy" data-copy="{$commit.hash|wash}">{'Copy'|i18n( 'extension/git_manager' )}</button></span>
            <dl class="gm-commit-fields">
                {foreach $commit.fields as $field}
                <dt>{$field.name|wash}</dt>
                <dd>{if eq( $field.name, 'Date' )}<time class="gm-when" datetime="{$field.value|wash}" title="{$field.value|wash}">{$field.value|wash}</time>{else}{$field.value|wash}{/if}</dd>
                {/foreach}
                <dt>{'Changes'|i18n( 'extension/git_manager' )}</dt>
                <dd>{$commit.files|count} {'files'|i18n( 'extension/git_manager' )}, <span class="gm-stat-add">+{$commit.additions}</span> <span class="gm-stat-del">&minus;{$commit.deletions}</span></dd>
            </dl>
            {if gt( $commit.message|count, 1 )}
            <pre class="gm-commit-body">{foreach $commit.message as $index => $line}{if gt( $index, 0 )}{$line|wash}
{/if}{/foreach}</pre>
            {/if}
        </div>

        {if $commit.files|count}
        <div class="gm-files">
        {foreach $commit.files as $file}
            <details class="gm-file"{if lt( $commit.files|count, 12 )} open="open"{/if}>
                <summary><span class="gm-file-name">{$file.name|wash}</span>
                    <span class="gm-file-stats"><span class="gm-stat-add">+{$file.additions}</span> <span class="gm-stat-del">&minus;{$file.deletions}</span></span></summary>
                <div class="gm-diff">{foreach $file.lines as $line}<div class="is-{$line.type}">{$line.text|wash}</div>{/foreach}</div>
                {if $file.truncated}<div class="gm-truncated">{'Only the first 500 lines of this file are shown.'|i18n( 'extension/git_manager' )}</div>{/if}
            </details>
        {/foreach}
        </div>
        {/if}
    {else}
        <div class="gm-output">
            <div class="gm-output-head"><span>{'Output'|i18n( 'extension/git_manager' )}</span></div>
            <pre>{$output|wash}</pre>
        </div>
    {/if}
    </div>

    <div class="controlbar">
        <form method="post" action={'git_manager/dashboard'|ezurl} data-gm-confirm="{'Check out this commit? The installation then runs its code, on a detached HEAD.'|i18n( 'extension/git_manager' )|wash}">
            <input type="hidden" name="hash" value="{$hash|wash}" />
            <div class="block">
                <a class="button" href={'git_manager/dashboard'|ezurl}>{'Back to the dashboard'|i18n( 'extension/git_manager' )}</a>
                <input class="defaultbutton" type="submit" name="CheckoutCommit" value="{'Checkout this commit'|i18n( 'extension/git_manager' )}" />
            </div>
        </form>
    </div>
</div>

{include uri='design:git_manager/parts/script.tpl'}
