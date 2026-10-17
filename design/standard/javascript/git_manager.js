/*
 * git_manager: the small behaviours of the dashboard, the commit details and the Backup page, no library needed.
 * Loaded with ezscript_require( 'git_manager.js' ) by those templates (no inline script, so a strict
 * Content-Security-Policy keeps them working). The texts come from data-gm-* attributes of the page's root
 * (.a4-gm), written and translated by the templates.
 *
 * Dashboard and commit details: copy buttons, confirmation before a checkout or a push, "Fetch now" busy state,
 * relative dates, author initials, the quick search of the commit log, the folds kept as
 * user preferences. Backup page: "select all", the count of the selected backups and the confirmations.
 * Without javascript every form still works; only the confirmations and the conveniences need it.
 */
(function () {
    'use strict';

    function each(list, fn) { Array.prototype.forEach.call(list, fn); }
    function closest(el, selector) { return el && el.closest ? el.closest(selector) : null; }

    function init() {
        var root = document.querySelector('.a4-gm');
        if (!root) { return; }
        var text = function (name) { return root.getAttribute('data-gm-' + name) || ''; };
        var lang = document.documentElement.lang || navigator.language || 'en';

        // Copy a hash or the output; a toast where admin4 has one, the button says it too.
        document.addEventListener('click', function (e) {
            var button = closest(e.target, '.a4-gm-copy');
            if (!button) { return; }
            var value = button.getAttribute('data-copy');
            if (value === null && button.getAttribute('data-copy-from')) {
                var from = document.getElementById(button.getAttribute('data-copy-from'));
                value = from ? from.textContent : '';
            }
            var done = function () {
                var old = button.textContent;
                button.textContent = text('copied');
                button.classList.add('is-done');
                if (typeof window.a4Toast === 'function') { window.a4Toast(text('copied'), 'ok', button); }
                setTimeout(function () { button.textContent = old; button.classList.remove('is-done'); }, 1400);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done, function () {});
            } else {
                var area = document.createElement('textarea');
                area.value = value; document.body.appendChild(area); area.select();
                try { document.execCommand('copy'); done(); } catch (err) {}
                document.body.removeChild(area);
            }
        });

        // A checkout changes the code the site runs: ask first.
        each(document.querySelectorAll('form[data-gm-confirm]'), function (form) {
            form.addEventListener('submit', function (e) {
                var select = form.querySelector('select[name="branch"]');
                if (select && !select.value) { e.preventDefault(); window.alert(text('choose')); return; }
                var message = form.getAttribute('data-gm-confirm');
                if (select) { message = select.value + '\n\n' + message; }
                if (!window.confirm(message)) { e.preventDefault(); }
            });
        });

        // A push publishes: say what goes where, and how far apart they are.
        each(document.querySelectorAll('form[data-gm-push]'), function (form) {
            form.addEventListener('submit', function (e) {
                var branch = form.querySelector('select[name="branch"]').value;
                var message = form.getAttribute('data-gm-push').replace('%branch', branch)
                    .replace('%remote', form.getAttribute('data-remote')).replace('%url', form.getAttribute('data-url'));
                var ahead = form.getAttribute('data-ahead'), behind = form.getAttribute('data-behind');
                if (ahead !== null && branch === text('head')) {
                    message += '\n\n' + text('state').replace('%ahead', ahead).replace('%behind', behind);
                }
                if (!window.confirm(message)) { e.preventDefault(); }
            });
        });

        // "Fetch now" can take a while: say so, and send it once.
        each(document.querySelectorAll('form[data-gm-busy]'), function (form) {
            form.addEventListener('submit', function (e) {
                if (form.classList.contains('is-busy')) { e.preventDefault(); return; }
                form.classList.add('is-busy');
                form.setAttribute('aria-busy', 'true');
                var button = form.querySelector('[type="submit"]');
                if (button) {
                    // The button's name must still reach the server: a hidden copy.
                    var copy = document.createElement('input');
                    copy.type = 'hidden'; copy.name = button.name; copy.value = button.value;
                    form.appendChild(copy);
                    if (button.tagName === 'INPUT') { button.value = form.getAttribute('data-gm-busy'); }
                    else { button.textContent = form.getAttribute('data-gm-busy'); }
                    setTimeout(function () { button.disabled = true; }, 0);
                }
            });
        });

        // A button that asks first (Remove a remote, Remove a submodule), in a form that does not.
        document.addEventListener('click', function (e) {
            var button = closest(e.target, '[data-gm-confirm-button]');
            if (button && !window.confirm(button.getAttribute('data-gm-confirm-button'))) { e.preventDefault(); }
        });

        // Dates as "3 days ago"; the exact date stays in the tooltip.
        if (window.Intl && Intl.RelativeTimeFormat) {
            var rtf = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });
            var steps = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
            each(document.querySelectorAll('time.a4-gm-when'), function (el) {
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

        // An author's initials in one of the eight chart colours, the same one for the same name (a class, no style).
        each(document.querySelectorAll('.a4-gm-avatar'), function (el) {
            var name = el.getAttribute('data-name') || '?';
            var parts = name.replace(/[^\p{L}\p{N} ]/gu, ' ').trim().split(/\s+/);
            el.textContent = ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
            var h = 0;
            for (var i = 0; i < name.length; i++) { h = (h * 31 + name.charCodeAt(i)) % 8; }
            el.classList.add('is-c' + (h + 1));
        });

        // The folds kept as user preferences (the commit log, "Add a submodule"). They work as plain details
        // elements without this; the request only remembers the state. The form token travels with it: a POST
        // without it is refused as expired.
        each(document.querySelectorAll('details[data-preference-url]'), function (fold) {
            fold.addEventListener('toggle', function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                var field = document.querySelector('input[name="ezxform_token"]');
                var headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' };
                if (meta) { headers['X-CSRF-Token'] = meta.getAttribute('content'); }
                try {
                    fetch(fold.getAttribute('data-preference-url') + '/' + (fold.open ? '1' : '0'), {
                        method: 'POST', credentials: 'same-origin', redirect: 'manual', headers: headers,
                        body: field ? 'ezxform_token=' + encodeURIComponent(field.value) : ''
                    });
                } catch (err) {}
            });
        });

        // Only the commits some remote does not have yet.
        var only = document.getElementById('gm-only-unpushed');
        var log = document.getElementById('gm-log');
        if (only && log) {
            only.addEventListener('change', function () { log.classList.toggle('is-only-unpushed', this.checked); });
        }

        // Quick search of the commits on the page.
        var find = document.getElementById('gm-log-find'), none = document.getElementById('gm-log-none');
        if (find && log) {
            find.addEventListener('input', function () {
                var q = this.value.trim().toLowerCase(), shown = 0;
                each(log.children, function (li) {
                    var match = q === '' || li.getAttribute('data-find').toLowerCase().indexOf(q) !== -1;
                    li.hidden = !match;
                    if (match) { shown++; }
                });
                if (none) { none.hidden = shown !== 0; }
            });
            // Enter in the quick search filters the page, it does not post the form.
            find.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        }

        // The Backup page: select all, the count, the confirmations.
        var backup = document.querySelector('.a4-gm-backup');
        if (backup) {
            var all = backup.querySelector('.a4-gm-select-all');
            var boxes = backup.querySelectorAll('.a4-gm-caption-select');
            var count = backup.querySelector('.a4-gm-selected-count');
            var selected = function () { var n = 0; each(boxes, function (b) { if (b.checked) { n++; } }); return n; };
            var update = function () {
                var n = selected();
                if (count) { count.textContent = text('selected').replace('%count', n); }
                if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
            };
            if (all) { all.addEventListener('change', function () { each(boxes, function (b) { b.checked = all.checked; }); update(); }); }
            each(boxes, function (b) { b.addEventListener('change', update); });
            update();
            backup.addEventListener('click', function (e) {
                var b = closest(e.target, '[data-gm-ask]');
                if (!b) { return; }
                var kind = b.getAttribute('data-gm-ask'), message;
                if (kind === 'delete-many') {
                    var n = selected();
                    if (n === 0) { e.preventDefault(); return; }
                    message = text('confirm-delete-many').replace('%count', n);
                } else if (kind === 'delete-one') {
                    message = text('confirm-delete-one').replace('%name', b.getAttribute('data-gm-name'));
                } else {
                    message = text('confirm-create');
                }
                if (!window.confirm(message)) { e.preventDefault(); }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
