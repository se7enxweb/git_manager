{ezscript_require( array( 'ezjsc::jqueryui' ) )}
{ezcss_require( 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/cupertino/jquery-ui.min.css' )}

<style>
{literal}   
.backup-manager {
    max-width: 1200px;
}

.backup-actions {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.backup-action-card {
    flex: 1;
    min-width: 280px;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    background: #f9f9f9;
    transition: all 0.3s ease;
}

.backup-action-card:hover {
    border-color: #4a90e2;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.backup-action-card.primary {
    border-color: #4a90e2;
    background: #e8f4fd;
}

.backup-action-icon {
    font-size: 48px;
    margin-bottom: 15px;
    color: #4a90e2;
}

.backup-action-icon.database { color: #e74c3c; }
.backup-action-icon.files { color: #f39c12; }
.backup-action-icon.full { color: #27ae60; }

.backup-action-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
}

.backup-action-description {
    font-size: 13px;
    color: #666;
    margin-bottom: 15px;
    line-height: 1.5;
}

.backup-action-help {
    font-size: 12px;
    color: #999;
    font-style: italic;
    margin-bottom: 15px;
    padding: 8px;
    background: #fff;
    border-left: 3px solid #4a90e2;
}

.backup-description-input {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.backup-encryption-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 15px;
}

.backup-encryption-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: bold;
    color: #2c3e50;
}

.backup-passphrase-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    display: none;
}

.backup-passphrase-input.active {
    display: block;
}

.encryption-note {
    font-size: 11px;
    color: #666;
    margin-top: 5px;
    font-style: italic;
}

.backup-list-header {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 6px 6px 0 0;
    border: 1px solid #ddd;
    border-bottom: none;
}

.backup-list-title {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.caption-item {
    border: 1px solid #ddd;
    border-top: none;
    padding: 20px;
    background: #fff;
    transition: background 0.2s;
}

.caption-item:hover {
    background: #f9f9f9;
}

.caption-item:last-child {
    border-radius: 0 0 6px 6px;
}

.caption-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.caption-timestamp {
    font-size: 16px;
    font-weight: bold;
    color: #2c3e50;
}

.caption-date {
    font-size: 13px;
    color: #7f8c8d;
    margin-left: 10px;
}

.caption-age {
    font-size: 13px;
    font-weight: 600;
    margin-left: 10px;
}

.caption-description {
    font-size: 13px;
    color: #555;
    margin-bottom: 10px;
    font-style: italic;
}

.caption-files {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.caption-file {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f0f0f0;
    border-radius: 4px;
    font-size: 13px;
}

.caption-file.database {
    background: #ffe6e6;
    color: #c0392b;
}

.caption-file.var {
    background: #fff3cd;
    color: #856404;
}

.caption-file.site {
    background: #d4edda;
    color: #155724;
}

.caption-file.agpl {
    background: #fff3e0;
    color: #7d4e00;
    border: 1px solid #f0c040;
}

.badge-agpl {
    display: inline-block;
    background: #e67e22;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 3px;
    letter-spacing: 0.5px;
    vertical-align: middle;
    margin-left: 8px;
}

.agpl-box {
    background: #fff8e1;
    border: 1px solid #f0c040;
    border-left: 3px solid #e67e22;
    border-radius: 4px;
    padding: 10px 12px;
    margin-bottom: 12px;
    font-size: 12px;
}

.agpl-box label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-weight: 600;
    color: #7d4e00;
    cursor: pointer;
    margin: 0;
}

.agpl-box input[type="checkbox"] {
    margin-top: 2px;
    flex-shrink: 0;
}

.agpl-note {
    font-size: 11px;
    color: #856404;
    margin-top: 6px;
    font-style: italic;
    line-height: 1.4;
    padding-left: 24px;
}

.caption-file-icon {
    font-size: 16px;
}

.caption-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.caption-actions-downloads {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.caption-actions-delete {
    margin-left: auto;
}

.btn-download {
    background: #27ae60;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
}

.btn-download:hover {
    background: #229954;
}

.btn-delete {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.btn-delete:hover {
    background: #c0392b;
}

.no-captions {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 14px;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 6px 6px;
    background: #fafafa;
}

.processing-indicator {
    padding: 15px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #856404;
}

.bulk-actions-bar {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 15px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
}

.bulk-actions-bar label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #333;
    cursor: pointer;
}

.bulk-actions-bar input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    margin: 0;
    flex-shrink: 0;
}

.btn-delete-selected {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-delete-selected:hover:not(:disabled) {
    background: #c0392b;
}

.btn-delete-selected:disabled {
    background: #ccc;
    cursor: not-allowed;
    opacity: 0.6;
}

.caption-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 0;
    padding-left: 12px;
    padding-top: 22px;
    min-width: 42px;
    justify-content: flex-start;
}

.caption-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    margin: 0;
    flex-shrink: 0;
}

.caption-item-wrapper {
    display: flex;
    gap: 0;
    align-items: flex-start;
}

.selected-count {
    color: #27ae60;
    font-weight: 600;
    margin-left: auto;
}

.backup-outdated-warning {
    margin: 15px 0 20px 0;
    padding: 18px 24px;
    background: linear-gradient(135deg, #fff3cd 0%, #ffe5a0 100%);
    border: 4px solid #ff9800;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.25);
    animation: pulse-warning 2s ease-in-out infinite;
}

@keyframes pulse-warning {
    0%, 100% { box-shadow: 0 4px 12px rgba(255, 152, 0, 0.25); }
    50% { box-shadow: 0 4px 20px rgba(255, 152, 0, 0.45); }
}

.backup-outdated-warning-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.backup-outdated-warning-icon {
    font-size: 32px;
    line-height: 1;
    animation: shake 3s ease-in-out infinite;
}

@keyframes shake {
    0%, 90%, 100% { transform: rotate(0deg); }
    92%, 96% { transform: rotate(-15deg); }
    94%, 98% { transform: rotate(15deg); }
}

.backup-outdated-warning-title {
    font-size: 18px;
    font-weight: 700;
    color: #d84315;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.backup-outdated-warning-message {
    font-size: 15px;
    line-height: 1.6;
    color: #5d4037;
    margin: 0;
    padding-left: 44px;
}

.backup-outdated-warning-message strong {
    color: #d84315;
    font-weight: 700;
}

.backup-critical-warning {
    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
    border-color: #d32f2f;
    animation: pulse-critical 1.5s ease-in-out infinite;
}

@keyframes pulse-critical {
    0%, 100% { 
        box-shadow: 0 4px 12px rgba(211, 47, 47, 0.35);
        border-color: #d32f2f;
    }
    50% { 
        box-shadow: 0 4px 24px rgba(211, 47, 47, 0.65);
        border-color: #c62828;
    }
}

.backup-critical-warning .backup-outdated-warning-icon {
    animation: shake-urgent 2s ease-in-out infinite;
}

@keyframes shake-urgent {
    0%, 85%, 100% { transform: rotate(0deg); }
    87%, 91%, 95%, 99% { transform: rotate(-20deg); }
    89%, 93%, 97% { transform: rotate(20deg); }
}

.backup-critical-warning .backup-outdated-warning-title {
    color: #b71c1c;
}

.backup-critical-warning .backup-outdated-warning-message strong {
    color: #b71c1c;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .backup-actions {
        flex-direction: column;
    }
    
    .backup-action-card {
        min-width: 100%;
    }
    
    .caption-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .caption-timestamp {
        font-size: 14px;
        word-break: break-all;
    }
    
    .caption-date,
    .caption-age {
        display: block;
        margin-left: 0;
        margin-top: 4px;
    }
    
    .caption-date {
        display: block;
        margin-left: 0;
    }
    
    .caption-files {
        flex-direction: column;
        gap: 8px;
    }
    
    .caption-file {
        width: 100%;
        font-size: 11px;
        padding: 6px 8px;
        word-break: break-all;
        overflow-wrap: break-word;
    }
    
    .caption-file span {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .caption-actions {
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }
    
    .btn-download,
    .btn-delete {
        width: 100%;
        text-align: center;
        font-size: 12px;
        padding: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .backup-outdated-warning {
        padding: 16px 20px;
        margin: 12px 0 18px 0;
    }
    
    .backup-outdated-warning-message {
        padding-left: 44px;
    }
    
    .bulk-actions-bar {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .selected-count {
        width: 100%;
        margin-left: 0;
        text-align: center;
    }
    
    .caption-item-wrapper {
        flex-direction: column;
        gap: 8px;
    }
    
    .caption-checkbox {
        margin-bottom: 8px;
    }
    
    .backup-manager {
        padding: 10px;
    }
    
    .caption-item {
        padding: 12px;
    }
}

@media (max-width: 480px) {
    .backup-action-title {
        font-size: 16px;
    }
    
    .backup-action-description,
    .backup-action-help {
        font-size: 12px;
    }
    
    .caption-timestamp {
        font-size: 12px;
    }
    
    .caption-file {
        font-size: 10px;
    }
    
    .btn-download,
    .btn-delete {
        font-size: 11px;
        padding: 8px;
    }
    
    .backup-outdated-warning {
        padding: 14px 16px;
        margin: 12px 0 16px 0;
        border-width: 3px;
    }
    
    .backup-outdated-warning-icon {
        font-size: 28px;
    }
    
    .backup-outdated-warning-title {
        font-size: 15px;
    }
    
    .backup-outdated-warning-message {
        font-size: 13px;
        padding-left: 40px;
    }
}
{/literal}
</style>

<script type="text/javascript">
{literal}
$(document).ready(function() {
    // Toggle passphrase input visibility
    $('.encrypt-checkbox').on('change', function() {
        var $form = $(this).closest('form');
        var $passphraseInput = $form.find('.backup-passphrase-input');
        
        if($(this).is(':checked')) {
            $passphraseInput.addClass('active');
            $passphraseInput.prop('required', true);
        } else {
            $passphraseInput.removeClass('active');
            $passphraseInput.prop('required', false);
            $passphraseInput.val('');
        }
    });
    
    // Multi-selection functionality
    function updateSelectedCount() {
        var count = $('.caption-select:checked').length;
        $('.selected-count').text(count + ' selected');
        $('.btn-delete-selected').prop('disabled', count === 0);
    }
    
    // Select all checkbox
    $('#select-all-captions').on('change', function() {
        $('.caption-select').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });
    
    // Individual caption checkboxes
    $('.caption-select').on('change', function() {
        var total = $('.caption-select').length;
        var checked = $('.caption-select:checked').length;
        $('#select-all-captions').prop('checked', total === checked);
        updateSelectedCount();
    });
    
    // Delete selected captions
    $('#delete-selected-form').on('submit', function(e) {
        var count = $('.caption-select:checked').length;
        if(count === 0) {
            e.preventDefault();
            alert('Please select at least one caption to delete.');
            return false;
        }
        
        // Collect selected timestamps
        var timestamps = [];
        $('.caption-select:checked').each(function() {
            timestamps.push($(this).val());
        });
        $('#selected-timestamps-input').val(timestamps.join(','));
        
        var confirmMsg = count === 1 
            ? 'Delete 1 selected caption? This cannot be undone.'
            : 'Delete ' + count + ' selected captions? This cannot be undone.';
        
        if(!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Individual delete button confirmation
    $('.btn-delete-single').on('click', function(e) {
        var timestamp = $(this).data('timestamp');
        if(!confirm('Delete caption ' + timestamp + '? This cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Initialize count
    updateSelectedCount();
});
{/literal}
</script>

{if $error}
<div class="message-error">
    <h2><strong>Error:</strong> {$error}</h2>
</div>
{/if}

{if $message}
<div class="message-feedback">
    <h2><strong>Success:</strong> {$message}</h2>
</div>	
{/if}

{if $processing}
<div class="processing-indicator">
    <strong>⏳ Processing...</strong> Creating backup, please wait. This may take several minutes depending on your database and file sizes.
</div>
{/if}

<div class="backup-manager">
    <div class="context-block">
        <div class="box-header">
            <h1 class="context-title">{'Backup & Caption Manager'|i18n( 'extension/git_manager' )}</h1>
            <div class="header-mainline"></div>
        </div>
        
        {if $no_backups_warning}
        <div class="backup-outdated-warning backup-critical-warning">
            <div class="backup-outdated-warning-header">
                <div class="backup-outdated-warning-icon">🚨</div>
                <h3 class="backup-outdated-warning-title">No Backups Found — System Unprotected!</h3>
            </div>
            <p class="backup-outdated-warning-message">
                <strong>Your website has ZERO backup captions.</strong> Take a backup caption <strong>right now</strong> to protect your site database and var directory files from data loss!
            </p>
        </div>
        {elseif $oldest_warning}
        <div class="backup-outdated-warning">
            <div class="backup-outdated-warning-header">
                <div class="backup-outdated-warning-icon">⚠️</div>
                <h3 class="backup-outdated-warning-title">Backup Outdated Warning</h3>
            </div>
            <p class="backup-outdated-warning-message">
                Your site backup captions are <strong>{$oldest_warning.age} old</strong> and outdated. Please create a new caption now to protect your site database and var directory.
            </p>
        </div>
        {/if}

        <div class="box-content">
            <div class="backup-actions">
                <!-- Full Site Backup Card -->
                <div class="backup-action-card primary">
                    <div class="backup-action-icon full">🌐</div>
                    <div class="backup-action-title">Full Site Backup</div>
                    <div class="backup-action-description">
                        <strong>Includes: SQL database dump + var directory files + extensions + settings + config.php</strong>
                    </div>
                    <div class="backup-action-help">
                        ⭐ <strong>Most Complete:</strong> Creates 3 archives — (1) SQL dump of entire database, (2) var/ directory including uploaded files, (3) site files: extensions/, settings/, config.php. Use this to fully restore the site from scratch.
                    </div>
                    <form action="{'git_manager/dump'|ezurl( 'no' )}" method="post">
                        <input type="text" name="description" class="backup-description-input" placeholder="Optional: Add description..." />
                        
                        <div class="backup-encryption-box">
                            <div class="backup-encryption-header">
                                <input type="checkbox" name="encrypt" value="yes" class="encrypt-checkbox" id="encrypt-fullsite" />
                                <label for="encrypt-fullsite">🔒 Encrypt backup files</label>
                            </div>
                            <input type="password" name="passphrase" class="backup-passphrase-input" placeholder="Enter encryption passphrase..." />
                            <div class="encryption-note">
                                ⚠️ <strong>Important:</strong> Remember your passphrase! Encrypted files cannot be recovered without it.
                            </div>
                        </div>
                        
                        <div class="agpl-box">
                            <label>
                                <input type="checkbox" name="agpl_compatible" value="yes" />
                                🔓 AGPL Compatible Release
                            </label>
                            <div class="agpl-note">
                                Creates an additional sanitized SQL dump with all private data removed (user passwords, emails, API keys, GA IDs, disk paths, credentials). Safe to share with other developers per AGPL/GPL obligations. Marked in caption history.
                            </div>
                        </div>
                        
                        <input class="button defaultbutton" type="submit" name="CreateFullSiteBackup" value="{'Create Full Site Backup'|i18n( 'extension/git_manager' )}" onclick="return confirm('Create full site backup (DB + var + site files)? This may take several minutes.');" />
                    </form>
                </div>

                <!-- Full Caption Card -->
                <div class="backup-action-card">
                    <div class="backup-action-icon full">📦</div>
                    <div class="backup-action-title">DB + Files Caption</div>
                    <div class="backup-action-description">
                        <strong>Includes: SQL database dump + var directory files</strong>
                    </div>
                    <div class="backup-action-help">
                        💡 <strong>Recommended daily backup:</strong> Creates 2 archives — (1) SQL dump of entire database (schema + data), (2) var/ directory with all uploaded files. Does <em>not</em> include extensions or settings.
                    </div>
                    <form action="{'git_manager/dump'|ezurl( 'no' )}" method="post">
                        <input type="text" name="description" class="backup-description-input" placeholder="Optional: Add description..." />
                        
                        <div class="backup-encryption-box">
                            <div class="backup-encryption-header">
                                <input type="checkbox" name="encrypt" value="yes" class="encrypt-checkbox" id="encrypt-full" />
                                <label for="encrypt-full">🔒 Encrypt backup files</label>
                            </div>
                            <input type="password" name="passphrase" class="backup-passphrase-input" placeholder="Enter encryption passphrase..." />
                            <div class="encryption-note">
                                ⚠️ <strong>Important:</strong> Remember your passphrase! Encrypted files cannot be recovered without it.
                            </div>
                        </div>
                        
                        <div class="agpl-box">
                            <label>
                                <input type="checkbox" name="agpl_compatible" value="yes" />
                                🔓 AGPL Compatible Release
                            </label>
                            <div class="agpl-note">
                                Creates an additional sanitized SQL dump with all private data removed (user passwords, emails, API keys, GA IDs, disk paths, credentials). Safe to share with other developers per AGPL/GPL obligations. Marked in caption history.
                            </div>
                        </div>
                        
                        <input class="button defaultbutton" type="submit" name="CreateFullCaption" value="{'Create Full Caption'|i18n( 'extension/git_manager' )}" onclick="return confirm('Create full caption (DB + var)? This may take several minutes.');" />
                    </form>
                </div>

                <!-- Database Caption Card -->
                <div class="backup-action-card">
                    <div class="backup-action-icon database">🗄️</div>
                    <div class="backup-action-title">Database Only</div>
                    <div class="backup-action-description">
                        <strong>Includes: SQL database dump only — no files</strong>
                    </div>
                    <div class="backup-action-help">
                        💾 Creates a single SQL archive with the full database (schema + data). No var/ directory or site files are included. Use before database changes or migrations.
                    </div>
                    <form action="{'git_manager/dump'|ezurl( 'no' )}" method="post">
                        <input type="text" name="description" class="backup-description-input" placeholder="Optional: Add description..." />
                        
                        <div class="backup-encryption-box">
                            <div class="backup-encryption-header">
                                <input type="checkbox" name="encrypt" value="yes" class="encrypt-checkbox" id="encrypt-db" />
                                <label for="encrypt-db">🔒 Encrypt backup file</label>
                            </div>
                            <input type="password" name="passphrase" class="backup-passphrase-input" placeholder="Enter encryption passphrase..." />
                            <div class="encryption-note">
                                ⚠️ <strong>Important:</strong> Remember your passphrase! Encrypted files cannot be recovered without it.
                            </div>
                        </div>
                        
                        <div class="agpl-box">
                            <label>
                                <input type="checkbox" name="agpl_compatible" value="yes" />
                                🔓 AGPL Compatible Release
                            </label>
                            <div class="agpl-note">
                                Creates an additional sanitized SQL dump with all private data removed (user passwords, emails, API keys, GA IDs, disk paths, credentials). Safe to share with other developers per AGPL/GPL obligations. Marked in caption history.
                            </div>
                        </div>
                        
                        <input class="button" type="submit" name="CreateDatabaseCaption" value="{'Capture Database'|i18n( 'extension/git_manager' )}" onclick="return confirm('Create database caption?');" />
                    </form>
                </div>

                <!-- Var Directory Caption Card -->
                <div class="backup-action-card">
                    <div class="backup-action-icon files">📁</div>
                    <div class="backup-action-title">Files Only (var/)</div>
                    <div class="backup-action-description">
                        <strong>Includes: var/ directory only — no database</strong>
                    </div>
                    <div class="backup-action-help">
                        📂 Archives the var/ directory containing uploaded images, files, and user content. Cache, logs, and existing backups are excluded. No SQL dump is included.
                    </div>
                    <form action="{'git_manager/dump'|ezurl( 'no' )}" method="post">
                        <input type="text" name="description" class="backup-description-input" placeholder="Optional: Add description..." />
                        
                        <div class="backup-encryption-box">
                            <div class="backup-encryption-header">
                                <input type="checkbox" name="encrypt" value="yes" class="encrypt-checkbox" id="encrypt-var" />
                                <label for="encrypt-var">🔒 Encrypt backup file</label>
                            </div>
                            <input type="password" name="passphrase" class="backup-passphrase-input" placeholder="Enter encryption passphrase..." />
                            <div class="encryption-note">
                                ⚠️ <strong>Important:</strong> Remember your passphrase! Encrypted files cannot be recovered without it.
                            </div>
                        </div>
                        
                        <input class="button" type="submit" name="CreateVarCaption" value="{'Capture Files'|i18n( 'extension/git_manager' )}" onclick="return confirm('Create var directory caption?');" />
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="context-block" style="margin-top: 30px;">
        <div class="backup-list-header">
            <h2 class="backup-list-title">📋 Existing Captions</h2>
        </div>

        {if $captions|count()}
            <div class="bulk-actions-bar">
                <label>
                    <input type="checkbox" id="select-all-captions" />
                    Select All
                </label>
                
                <form id="delete-selected-form" action="{'git_manager/dump'|ezurl('no')}" method="post" style="margin: 0;">
                    <button type="submit" name="DeleteSelectedCaptions" class="btn-delete-selected" disabled>
                        🗑️ Delete Selected
                    </button>
                    <input type="hidden" name="selected_timestamps" id="selected-timestamps-input" value="" />
                </form>
                
                <span class="selected-count">0 selected</span>
            </div>
            
            {foreach $captions as $caption}
            <div class="caption-item-wrapper">
                <div class="caption-checkbox">
                    <input type="checkbox" class="caption-select" name="timestamps[]" value="{$caption.timestamp}" />
                </div>
                <div class="caption-item" style="flex: 1;">
                <div class="caption-header">
                    <div>
                        <span class="caption-timestamp">🕐 {$caption.timestamp}</span>
                        <span class="caption-date">({$caption.date})</span>
                        <span class="caption-age" style="color:{$caption.time_ago.color};font-weight:600;margin-left:10px;">
                            ⏱ {$caption.time_ago.display}
                        </span>
                        {if $caption.agpl_compatible}<span class="badge-agpl" title="Includes AGPL-compatible sanitized SQL dump — safe for public sharing">🔓 AGPL COMPATIBLE</span>{/if}
                    </div>
                    <div>
                        <strong>Total Size:</strong> {$caption.total_size_formatted}
                    </div>
                </div>

                {if $caption.description}
                <div class="caption-description">
                    📝 {$caption.description}
                </div>
                {/if}

                <div class="caption-files">
                    {foreach $caption.files as $file}
                    <div class="caption-file {$file.type}">
                        {if $file.type|eq('database')}
                            <span class="caption-file-icon">🗄️</span>
                        {elseif $file.type|eq('site')}
                            <span class="caption-file-icon">🌐</span>
                        {elseif $file.type|eq('agpl')}
                            <span class="caption-file-icon">🔓</span>
                        {else}
                            <span class="caption-file-icon">📁</span>
                        {/if}
                        <span><strong>{$file.name}</strong> ({$file.size_formatted})</span>
                        {if $file.encrypted}
                            <span style="color:#d35400;margin-left:5px;" title="Encrypted file - passphrase required">🔒</span>
                        {/if}
                    </div>
                    {/foreach}
                </div>

                <div class="caption-actions">
                    <div class="caption-actions-downloads">
                    {foreach $caption.files as $file}
                    <a href="{concat('git_manager/download/', $caption.timestamp, '/', $file.name)|ezurl('no')}" class="btn-download">
                        ⬇️ Download {$file.name}
                    </a>
                    {/foreach}
                    </div>
                    
                    <div class="caption-actions-delete">
                    <form action="{'git_manager/dump'|ezurl( 'no' )}" method="post" style="display: inline;">
                        <input type="hidden" name="timestamp" value="{$caption.timestamp}" />
                        <button type="submit" name="DeleteCaption" class="btn-delete btn-delete-single" data-timestamp="{$caption.timestamp}">
                            🗑️ Delete Caption
                        </button>
                    </form>
                    </div>
                </div>
                </div>
            </div>
            {/foreach}
        {else}
            <div class="no-captions">
                <p><strong>📭 No captions found</strong></p>
                <p>Create your first caption using the action cards above.</p>
            </div>
        {/if}
    </div>
</div>
