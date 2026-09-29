{* The dashboard's and commit details' small behaviours, no library needed:
   copy buttons, confirmation before a checkout, relative dates, author
   initials, the quick search of the commit log, and clearing the filter. *}
<script type="text/javascript">
var gmTexts = {ldelim}
    copied: "{'Copied'|i18n( 'extension/git_manager' )|wash( 'javascript' )}",
    choose: "{'Choose a branch first.'|i18n( 'extension/git_manager' )|wash( 'javascript' )}",
    head: "{if is_set( $git_manager )}{$git_manager.current_branch|wash( 'javascript' )}{/if}",
    state: "{'It is %ahead commits ahead of the remote and %behind behind.'|i18n( 'extension/git_manager' )|wash( 'javascript' )}"
{rdelim};
{literal}
(function () {
    var lang = document.documentElement.lang || navigator.language || 'en';

    // Copy a hash or the output.
    document.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('.gm-copy') : null;
        if (!button) { return; }
        var text = button.getAttribute('data-copy');
        if (text === null && button.getAttribute('data-copy-from')) {
            var from = document.getElementById(button.getAttribute('data-copy-from'));
            text = from ? from.textContent : '';
        }
        var done = function () {
            var old = button.textContent;
            button.textContent = gmTexts.copied;
            button.classList.add('is-done');
            setTimeout(function () { button.textContent = old; button.classList.remove('is-done'); }, 1400);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () {});
        } else {
            var area = document.createElement('textarea');
            area.value = text; document.body.appendChild(area); area.select();
            try { document.execCommand('copy'); done(); } catch (err) {}
            document.body.removeChild(area);
        }
    });

    // A checkout changes the code the site runs: ask first.
    Array.prototype.forEach.call(document.querySelectorAll('form[data-gm-confirm]'), function (form) {
        form.addEventListener('submit', function (e) {
            var select = form.querySelector('select[name="branch"]');
            if (select && !select.value) { e.preventDefault(); window.alert(gmTexts.choose); return; }
            var text = form.getAttribute('data-gm-confirm');
            if (select) { text = select.value + '\n\n' + text; }
            if (!window.confirm(text)) { e.preventDefault(); }
        });
    });

    // A push publishes: say what goes where, and how far apart they are.
    Array.prototype.forEach.call(document.querySelectorAll('form[data-gm-push]'), function (form) {
        form.addEventListener('submit', function (e) {
            var branch = form.querySelector('select[name="branch"]').value;
            var text = form.getAttribute('data-gm-push').replace('%branch', branch)
                .replace('%remote', form.getAttribute('data-remote')).replace('%url', form.getAttribute('data-url'));
            var ahead = form.getAttribute('data-ahead'), behind = form.getAttribute('data-behind');
            if (ahead !== null && branch === gmTexts.head) {
                text += '\n\n' + gmTexts.state.replace('%ahead', ahead).replace('%behind', behind);
            }
            if (!window.confirm(text)) { e.preventDefault(); }
        });
    });

    // "Clear" empties the filter fields before the form is sent.
    var clear = document.querySelector('[data-gm-clear]');
    if (clear) {
        clear.addEventListener('click', function () {
            Array.prototype.forEach.call(document.querySelectorAll('#gm-filter input[name^="filter["]'), function (input) { input.value = ''; });
        });
    }

    // Dates as "3 days ago"; the exact date stays in the tooltip.
    if (window.Intl && Intl.RelativeTimeFormat) {
        var rtf = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });
        var steps = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
        Array.prototype.forEach.call(document.querySelectorAll('time.gm-when'), function (el) {
            var t = Date.parse(el.getAttribute('datetime'));
            if (isNaN(t)) { return; }
            var diff = (t - Date.now()) / 1000;
            for (var i = 0; i < steps.length; i++) {
                if (Math.abs(diff) >= steps[i][1] || i === steps.length - 1) {
                    el.textContent = rtf.format(Math.round(diff / steps[i][1]), steps[i][0]);
                    break;
                }
            }
        });
    }

    // An author's initials in a colour of their own.
    Array.prototype.forEach.call(document.querySelectorAll('.gm-avatar'), function (el) {
        var name = el.getAttribute('data-name') || '?';
        var parts = name.replace(/[^\p{L}\p{N} ]/gu, ' ').trim().split(/\s+/);
        el.textContent = ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
        var h = 0;
        for (var i = 0; i < name.length; i++) { h = (h * 31 + name.charCodeAt(i)) % 360; }
        el.style.background = 'hsl(' + h + ', 45%, 45%)';
    });

    // Quick search of the commits on the page.
    var find = document.getElementById('gm-log-find'), log = document.getElementById('gm-log'), none = document.getElementById('gm-log-none');
    if (find && log) {
        find.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase(), shown = 0;
            Array.prototype.forEach.call(log.children, function (li) {
                var match = q === '' || li.getAttribute('data-find').toLowerCase().indexOf(q) !== -1;
                li.hidden = !match;
                if (match) { shown++; }
            });
            if (none) { none.hidden = shown !== 0; }
        });
        // Enter in the quick search filters the page, it does not post the form.
        find.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
    }
})();
{/literal}
</script>
