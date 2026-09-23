/* GoalSpace module: small shared UI (bookings search, promo copy, review helpful).
   Loaded on every page via footer after core.js. */
document.addEventListener('DOMContentLoaded', function () {
    /* Live search on bookings lists (filters .mbooking cards via data-search) */
    function wireBookingsSearch(inputId, clearId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const clearBtn = document.getElementById(clearId);
        const grid = document.querySelector('.mbookings');
        if (!grid) return;
        const items = grid.querySelectorAll('.mbooking');
        function filter() {
            const q = (input.value || '').toLowerCase().trim();
            if (!q) { items.forEach(function (c) { c.style.display = ''; }); return; }
            items.forEach(function (card) {
                const txt = (card.getAttribute('data-search') || '').toLowerCase();
                card.style.display = txt.indexOf(q) === -1 ? 'none' : '';
            });
        }
        input.addEventListener('input', filter);
        if (clearBtn) {
            clearBtn.addEventListener('click', function () { input.value = ''; filter(); input.focus(); });
        }
    }
    wireBookingsSearch('managerSearch', 'managerClear');
    wireBookingsSearch('adminSearch', 'adminClear');

    /* Promo code badge copy-to-clipboard */
    document.querySelectorAll('.promo-code-badge').forEach(function (badge) {
        badge.addEventListener('click', function () {
            const code = badge.getAttribute('data-code');
            if (!code) return;
            navigator.clipboard.writeText(code).then(function () {
                const orig = badge.textContent;
                badge.textContent = 'Copied!';
                badge.style.background = 'var(--brand)';
                badge.style.color = '#fff';
                setTimeout(function () {
                    badge.textContent = orig;
                    badge.style.background = '';
                    badge.style.color = '';
                }, 1200);
            });
        });
    });
    /* end promo badges */

    /* Review helpful voting */
    document.querySelectorAll('.helpful-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = form.querySelector('.helpful-btn');
            if (btn.disabled) return;
            btn.disabled = true;
            const fd = new FormData(form);
            const url = form.action;
            fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        btn.classList.add('helped');
                        btn.disabled = true;
                        btn.setAttribute('aria-label', 'You found this helpful');
                        const countEl = btn.querySelector('.helpful-count');
                        if (countEl) countEl.textContent = data.count;
                    } else {
                        btn.disabled = false;
                        openErrorModal(data.error || 'Could not vote', 'Could not vote');
                    }
                })
                .catch(function () { btn.disabled = false; });
        });
    });
    /* end review helpful */
});
