{* git_manager/backup: how fresh the backups are, making a backup, and the backups with their archives.

   The status at the top is taken from the NEWEST backup (GitManagerBackupFreshness); its words come from
   GitManagerBackupMessages and are washed here. Every form posts to git_manager/backup (ezformtoken adds its
   token) and the view redirects back, so a reload repeats nothing. Without javascript everything works:
   the confirmations and "select all" are the only parts that need it (git_manager.js, its texts the data-gm-*
   attributes of the root).

   Variables: captions, backup_status, error, message, persistent_server, download_limit_text.
   Kept from 1.x for overridden templates: oldest_warning (since 2.0.15 the age of the newest backup),
   no_backups_warning, processing. Look: the shared components (.a4-card, .a4-notice, .a4-table, .a4-btn,
   .a4-badge, .a4-toolbar, .a4-empty) and design/standard/stylesheets/modules/git_manager.css (.a4-gm-*). *}
{def $status = first_set( $backup_status, false() )
     $action = 'git_manager/backup'|ezurl( 'no' )
     $type_names = hash( 'database', 'Database'|i18n( 'extension/git_manager' ),
                         'agpl', 'Database, AGPL compatible'|i18n( 'extension/git_manager' ),
                         'var', 'var directory'|i18n( 'extension/git_manager' ),
                         'site', 'Site files'|i18n( 'extension/git_manager' ),
                         'other', 'Other file'|i18n( 'extension/git_manager' ) )
     $age_tones = hash( 'fresh', 'is-ok', 'ageing', 'is-warn', 'future', 'is-warn', 'stale', 'is-bad' )}

<div class="context-block a4-page a4-gm a4-gm-backup"
     {include uri='design:git_manager/parts/data.tpl'}
     data-gm-confirm-delete-one="{'Remove the backup %name and all its files? This cannot be undone.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-confirm-delete-many="{'Remove %count selected backups and all their files? This cannot be undone.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-confirm-create="{'Create this backup now? It can take several minutes; keep the page open.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-selected="{'%count selected'|i18n( 'extension/git_manager' )|wash}">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Backups'|i18n( 'extension/git_manager' )}</h1>
<div class="header-mainline"></div>
</div></div>

<div class="box-ml"><div class="box-mr"><div class="box-content">

<p class="a4-intro">{'Backups of this site: the database, the var directory with the uploaded files, and the site\'s own extensions and settings. Each backup is a folder of archives you can download, and only the site\'s owner on the server can read it.'|i18n( 'extension/git_manager' )}</p>

{if $error}
<div class="a4-notice is-bad" role="alert"><p><strong>{'Not done:'|i18n( 'extension/git_manager' )}</strong> {$error|wash}</p></div>
{/if}
{if $message}
<div class="a4-notice is-ok" role="status"><p><strong>{'Done:'|i18n( 'extension/git_manager' )}</strong> {$message|wash}</p></div>
{/if}

{if $status}
<section class="a4-notice is-{$status.severity|wash} a4-gm-health" data-gm-backup-state="{$status.state|wash}" aria-labelledby="gm-health-title">
    <div class="a4-gm-health-body">
        <h2 class="a4-h3 a4-notice-title" id="gm-health-title">{$status.title|wash}</h2>
        <p>{$status.text|wash}</p>
        {foreach $status.notes as $note}<p class="a4-gm-health-note">{$note|wash}</p>{/foreach}
    </div>
    <p class="a4-badge a4-gm-health-count">{$status.count_text|wash}</p>
</section>
{/if}

<section class="a4-section" aria-labelledby="gm-create-title">
<h2 class="a4-h2" id="gm-create-title">{'Create a backup'|i18n( 'extension/git_manager' )}</h2>
<div class="a4-grid is-wide">
{foreach array(
    hash( 'action', 'CreateFullSiteBackup', 'id', 'fullsite', 'primary', true(), 'agpl', true(),
          'title', 'Full site backup'|i18n( 'extension/git_manager' ),
          'what', 'Database, var directory, extensions, settings and config.php: three archives, enough to set the site up again from nothing.'|i18n( 'extension/git_manager' ),
          'button', 'Create full site backup'|i18n( 'extension/git_manager' ) ),
    hash( 'action', 'CreateFullCaption', 'id', 'full', 'primary', false(), 'agpl', true(),
          'title', 'Database and files'|i18n( 'extension/git_manager' ),
          'what', 'Database and var directory: two archives. The regular backup; extensions and settings are not included.'|i18n( 'extension/git_manager' ),
          'button', 'Create backup'|i18n( 'extension/git_manager' ) ),
    hash( 'action', 'CreateDatabaseCaption', 'id', 'db', 'primary', false(), 'agpl', true(),
          'title', 'Database only'|i18n( 'extension/git_manager' ),
          'what', 'The whole database, schema and data, in one archive. Before an upgrade or a change to the database.'|i18n( 'extension/git_manager' ),
          'button', 'Back up the database'|i18n( 'extension/git_manager' ) ),
    hash( 'action', 'CreateVarCaption', 'id', 'var', 'primary', false(), 'agpl', false(),
          'title', 'Files only'|i18n( 'extension/git_manager' ),
          'what', 'The var directory with the uploaded images and files, without caches, logs, sessions and earlier backups.'|i18n( 'extension/git_manager' ),
          'button', 'Back up the files'|i18n( 'extension/git_manager' ) ) ) as $card}
<form class="a4-card{if $card.primary} is-selected{/if} a4-gm-create" action="{$action|wash}" method="post" aria-labelledby="gm-create-{$card.id}">
    <div class="a4-card-head">
        <h3 class="a4-h3" id="gm-create-{$card.id}">{$card.title|wash}</h3>
        <p>{$card.what|wash}</p>
    </div>
    <div class="a4-card-body a4-stack">
        <div class="a4-field">
            <label for="gm-desc-{$card.id}">{'Description'|i18n( 'extension/git_manager' )} <span class="a4-muted">{'(optional)'|i18n( 'extension/git_manager' )}</span></label>
            <input type="text" id="gm-desc-{$card.id}" name="description" maxlength="200" autocomplete="off" />
        </div>
        <label class="a4-check"><input type="checkbox" name="encrypt" value="yes" /> <span>{'Encrypt the archives (GPG, AES-256)'|i18n( 'extension/git_manager' )}</span></label>
        <div class="a4-field">
            <label for="gm-pass-{$card.id}">{'Passphrase for encryption'|i18n( 'extension/git_manager' )}</label>
            <input type="password" id="gm-pass-{$card.id}" name="passphrase" autocomplete="new-password" aria-describedby="gm-pass-help-{$card.id}" />
            <span class="a4-help" id="gm-pass-help-{$card.id}">{'Only used when encrypting. Without it an encrypted archive cannot be opened, by anyone.'|i18n( 'extension/git_manager' )}</span>
        </div>
        {if $card.agpl}
        <label class="a4-check"><input type="checkbox" name="agpl_compatible" value="yes" /> <span>{'Also an AGPL compatible database dump: passwords, e-mail addresses, keys and paths removed, safe to share.'|i18n( 'extension/git_manager' )}</span></label>
        {/if}
    </div>
    <div class="a4-card-foot">
        <button type="submit" class="a4-btn{if $card.primary} a4-btn-primary{/if}" name="{$card.action}" value="1" data-gm-ask="create">{$card.button|wash}</button>
    </div>
</form>
{/foreach}
</div>
</section>

<section class="a4-section" aria-labelledby="gm-list-title">
<h2 class="a4-h2" id="gm-list-title">{'Existing backups'|i18n( 'extension/git_manager' )}</h2>

{if $captions|count|eq( 0 )}
<p class="a4-empty">{'There is no backup yet. Create one with one of the forms above.'|i18n( 'extension/git_manager' )}</p>
{else}
{if $persistent_server}
<p class="a4-help a4-gm-list-note">{'This page is served by a persistent PHP server, which sends files up to %limit. Larger archives are marked; download them through the address served by Apache or PHP-FPM.'|i18n( 'extension/git_manager',, hash( '%limit', $download_limit_text ) )|wash}</p>
{/if}
<form class="a4-gm-list" action="{$action|wash}" method="post">
<div class="a4-toolbar">
    <label class="a4-check a4-gm-select-all-label"><input type="checkbox" class="a4-gm-select-all" /> <span>{'Select all'|i18n( 'extension/git_manager' )}</span></label>
    <p class="a4-meta a4-gm-selected-count" aria-live="polite"></p>
    <div class="a4-actions">
        <button type="submit" class="a4-btn a4-btn-outline-danger" name="DeleteSelectedCaptions" value="1" data-gm-ask="delete-many">{'Remove selected'|i18n( 'extension/git_manager' )}</button>
    </div>
</div>

<ul class="a4-cards a4-gm-captions">
{foreach $captions as $caption}
<li class="a4-card{if $caption.is_newest} is-info{/if} a4-gm-caption is-{$caption.time_ago.state|wash}{if $caption.is_newest} is-newest{/if}">
    <div class="a4-card-head a4-gm-caption-head">
        {if $caption.valid_name}
        <label class="a4-pick"><input type="checkbox" class="a4-gm-caption-select" name="timestamps[]" value="{$caption.timestamp|wash}" aria-label="{'Select %name'|i18n( 'extension/git_manager',, hash( '%name', $caption.timestamp ) )|wash}" /></label>
        {/if}
        <div class="a4-gm-caption-title">
            <h3 class="a4-h3">{$caption.timestamp|wash}</h3>
            <ul class="a4-badges">
                <li class="a4-badge {first_set( $age_tones[$caption.time_ago.state], '' )}">{$caption.age_text|wash}</li>
                {if $caption.is_newest}<li class="a4-badge is-info">{'Newest'|i18n( 'extension/git_manager' )}</li>{/if}
                {if $caption.agpl_compatible}<li class="a4-badge">{'AGPL compatible dump'|i18n( 'extension/git_manager' )}</li>{/if}
                {if $caption.readable|not}<li class="a4-badge is-bad">{'Cannot be read'|i18n( 'extension/git_manager' )}</li>{/if}
            </ul>
            <p class="a4-meta">{$caption.date|wash}{if eq( $caption.created_source, 'mtime' )} &middot; {'dated by the folder, the name is not a date'|i18n( 'extension/git_manager' )}{/if} &middot; {$caption.total_size_formatted|wash}</p>
            {if $caption.description}<p class="a4-gm-caption-desc">{$caption.description|wash}</p>{/if}
        </div>
        {if $caption.valid_name}
        <div class="a4-card-actions">
            <button type="submit" class="a4-btn a4-btn-small a4-btn-outline-danger" name="DeleteCaption" value="{$caption.timestamp|wash}" data-gm-ask="delete-one" data-gm-name="{$caption.timestamp|wash}">{'Remove'|i18n( 'extension/git_manager' )}<span class="a4-sr-only"> {$caption.timestamp|wash}</span></button>
        </div>
        {/if}
    </div>
    {if $caption.files|count}
    <div class="a4-card-body">
    <div class="a4-table-wrap">
    <table class="a4-table">
        <caption class="a4-sr-only">{'Archives of %name'|i18n( 'extension/git_manager',, hash( '%name', $caption.timestamp ) )|wash}</caption>
        <thead><tr><th scope="col">{'Archive'|i18n( 'extension/git_manager' )}</th><th scope="col">{'Contents'|i18n( 'extension/git_manager' )}</th><th scope="col" class="a4-num">{'Size'|i18n( 'extension/git_manager' )}</th><th scope="col"><span class="a4-sr-only">{'Download'|i18n( 'extension/git_manager' )}</span></th></tr></thead>
        <tbody>
        {foreach $caption.files as $file}
        <tr>
            <td class="a4-gm-arch"><code class="a4-code">{$file.name|wash}</code>{if $file.encrypted} <span class="a4-badge is-info">{'Encrypted'|i18n( 'extension/git_manager' )}</span>{/if}</td>
            <td>{first_set( $type_names[$file.type], $file.type )|wash}</td>
            <td class="a4-num a4-nowrap">{$file.size_formatted|wash}</td>
            <td class="a4-end a4-nowrap">
            {if $file.archive|not}
                <span class="a4-muted">&ndash;</span>
            {elseif $file.too_large_here}
                <span class="a4-muted">{'Too large for this server'|i18n( 'extension/git_manager' )}</span>
            {else}
                <a class="a4-btn a4-btn-small" href="{concat( 'git_manager/download/', $caption.timestamp, '/', $file.name )|ezurl( 'no' )}" download>{'Download'|i18n( 'extension/git_manager' )}<span class="a4-sr-only"> {$file.name|wash}</span></a>
            {/if}
            </td>
        </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
    </div>
    {elseif $caption.readable}
    <p class="a4-empty">{'This backup holds no archive.'|i18n( 'extension/git_manager' )}</p>
    {/if}
</li>
{/foreach}
</ul>
</form>
{/if}
</section>

</div></div></div>
</div>

{include uri='design:git_manager/parts/script.tpl'}
{undef}
