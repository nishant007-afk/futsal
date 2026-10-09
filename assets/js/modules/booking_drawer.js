/* GoalSpace module: slide-over booking viewer for the manager and admin bookings list.

   Opted into per table by bookings_table_html(['drawer' => true]), which adds
   data-booking-drawer="<id>" to the row's details link. The href is left intact, so the
   link still navigates when JS is unavailable, on middle-click, or for the roles that keep
   the full page (player My Bookings, the dashboards). This module only upgrades the
   left-click case into a drawer.

   The panel is built on first use and injected once, so nothing depends on markup that only
   exists on the bookings pages. */
(function () {
    'use strict';

    var STATE_KEY = 'bookingDrawer';
    var els = null;
    var currentId = null;
    var lastFocused = null;
    var pushedEntry = false;
    var token = 0;

    function build() {
        if (els) return;

        var root = document.createElement('div');
        root.className = 'bdrawer';
        root.innerHTML =
            '<div class="bdrawer-scrim" data-bdrawer-close></div>' +
            '<aside class="bdrawer-panel" role="dialog" aria-modal="true" aria-labelledby="bdrawerTitle" tabindex="-1">' +
                '<header class="bdrawer-head">' +
                    '<div class="bdrawer-heading">' +
                        '<span class="bdrawer-kicker">Booking details</span>' +
                        '<h2 class="bdrawer-title" id="bdrawerTitle">Loading</h2>' +
                    '</div>' +
                    '<button type="button" class="bdrawer-close" data-bdrawer-close aria-label="Close booking details">' +
                        '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' +
                    '</button>' +
                '</header>' +
                '<div class="bdrawer-body">' +
                    '<div class="bdrawer-loading"><span class="bdrawer-spinner" aria-hidden="true"></span>Loading booking</div>' +
                '</div>' +
            '</aside>';

        document.body.appendChild(root);

        els = {
            root: root,
            panel: root.querySelector('.bdrawer-panel'),
            body: root.querySelector('.bdrawer-body'),
            title: root.querySelector('.bdrawer-title')
        };

        root.addEventListener('click', function (e) {
            if (e.target.closest('[data-bdrawer-close]')) {
                e.preventDefault();
                close();
            }
        });

        // Keep Tab inside the panel while it is open.
        els.panel.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            var f = focusables();
            if (!f.length) return;
            var first = f[0];
            var last = f[f.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === els.panel)) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && currentId !== null) {
                e.preventDefault();
                close();
            }
        });
    }

    function focusables() {
        return Array.prototype.slice.call(els.panel.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]),' +
            ' textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) {
            return el.tagName === 'IFRAME' || el.offsetWidth > 0 || el.offsetHeight > 0;
        });
    }

    function lockBody(on) {
        var gap = window.innerWidth - document.documentElement.clientWidth;
        document.body.classList.toggle('bdrawer-locked', !!on);
        document.body.style.paddingRight = (on && gap > 0) ? gap + 'px' : '';
    }

    function teardown() {
        currentId = null;
        token++;
        if (els) {
            els.root.classList.remove('is-open');
        }
        lockBody(false);
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    function render(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var wrap = doc.querySelector('.bd-wrap');
        if (!wrap) throw new Error('no booking content');

        // The panel has its own close affordances, so the page's back link/arrow is redundant.
        var back = wrap.querySelector('.bd-back-link, .page-back-arrow');
        if (back) back.remove();

        // .reveal starts at opacity 0 and is un-hidden by the reveal-on-scroll observer in
        // core.js, which only ever observes the initial document. Content injected after
        // load would stay invisible, so mark it revealed here. The wrapper itself is the
        // .reveal element on this page, hence both calls.
        if (wrap.classList.contains('reveal')) wrap.classList.add('visible');
        Array.prototype.forEach.call(wrap.querySelectorAll('.reveal'), function (el) {
            el.classList.add('visible');
        });

        els.title.textContent = (wrap.querySelector('.bd-venue-title') || {}).textContent || 'Booking details';
        els.body.innerHTML = '';
        els.body.appendChild(wrap);
        els.body.scrollTop = 0;
    }

    function fail(url) {
        els.title.textContent = 'Could not load';
        els.body.innerHTML =
            '<div class="bdrawer-error">' +
                '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>' +
                '<p>That booking could not be loaded.</p>' +
                '<a class="btn btn-outline btn-sm" href="' + url + '">Open full page</a>' +
            '</div>';
    }

    function open(id, url, trigger) {
        build();
        var mine = ++token;
        currentId = id;
        lastFocused = trigger && document.contains(trigger) ? trigger : document.activeElement;

        els.root.classList.add('is-open');
        els.title.textContent = 'Loading';
        els.body.innerHTML = '<div class="bdrawer-loading"><span class="bdrawer-spinner" aria-hidden="true"></span>Loading booking</div>';
        lockBody(true);
        els.panel.focus();

        // Fetch the trigger's own href rather than rebuilding the path: the bookings list
        // lives at /manager/bookings.php and /admin/bookings.php, so a relative
        // "pages/booking_details.php" would resolve under those directories and 404.
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                if (mine === token) render(html);
            })
            .catch(function () {
                if (mine === token) fail(url);
            });
    }

    function close() {
        if (currentId === null) return;
        teardown();
        if (pushedEntry) {
            // Consume the entry we pushed so Back does not resurrect a closed drawer.
            pushedEntry = false;
            history.back();
        }
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-booking-drawer]');
        if (!link) return;
        // Leave modified clicks and non-primary buttons to the browser.
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

        var id = link.getAttribute('data-booking-drawer');
        if (!/^\d+$/.test(id)) return;
        e.preventDefault();
        history.pushState({ [STATE_KEY]: id }, '', link.href);
        pushedEntry = true;
        open(id, link.href, link);
    });

    window.addEventListener('popstate', function (e) {
        var state = e.state;
        if (state && state[STATE_KEY]) {
            // Forward navigation, or Back onto the drawer entry from somewhere else.
            var id = state[STATE_KEY];
            var link = document.querySelector('[data-booking-drawer="' + id + '"]');
            pushedEntry = false;
            if (link) open(id, link.href, link);
            return;
        }
        // Back out of the drawer, or past the list: just put the page back.
        pushedEntry = false;
        if (currentId !== null) teardown();
    });
})();