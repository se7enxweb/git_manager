{* git_manager/backup: how fresh the backups are, making a backup, and the backups with their archives.

   The status at the top is taken from the NEWEST backup (GitManagerBackupFreshness); its words come from
   GitManagerBackupMessages and are washed here. Every form posts to git_manager/backup (ezformtoken adds its
   token) and the view redirects back, so a reload repeats nothing. Without javascript everything works:
   the confirmations and "select all" are the only parts that need it.

   Variables: captions, backup_status, error, message, persistent_server, download_limit_text.
   Kept from 1.x for overridden templates: oldest_warning (since 2.0.15 the age of the newest backup),
   no_backups_warning, processing. Look: design/standard/stylesheets/git_manager.css (.gm-backup). *}
{ezcss_require( 'git_manager.css' )}
{def $status = first_set( $backup_status, false() )
     $action = 'git_manager/backup'|ezurl( 'no' )
     $type_names = hash( 'database', 'Database'|i18n( 'extension/git_manager' ),
                         'agpl', 'Database, AGPL compatible'|i18n( 'extension/git_manager' ),
                         'var', 'var directory'|i18n( 'extension/git_manager' ),
                         'site', 'Site files'|i18n( 'extension/git_manager' ),
                         'other', 'Other file'|i18n( 'extension/git_manager' ) )}

<div class="context-block gm-backup"
     data-gm-confirm-delete-one="{'Remove the backup %name and all its files? This cannot be undone.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-confirm-delete-many="{'Remove %count selected backups and all their files? This cannot be undone.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-confirm-create="{'Create this backup now? It can take several minutes; keep the page open.'|i18n( 'extension/git_manager' )|wash}"
     data-gm-selected="{'%count selected'|i18n( 'extension/git_manager' )|wash}">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Backups'|i18n( 'extension/git_manager' )}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

<p class="gm-intro">{'Backups of this site: the database, the var directory with the uploaded files, and the site\'s own extensions and settings. Each backup is a folder of archives you can download, and only the site\'s owner on the server can read it.'|i18n( 'extension/git_manager' )}</p>

{if $error}
<div class="gm-feedback is-bad" role="alert"><strong>{'Not done:'|i18n( 'extension/git_manager' )}</strong> {$error|wash}</div>
{/if}
{if $message}
<div class="gm-feedback is-ok" role="status"><strong>{'Done:'|i18n( 'extension/git_manager' )}</strong> {$message|wash}</div>
{/if}

{if $status}
<section class="gm-health is-{$status.severity|wash}" data-gm-backup-state="{$status.state|wash}" aria-labelledby="gm-health-title">
    <div class="gm-health-mark" aria-hidden="true">{if eq( $status.severity, 'ok' )}&#10003;{else}!{/if}</div>
    <div class="gm-health-body">
        <h2 class="gm-health-title" id="gm-health-title">{$status.title|wash}</h2>
        <p>{$status.text|wash}</p>
        {foreach $status.notes as $note}<p class="gm-health-note">{$note|wash}</p>{/foreach}
    </div>
    <p class="gm-health-count">{$status.count_text|wash}</p>
</section>
{/if}

<section class="gm-section" aria-labelledby="gm-create-title">
<h2 class="gm-h2" id="gm-create-title">{'Create a backup'|i18n( 'extension/git_manager' )}</h2>
<div class="gm-create-grid">
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
<form class="gm-create{if $card.primary} is-primary{/if}" action="{$action|wash}" method="post">
    <h3>{$card.title|wash}</h3>
    <p class="gm-muted">{$card.what|wash}</p>
    <div class="gm-field">
        <label for="gm-desc-{$card.id}">{'Description'|i18n( 'extension/git_manager' )} <span class="gm-muted">{'(optional)'|i18n( 'extension/git_manager' )}</span></label>
        <input type="text" id="gm-desc-{$card.id}" name="description" maxlength="200" autocomplete="off" />
    </div>
    <label class="gm-check"><input type="checkbox" name="encrypt" value="yes" /> <span>{'Encrypt the archives (GPG, AES-256)'|i18n( 'extension/git_manager' )}</span></label>
    <div class="gm-field">
        <label for="gm-pass-{$card.id}">{'Passphrase for encryption'|i18n( 'extension/git_manager' )}</label>
        <input type="password" id="gm-pass-{$card.id}" name="passphrase" autocomplete="new-password" aria-describedby="gm-pass-help-{$card.id}" />
        <span class="gm-help" id="gm-pass-help-{$card.id}">{'Only used when encrypting. Without it an encrypted archive cannot be opened, by anyone.'|i18n( 'extension/git_manager' )}</span>
    </div>
    {if $card.agpl}
    <label class="gm-check"><input type="checkbox" name="agpl_compatible" value="yes" /> <span>{'Also an AGPL compatible database dump: passwords, e-mail addresses, keys and paths removed, safe to share.'|i18n( 'extension/git_manager' )}</span></label>
    {/if}
    <div class="gm-create-foot">
        <button type="submit" class="gm-btn{if $card.primary} gm-btn-primary{/if}" name="{$card.action}" value="1" data-gm-confirm="create">{$card.button|wash}</button>
    </div>
</form>
{/foreach}
</div>
</section>

<section class="gm-section" aria-labelledby="gm-list-title">
<h2 class="gm-h2" id="gm-list-title">{'Existing backups'|i18n( 'extension/git_manager' )}</h2>

{if $captions|count|eq( 0 )}
<p class="gm-empty">{'There is no backup yet. Create one with one of the forms above.'|i18n( 'extension/git_manager' )}</p>
{else}
{if $persistent_server}
<p class="gm-help gm-list-note">{'This page is served by a persistent PHP server, which sends files up to %limit. Larger archives are marked; download them through the address served by Apache or PHP-FPM.'|i18n( 'extension/git_manager',, hash( '%limit', $download_limit_text ) )|wash}</p>
{/if}
<form class="gm-list" action="{$action|wash}" method="post">
<div class="gm-bulkbar">
    <label class="gm-check"><input type="checkbox" class="gm-select-all" /> <span>{'Select all'|i18n( 'extension/git_manager' )}</span></label>
    <span class="gm-muted gm-selected-count" aria-live="polite"></span>
    <button type="submit" class="gm-btn gm-btn-outline-danger" name="DeleteSelectedCaptions" value="1" data-gm-confirm="delete-many">{'Remove selected'|i18n( 'extension/git_manager' )}</button>
</div>

<ul class="gm-captions">
{foreach $captions as $caption}
<li class="gm-caption is-{$caption.time_ago.state|wash}{if $caption.is_newest} is-newest{/if}">
    <div class="gm-caption-head">
        {if $caption.valid_name}
        <label class="gm-select"><input type="checkbox" class="gm-caption-select" name="timestamps[]" value="{$caption.timestamp|wash}" aria-label="{'Select %name'|i18n( 'extension/git_manager',, hash( '%name', $caption.timestamp ) )|wash}" /></label>
        {/if}
        <div class="gm-caption-title">
            <h3>{$caption.timestamp|wash}</h3>
            <ul class="gm-badges">
                <li class="gm-badge is-{$caption.time_ago.state|wash}">{$caption.age_text|wash}</li>
                {if $caption.is_newest}<li class="gm-badge is-info">{'Newest'|i18n( 'extension/git_manager' )}</li>{/if}
                {if $caption.agpl_compatible}<li class="gm-badge">{'AGPL compatible dump'|i18n( 'extension/git_manager' )}</li>{/if}
                {if $caption.readable|not}<li class="gm-badge is-bad">{'Cannot be read'|i18n( 'extension/git_manager' )}</li>{/if}
            </ul>
            <p class="gm-muted">{$caption.date|wash}{if eq( $caption.created_source, 'mtime' )} &middot; {'dated by the folder, the name is not a date'|i18n( 'extension/git_manager' )}{/if} &middot; {$caption.total_size_formatted|wash}</p>
            {if $caption.description}<p class="gm-caption-desc">{$caption.description|wash}</p>{/if}
        </div>
        {if $caption.valid_name}
        <button type="submit" class="gm-btn gm-btn-small gm-btn-outline-danger" name="DeleteCaption" value="{$caption.timestamp|wash}" data-gm-confirm="delete-one" data-gm-name="{$caption.timestamp|wash}">{'Remove'|i18n( 'extension/git_manager' )}</button>
        {/if}
    </div>
    {if $caption.files|count}
    <div class="gm-table-wrap">
    <table class="gm-table">
        <caption class="gm-sr">{'Archives of %name'|i18n( 'extension/git_manager',, hash( '%name', $caption.timestamp ) )|wash}</caption>
        <thead><tr><th scope="col">{'Archive'|i18n( 'extension/git_manager' )}</th><th scope="col">{'Contents'|i18n( 'extension/git_manager' )}</th><th scope="col" class="gm-num">{'Size'|i18n( 'extension/git_manager' )}</th><th scope="col"><span class="gm-sr">{'Download'|i18n( 'extension/git_manager' )}</span></th></tr></thead>
        <tbody>
        {foreach $caption.files as $file}
        <tr>
            <td class="gm-arch"><code>{$file.name|wash}</code>{if $file.encrypted} <span class="gm-badge is-info">{'Encrypted'|i18n( 'extension/git_manager' )}</span>{/if}</td>
            <td>{first_set( $type_names[$file.type], $file.type )|wash}</td>
            <td class="gm-num">{$file.size_formatted|wash}</td>
            <td class="gm-dl">
            {if $file.archive|not}
                <span class="gm-muted">&ndash;</span>
            {elseif $file.too_large_here}
                <span class="gm-muted">{'Too large for this server'|i18n( 'extension/git_manager' )}</span>
            {else}
                <a class="gm-btn gm-btn-small" href="{concat( 'git_manager/download/', $caption.timestamp, '/', $file.name )|ezurl( 'no' )}" download>{'Download'|i18n( 'extension/git_manager' )}<span class="gm-sr"> {$file.name|wash}</span></a>
            {/if}
            </td>
        </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
    {elseif $caption.readable}
    <p class="gm-muted">{'This backup holds no archive.'|i18n( 'extension/git_manager' )}</p>
    {/if}
</li>
{/foreach}
</ul>
</form>
{/if}
</section>

</div></div></div>
</div>

<script>
{literal}
(function () {
    var page = document.querySelector('.gm-backup');
    if (!page) return;
    var all = page.querySelector('.gm-select-all');
    var boxes = page.querySelectorAll('.gm-caption-select');
    var count = page.querySelector('.gm-selected-count');
    function selected() { var n = 0; for (var i = 0; i < boxes.length; i++) if (boxes[i].checked) n++; return n; }
    function update() {
        var n = selected();
        if (count) count.textContent = page.getAttribute('data-gm-selected').replace('%count', n);
        if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
    }
    if (all) all.addEventListener('change', function () { for (var i = 0; i < boxes.length; i++) boxes[i].checked = all.checked; update(); });
    for (var i = 0; i < boxes.length; i++) boxes[i].addEventListener('change', update);
    update();
    page.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-gm-confirm]') : null;
        if (!b) return;
        var kind = b.getAttribute('data-gm-confirm'), text;
        if (kind === 'delete-many') {
            var n = selected();
            if (n === 0) { e.preventDefault(); return; }
            text = page.getAttribute('data-gm-confirm-delete-many').replace('%count', n);
        } else if (kind === 'delete-one') {
            text = page.getAttribute('data-gm-confirm-delete-one').replace('%name', b.getAttribute('data-gm-name'));
        } else {
            text = page.getAttribute('data-gm-confirm-create');
        }
        if (!window.confirm(text)) e.preventDefault();
    });
})();
{/literal}
</script>
{undef}
