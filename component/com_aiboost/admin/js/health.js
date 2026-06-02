/**
 * AI Boost — Health Checker JS
 * Handles AJAX re-run, dismiss/restore, expand/collapse, and copy-report.
 * @package AiBoost
 */
(function () {
    'use strict';

    /* ── Helper: action message bar ─────────────────────────────────────────── */

    function setActionMsg(msg, type) {
        var el = document.getElementById('ab-hc-action-msg');
        if (!el) { return; }
        el.textContent = msg;
        el.className   = 'mt-2 ' + (type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-muted');
        el.style.fontSize = '.8rem';
        if (msg) {
            setTimeout(function () { el.textContent = ''; el.className = 'mt-2'; }, 4000);
        }
    }

    /* ── Helpers: score display ──────────────────────────────────────────────── */

    function scoreClass(s) {
        if (s >= 80) { return 'ab-hc-score--green'; }
        if (s >= 50) { return 'ab-hc-score--orange'; }
        return 'ab-hc-score--red';
    }

    function scoreLabel(s) {
        if (s >= 80) { return 'Good'; }
        if (s >= 50) { return 'Needs Work'; }
        return 'Critical Issues';
    }

    function rowClass(check) {
        if (check.dismissed)                             { return 'ab-hc-row--dismissed'; }
        if (check.pass || check.status === 'info')       { return 'ab-hc-row--pass'; }
        if (check.status === 'critical')                 { return 'ab-hc-row--fail-critical'; }
        return 'ab-hc-row--fail-warning';
    }

    function rowIcon(check) {
        if (check.dismissed)                             { return 'icon-minus-circle ab-hc-icon--dismissed'; }
        if (check.pass || check.status === 'info')       { return 'icon-checkmark-circle ab-hc-icon--pass'; }
        if (check.status === 'critical')                 { return 'icon-warning ab-hc-icon--critical'; }
        return 'icon-info-circle ab-hc-icon--warning';
    }

    /* ── Update UI from fresh check data ──────────────────────────────────── */

    function applyChecksToUI(score, checks) {
        var circle = document.getElementById('ab-hc-score-circle');
        var num    = document.getElementById('ab-hc-score-num');
        var lbl    = document.getElementById('ab-hc-score-label');
        if (circle && num && lbl) {
            circle.className = 'ab-hc-score ' + scoreClass(score);
            num.textContent  = score;
            lbl.textContent  = scoreLabel(score);
        }

        var summary   = document.getElementById('ab-hc-score-summary');
        var critFails = checks.filter(function (c) { return c.status === 'critical' && !c.pass && !c.dismissed; }).length;
        var warnFails = checks.filter(function (c) { return c.status === 'warning'  && !c.pass && !c.dismissed; }).length;
        if (summary) {
            var parts = [];
            if (critFails) { parts.push(critFails + ' critical issue' + (critFails > 1 ? 's' : '')); }
            if (warnFails) { parts.push(warnFails + ' warning' + (warnFails > 1 ? 's' : '')); }
            summary.textContent = parts.length ? parts.join(', ') + ' found.' : 'All checks passed.';
        }

        checks.forEach(function (check) {
            var row = document.getElementById('ab-hc-row-' + check.id);
            if (!row) { return; }

            row.className    = 'ab-hc-row ' + rowClass(check);
            row.dataset.pass = check.pass ? '1' : '0';

            var icon = row.querySelector('.ab-hc-row-icon');
            if (icon) { icon.className = rowIcon(check) + ' ab-hc-row-icon flex-shrink-0'; }

            var msgEl = row.querySelector('.ab-hc-row-msg');
            if (msgEl) { msgEl.textContent = check.message; }

            var dismissBtn = row.querySelector('.ab-hc-dismiss-btn');
            if (dismissBtn && check.status !== 'info') {
                dismissBtn.setAttribute('data-action', check.dismissed ? 'restore' : 'dismiss');
                dismissBtn.setAttribute('title', check.dismissed ? 'Restore this check' : 'Dismiss this check');
                dismissBtn.textContent = check.dismissed ? 'Restore' : 'Dismiss';
            }

            var fixBtn = row.querySelector('.ab-hc-fix-btn');
            if (fixBtn) {
                fixBtn.style.display = (!check.pass && !check.dismissed && check.fix_url) ? '' : 'none';
            }
        });

        var critTotal = checks.filter(function (c) { return c.status === 'critical'; }).length;
        var critOk    = critTotal - checks.filter(function (c) { return c.status === 'critical' && !c.pass; }).length;
        var warnTotal = checks.filter(function (c) { return c.status === 'warning'; }).length;
        var warnOk    = warnTotal - checks.filter(function (c) { return c.status === 'warning' && !c.pass; }).length;

        var critBadge = document.getElementById('ab-hc-stat-critical');
        var warnBadge = document.getElementById('ab-hc-stat-warning');
        if (critBadge) {
            critBadge.textContent = 'Critical: ' + critOk + '/' + critTotal + ' OK';
            critBadge.className   = 'badge bg-' + (critFails ? 'danger' : 'success');
        }
        if (warnBadge) {
            warnBadge.textContent = 'Warnings: ' + warnOk + '/' + warnTotal + ' OK';
            warnBadge.className   = 'badge bg-' + (warnFails ? 'warning' : 'success');
        }

        var catFails = {};
        checks.forEach(function (c) {
            if (c.status === 'info') { return; }
            var cat = c.category || 'General';
            if (!catFails[cat]) { catFails[cat] = 0; }
            if (!c.pass && !c.dismissed) { catFails[cat]++; }
        });
        Object.keys(catFails).forEach(function (cat) {
            var badge = document.getElementById('ab-hc-cat-badge-' + cat);
            if (!badge) { return; }
            var fails = catFails[cat];
            badge.textContent = fails ? fails + ' issue' + (fails > 1 ? 's' : '') : 'All OK';
            badge.className   = 'badge ms-1 bg-' + (fails ? 'danger' : 'success');
        });
    }

    /* ── Clipboard fallback ──────────────────────────────────────────────────── */

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left     = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            setActionMsg('Report copied to clipboard.', 'success');
        } catch (e) {
            setActionMsg('Could not copy — please select and copy manually.', 'error');
        }
        document.body.removeChild(ta);
    }

    /* ── DOM-ready init ──────────────────────────────────────────────────────── */

    document.addEventListener('DOMContentLoaded', function () {

        var wrap = document.getElementById('ab-health-wrap');
        if (!wrap) { return; }

        var tokenName = wrap.getAttribute('data-token-name') || '';

        /* ── Re-run ────────────────────────────────────────────────────────── */

        var rerunBtn = document.getElementById('ab-hc-rerun-btn');
        if (rerunBtn) {
            rerunBtn.addEventListener('click', function () {
                rerunBtn.disabled = true;
                rerunBtn.innerHTML = '<span class="icon-refresh me-1" aria-hidden="true" style="animation:spin 1s linear infinite;display:inline-block"></span> Running\u2026';
                setActionMsg('Running checks\u2026', '');

                var fd = new FormData();
                fd.append(tokenName, '1');

                fetch('index.php?option=com_aiboost&task=health.rerun&format=json', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        // Reload the page so the server-rendered health view reflects new results
                        location.reload();
                    } else {
                        setActionMsg(data.message || 'Error running checks.', 'error');
                        rerunBtn.disabled = false;
                        rerunBtn.innerHTML = '<span class="icon-refresh me-1" aria-hidden="true"></span> Re-run Checks';
                    }
                })
                .catch(function () {
                    setActionMsg('Network error. Please try again.', 'error');
                    rerunBtn.disabled = false;
                    rerunBtn.innerHTML = '<span class="icon-refresh me-1" aria-hidden="true"></span> Re-run Checks';
                });
            });
        }

        /* ── Dismiss / Restore ─────────────────────────────────────────────── */

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.ab-hc-dismiss-btn');
            if (!btn) { return; }

            var checkId = btn.getAttribute('data-check-id');
            var action  = btn.getAttribute('data-action') || 'dismiss';
            if (!checkId) { return; }

            btn.disabled    = true;
            btn.textContent = action === 'dismiss' ? 'Dismissing\u2026' : 'Restoring\u2026';

            var formData = new FormData();
            formData.set(tokenName, '1');
            formData.set('check_id', checkId);
            formData.set('action', action);

            fetch('index.php?option=com_aiboost&task=health.dismiss&format=json', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    setActionMsg(data.message || 'Error saving.', 'error');
                    btn.disabled    = false;
                    btn.textContent = action === 'dismiss' ? 'Dismiss' : 'Restore';
                    return;
                }

                var row = document.getElementById('ab-hc-row-' + checkId);
                if (row) {
                    var isDismissed = action === 'dismiss';
                    var pass        = row.dataset.pass === '1';
                    var status      = row.dataset.status || 'warning';

                    if (isDismissed) {
                        row.className = 'ab-hc-row ab-hc-row--dismissed';
                    } else if (pass || status === 'info') {
                        row.className = 'ab-hc-row ab-hc-row--pass';
                    } else if (status === 'critical') {
                        row.className = 'ab-hc-row ab-hc-row--fail-critical';
                    } else {
                        row.className = 'ab-hc-row ab-hc-row--fail-warning';
                    }

                    var icon = row.querySelector('.ab-hc-row-icon');
                    if (icon) {
                        var ic = isDismissed
                            ? 'icon-minus-circle ab-hc-icon--dismissed'
                            : (pass || status === 'info')
                                ? 'icon-checkmark-circle ab-hc-icon--pass'
                                : (status === 'critical' ? 'icon-warning ab-hc-icon--critical' : 'icon-info-circle ab-hc-icon--warning');
                        icon.className = ic + ' ab-hc-row-icon flex-shrink-0';
                    }

                    btn.setAttribute('data-action', isDismissed ? 'restore' : 'dismiss');
                    btn.setAttribute('title', isDismissed ? 'Restore this check' : 'Dismiss this check');
                    btn.textContent = isDismissed ? 'Restore' : 'Dismiss';
                    btn.disabled    = false;

                    var fixBtn = row.querySelector('.ab-hc-fix-btn');
                    if (fixBtn) { fixBtn.style.display = isDismissed ? 'none' : ''; }
                }

                setActionMsg(action === 'dismiss' ? 'Check dismissed.' : 'Check restored.', 'success');
            })
            .catch(function () {
                setActionMsg('Network error.', 'error');
                btn.disabled    = false;
                btn.textContent = action === 'dismiss' ? 'Dismiss' : 'Restore';
            });
        });

        /* ── Fix-it link: Settings tab deep-link ──────────────────────────── */

        document.addEventListener('click', function (e) {
            var link = e.target.closest('.ab-hc-fix-btn');
            if (!link) { return; }
            var href = link.getAttribute('href') || '';
            var hash = href.indexOf('#') !== -1 ? href.split('#')[1] : '';
            if (hash && href.indexOf('view=settings') !== -1) {
                sessionStorage.setItem('ab_settings_tab', hash);
            }
        });

        /* ── Export / Copy report ──────────────────────────────────────────── */

        var exportBtn = document.getElementById('ab-hc-export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                var numEl    = document.getElementById('ab-hc-score-num');
                var scoreTxt = numEl ? numEl.textContent + '/100' : '?/100';

                var lines = [
                    'AI Boost for Joomla \u2014 Health Report',
                    'Score: ' + scoreTxt,
                    'Generated: ' + new Date().toLocaleString(),
                    '============================================================',
                ];

                var categoryOrder = ['General', 'Schema', 'Sitemap', 'Social', 'Analytics', 'AEO', 'License'];
                categoryOrder.forEach(function (cat) {
                    var rows = document.querySelectorAll('[data-category="' + cat + '"]');
                    if (!rows.length) { return; }
                    lines.push('');
                    lines.push('\u2500\u2500 ' + cat.toUpperCase() + ' \u2500\u2500');
                    rows.forEach(function (row) {
                        var label     = (row.querySelector('.ab-hc-row-label') || {}).textContent || '';
                        var msg       = (row.querySelector('.ab-hc-row-msg') || {}).textContent || '';
                        var pass      = row.dataset.pass === '1';
                        var dismissed = row.classList.contains('ab-hc-row--dismissed');
                        var status    = dismissed ? '[DISMISSED]' : (pass ? '[OK]' : '[FAIL]');
                        lines.push(status + ' ' + label.replace(/\s+/g, ' ').trim() + ': ' + msg.trim());
                    });
                });
                lines.push('');
                lines.push('\u2014 AI Boost (aiboostnow.com)');

                var text = lines.join('\n');

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        setActionMsg('Report copied to clipboard.', 'success');
                    }).catch(function () {
                        fallbackCopy(text);
                    });
                } else {
                    fallbackCopy(text);
                }
            });
        }

    }); // end DOMContentLoaded

}());
