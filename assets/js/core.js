/* GoalSpace core.js
   Shared runtime loaded on every page: skeleton loader, panels, modals,
   toasts, top bar, cookie consent, scroll helpers. Page-specific modules
   live in assets/js/modules/ and are loaded only where needed. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.has-error input, .has-error textarea, .has-error select').forEach(function (el) {
        el.setAttribute('aria-invalid', 'true');
    });

    const pageSkeleton = document.getElementById('pageSkeleton');
    const skeletonMsg = document.getElementById('skeletonMsg');
    const skeletonMsgText = document.getElementById('skeletonMsgText');
    const appLoader = document.getElementById('appLoader');
    const alText = document.getElementById('alText');
    let skeletonShown = false;
    let skeletonTimers = [];
    function setSkeletonMsg(text) {
        if (skeletonMsgText) skeletonMsgText.textContent = text;
    }
    function showSkeletonMsg() {
        if (skeletonMsg) skeletonMsg.classList.add('show');
    }
    function hideSkeletonMsg() {
        if (skeletonMsg) skeletonMsg.classList.remove('show');
    }
    function showLoader() {
        if (appLoader && !appLoader.classList.contains('active')) {
            appLoader.classList.add('active');
        }
    }
    function hideLoader() {
        if (appLoader) appLoader.classList.remove('active');
    }
    window.showLoader = showLoader;
    window.hideLoader = hideLoader;
    function hideSkeleton() {
        skeletonTimers.forEach(function (t) { clearTimeout(t); });
        skeletonTimers = [];
        hideSkeletonMsg();
        hideLoader();
        if (skeletonShown) {
            pageSkeleton.classList.add('hide');
            setTimeout(function () { pageSkeleton.remove(); }, 400);
        } else {
            pageSkeleton.remove();
        }
    }
    if (pageSkeleton) {
        // If there's an inline flash toast (e.g. "Welcome back" after login), skip
        // the skeleton so it doesn't cover the message before it auto-dismisses.
        if (document.querySelector('.toast-inline')) {
            pageSkeleton.remove();
        } else {
        // <300ms: show no loader. Wait for a quick paint, then only show skeleton if still loading.
        skeletonTimers.push(setTimeout(function () {
            if (!pageSkeleton.isConnected) return;
            if (document.readyState === 'complete') { pageSkeleton.remove(); return; }
            pageSkeleton.classList.add('visible');
            skeletonShown = true;
            // 300ms–3s: skeleton alone. Past 3s, add a subtle loading message.
            skeletonTimers.push(setTimeout(function () {
                if (!skeletonShown || !pageSkeleton.isConnected) return;
                showSkeletonMsg();
            }, 3000));
            // >5s: escalate skeleton to the spinner loader with a descriptive message.
            skeletonTimers.push(setTimeout(function () {
                if (!skeletonShown || !pageSkeleton.isConnected) return;
                if (alText) alText.textContent = 'Still working on it';
                showLoader();
            }, 5000));
        }, 120));
        if (document.readyState === 'complete') {
            hideSkeleton();
        } else {
            window.addEventListener('load', hideSkeleton);
            setTimeout(hideSkeleton, 12000);
        }
        }
    }

    const greetingEl = document.getElementById('greeting');
    function updateGreeting() {
        if (!greetingEl) return;
        const h = new Date().getHours();
        const g = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
        if (greetingEl.textContent !== g) greetingEl.textContent = g;
    }
    updateGreeting();
    setInterval(updateGreeting, 60000);

    const navToggle = document.getElementById('navToggle');
    const mainNav = document.getElementById('mainNav');
    let setNavOpen = null;
    if (navToggle && mainNav) {
        let navLockY = 0;
        setNavOpen = function (open) {
            mainNav.classList.toggle('open', open);
            document.body.classList.toggle('nav-open', open);
            document.body.classList.toggle('nav-locked', open);
            navToggle.innerHTML = open ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>';
            if (open) {
                navLockY = window.scrollY;
                document.body.style.top = '-' + navLockY + 'px';
            } else {
                document.body.style.top = '';
                window.scrollTo(0, navLockY);
            }
        }
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (bellWrap && bellWrap.classList.contains('open')) bellWrap.classList.remove('open');
            if (profileWrap && profileWrap.classList.contains('open')) {
                profileWrap.classList.remove('open');
                if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
            }
            if (typeof refreshPanelBackdrop === 'function') refreshPanelBackdrop();
            setNavOpen(!mainNav.classList.contains('open'));
        });
        document.addEventListener('click', function (e) {
            if (mainNav.classList.contains('open') && !mainNav.contains(e.target) && e.target !== navToggle) {
                setNavOpen(false);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mainNav.classList.contains('open')) setNavOpen(false);
        });
    }

    function refreshPanelBackdrop() {
        const anyOpen = !!(bellWrap && bellWrap.classList.contains('open')) || !!(profileWrap && profileWrap.classList.contains('open'));
        document.body.classList.toggle('panel-open', anyOpen);
    }

    const bellBtn = document.getElementById('bellBtn');
    const bellWrap = document.getElementById('bellWrap');
    if (bellBtn && bellWrap) {
        bellBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (setNavOpen && mainNav && mainNav.classList.contains('open')) setNavOpen(false);
            if (profileWrap && profileWrap.classList.contains('open')) {
                profileWrap.classList.remove('open');
                if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
            }
            bellWrap.classList.toggle('open');
            refreshPanelBackdrop();
        });
        document.addEventListener('click', function (e) {
            if (!bellWrap.contains(e.target)) { bellWrap.classList.remove('open'); refreshPanelBackdrop(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && bellWrap.classList.contains('open')) { bellWrap.classList.remove('open'); refreshPanelBackdrop(); }
        });
    }

    const profileBtn = document.getElementById('profileBtn');
    const profileWrap = document.getElementById('profileWrap');
    if (profileBtn && profileWrap) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (setNavOpen && mainNav && mainNav.classList.contains('open')) setNavOpen(false);
            if (bellWrap && bellWrap.classList.contains('open')) bellWrap.classList.remove('open');
            profileWrap.classList.toggle('open');
            profileBtn.setAttribute('aria-expanded', profileWrap.classList.contains('open') ? 'true' : 'false');
            refreshPanelBackdrop();
        });
        document.addEventListener('click', function (e) {
            if (!profileWrap.contains(e.target)) {
                profileWrap.classList.remove('open');
                profileBtn.setAttribute('aria-expanded', 'false');
                refreshPanelBackdrop();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                profileWrap.classList.remove('open');
                profileBtn.setAttribute('aria-expanded', 'false');
                refreshPanelBackdrop();
            }
        });
    }

    const navDrop = document.querySelector('.nav-drop');
    if (navDrop) {
        const navDropLink = navDrop.querySelector('.nav-link');
        if (navDropLink) {
            navDropLink.addEventListener('click', function (e) {
                e.preventDefault();
                const wasOpen = navDrop.classList.contains('open');
                navDrop.classList.remove('open');
                if (!wasOpen) navDrop.classList.add('open');
            });
        }
        document.addEventListener('click', function (e) {
            if (!navDrop.contains(e.target)) navDrop.classList.remove('open');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') navDrop.classList.remove('open');
        });
    }

    const siteHeader = document.querySelector('.site-header');
    const bottomNav = document.getElementById('bottomNav');
    if (siteHeader) {
        let lastY = window.scrollY;

        // Only treat scrolls that follow a real user gesture (wheel/touch/keyboard)
        // as "user is scrolling". Programmatic scrolls (in-page anchor links like
        // index.php#how, scroll restoration) must never hide the navigation.
        let lastUserScroll = 0;
        function markUserScroll() { lastUserScroll = Date.now(); }
        const scrollKeys = [' ', 'ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End'];
        window.addEventListener('wheel', markUserScroll, { passive: true });
        window.addEventListener('touchmove', markUserScroll, { passive: true });
        window.addEventListener('keydown', function (e) {
            if (scrollKeys.indexOf(e.key) !== -1) markUserScroll();
        }, { passive: true });

        function isUserScrolling() {
            return Date.now() - lastUserScroll < 500;
        }
        function isCompactHeader() {
            return !window.matchMedia('(min-width: 821px)').matches;
        }
        function onScroll() {
            const y = window.scrollY;
            const delta = y - lastY;
            if (delta > 6 && isUserScrolling()) {
                // scrolling down -> hide the top navbar up and the mobile bottom nav down
                siteHeader.classList.add('collapsed');
                if (bottomNav) { bottomNav.classList.add('hidden'); document.body.classList.add('nav-hidden'); }
                // mobile only: dismiss the slide-in nav drawer if open
                if (isCompactHeader() && mainNav && mainNav.classList.contains('open') && typeof setNavOpen === 'function') {
                    setNavOpen(false);
                    document.body.classList.remove('nav-open');
                }
            } else if ((delta < -6 && isUserScrolling()) || y <= 8) {
                // scrolling up (or near the top) -> reveal the top navbar and bottom nav
                siteHeader.classList.remove('collapsed');
                if (bottomNav) { bottomNav.classList.remove('hidden'); document.body.classList.remove('nav-hidden'); }
            }
            lastY = y;
        }
        window.addEventListener('scroll', onScroll, { passive: true });

        /* Mobile: tap/click on a blank area toggles both the top and bottom nav.
           If they are hidden, one tap reveals them; if revealed, one tap hides them. */
        document.addEventListener('click', function (e) {
            if (!window.matchMedia('(max-width: 820px)').matches) return;
            if (!bottomNav) return;
            if (Date.now() - lastUserScroll < 500) return;
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.closest('.site-header') || t.closest('.bottom-nav')) return;
            if (t.closest('a, button, input, textarea, select, label, [data-confirm], .otp-box')) return;
            if (t.closest('.toast, .modal, .popup-backdrop, .cookie-banner, .drawer')) return;
            const hidden = siteHeader.classList.contains('collapsed') && bottomNav.classList.contains('hidden');
            if (hidden) {
                hideTopBar();
                siteHeader.classList.remove('collapsed');
                bottomNav.classList.remove('hidden');
                document.body.classList.remove('nav-hidden');
            } else {
                siteHeader.classList.add('collapsed');
                bottomNav.classList.add('hidden');
                document.body.classList.add('nav-hidden');
            }
        });
    }

    const reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        reveals.forEach(function (el) { revealObserver.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('visible'); });
    }

    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openConfirmModal(el.dataset.confirm, function () {
                showLoader();
                if (el.tagName === 'A') {
                    window.location.href = el.href;
                } else if (el.tagName === 'BUTTON' && el.type === 'submit') {
                    el.closest('form').submit();
                }
            }, {
                okText: el.dataset.confirmOk || 'Yes, continue',
                cancelText: el.dataset.confirmCancel || 'Cancel'
            });
        });
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const sub = e.submitter;
            const btn = sub && sub.matches('button[type="submit"]')
                ? sub
                : form.querySelector('button[type="submit"]');
            if (btn && !btn.classList.contains('btn-loading')) {
                btn.classList.add('btn-loading');
                btn.setAttribute('disabled', 'disabled');
            }
        });
    });

    // ----- Floating labels + gated CTA buttons ---------------------------
    // Both react to typing AND to browser autofill (which often fires no
    // input/change event). Central here so every page behaves the same.
    function syncFloatingLabels() {
        document.querySelectorAll('.input-group.floating input').forEach(function (el) {
            const on = el.value.trim() !== '';
            if (el.classList.contains('has-value') !== on) {
                el.classList.toggle('has-value', on);
            }
        });
    }

    function autogateAll() {
        document.querySelectorAll('button[data-autogate]').forEach(function (btn) {
            const form = btn.closest('form');
            if (!form) return;
            let ok = true;
            form.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (el) {
                if (el.disabled) return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (!el.checked) ok = false;
                } else if (!el.value || !el.value.trim()) {
                    ok = false;
                }
            });
            if (btn.disabled === ok) btn.disabled = !ok;
        });
    }

    function autosync() {
        syncFloatingLabels();
        autogateAll();
    }

    document.addEventListener('input', autosync, true);
    document.addEventListener('change', autosync, true);
    document.addEventListener('click', autosync, true);
    document.addEventListener('focusin', autogateAll);
    document.addEventListener('animationstart', function (e) {
        if (e.animationName === 'autofill') autosync();
    });
    window.addEventListener('load', autosync);
    window.addEventListener('pageshow', autosync);

    // Autofill can land a moment after load; keep polling briefly.
    var recheckTries = 0;
    var recheck = setInterval(function () {
        recheckTries++;
        autosync();
        if (recheckTries >= 30) clearInterval(recheck);
    }, 100);
    autosync();

    // ----- OTP boxes (one digit per box) ------------------------------
    // Single shared behaviour: auto-advance on typing, go back with
    // backspace, accept paste, keyboard arrows. Values live in a hidden
    // `.otp-source` input so server-side code keeps working unchanged.
    document.querySelectorAll('.otp-boxes').forEach(function (wrap) {
        const form = wrap.closest('form');
        const source = form && form.querySelector('.otp-source');
        if (!source) return;
        const boxes = Array.prototype.slice.call(wrap.querySelectorAll('.otp-box'));
        if (boxes.length === 0) return;

        function readBoxes() {
            source.value = boxes.map(function (b) { return b.value; }).join('');
        }
        function writeBoxes() {
            for (let i = 0; i < boxes.length; i++) {
                boxes[i].value = source.value[i] ? source.value[i] : '';
            }
        }
        function emitChange() {
            source.dispatchEvent(new Event('input', { bubbles: true }));
            source.dispatchEvent(new Event('change', { bubbles: true }));
        }

        boxes.forEach(function (box, i) {
            box.addEventListener('input', function () {
                box.value = box.value.replace(/[^0-9]/g, '').slice(0, 1);
                readBoxes();
                emitChange();
                if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
            });
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace') {
                    if (box.value === '') {
                        if (i > 0) {
                            e.preventDefault();
                            boxes[i - 1].value = '';
                            boxes[i - 1].focus();
                            readBoxes();
                            emitChange();
                        }
                    } else {
                        box.value = '';
                        readBoxes();
                        emitChange();
                    }
                }
                if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); boxes[i - 1].focus(); }
                if (e.key === 'ArrowRight' && i < boxes.length - 1) { e.preventDefault(); boxes[i + 1].focus(); }
            });
            box.addEventListener('focus', function () { box.select(); });
            box.addEventListener('paste', function (e) {
                e.preventDefault();
                const digits = (e.clipboardData.getData('text') || '').replace(/[^0-9]/g, '');
                boxes.forEach(function (b) { b.value = ''; });
                digits.split('').forEach(function (ch, j) { if (j < boxes.length) boxes[j].value = ch; });
                readBoxes();
                emitChange();
                const last = Math.min(digits.length, boxes.length) - 1;
                boxes[last >= 0 ? last : 0].focus();
            });
        });

        wrap.addEventListener('click', function () {
            let idx = boxes.findIndex(function (b) { return b.value === ''; });
            if (idx === -1) idx = boxes.length - 1;
            boxes[idx].focus();
        });

        writeBoxes();
    });

    const markAllBtn = document.getElementById('bellMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = markAllBtn.closest('form');
            if (!form) return;
            markAllBtn.disabled = true;
            const fd = new FormData(form);
            if (markAllBtn.name) fd.append(markAllBtn.name, markAllBtn.value || '1');
            fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('request failed')); })
                .then(function () {
                    document.querySelectorAll('#bellPanel .bell-item.unread').forEach(function (item) {
                        item.classList.remove('unread');
                    });
                    document.querySelectorAll('#bellPanel .bell-unread-dot').forEach(function (d) { d.remove(); });
                    const dot = document.getElementById('bellDot');
                    if (dot) dot.remove();
                    const hc = document.querySelector('.bell-head-count');
                    if (hc) hc.remove();
                    if (typeof updateBellBadge === 'function') updateBellBadge(0);
                })
                .catch(function () { markAllBtn.disabled = false; });
        });
    }

    /* ---- Top loading bar (page navigation, ~1–2s) ---- */
    const topBar = document.getElementById('topBar');
    function showTopBar() {
        if (topBar) topBar.classList.add('loading');
    }
    function hideTopBar() {
        if (topBar) topBar.classList.remove('loading');
    }
    window.showTopBar = showTopBar;
    window.hideTopBar = hideTopBar;
    const internalLinks = Array.prototype.slice.call(document.querySelectorAll('a[href]:not([target="_blank"]):not([download]):not([data-no-bar])'))
        .filter(function (a) {
            const href = a.getAttribute('href') || '';
            if (href.charAt(0) === '#') return false;
            if (/^(https?:)?\/\//i.test(href)) return false;
            return true;
        })
        .filter(function (a) {
            let url;
            try { url = new URL(a.href, window.location.href); } catch (e) { return true; }
            const samePage = url.origin === window.location.origin && url.pathname === window.location.pathname;
            return !(samePage && url.hash);
        });
    internalLinks.forEach(function (a) {
        a.addEventListener('click', function () { showTopBar(); });
    });
    window.addEventListener('hashchange', hideTopBar);
    window.addEventListener('pagehide', hideTopBar);

    const alDots = document.getElementById('alDots');
    if (alDots) {
        let dotCount = 0;
        setInterval(function () {
            dotCount = dotCount % 3 + 1;
            alDots.textContent = '.'.repeat(dotCount);
        }, 500);
    }

    // Full-screen loader is reserved for startup / auth session verification.
    // Button actions use the inline spinner (btn-loading) and normal navigation uses the top bar.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.hasAttribute('data-no-loader') || form.classList.contains('no-loader')) return;
        setTimeout(function () {
            if (e.defaultPrevented) return;
            if (form.matches('.auth-form') || form.hasAttribute('data-fullscreen-loader') || document.body.classList.contains('auth-page')) {
                showLoader();
            } else {
                showTopBar();
            }
        }, 0);
    }, true);

    const cookieBanner = document.getElementById('cookieBanner');
    if (cookieBanner) {
        function consent(choice) {
            try { localStorage.setItem('futsal_cookie_consent', choice); } catch (e) {}
            cookieBanner.classList.remove('show');
        }
        const saved = (function () {
            try { return localStorage.getItem('futsal_cookie_consent'); } catch (e) { return null; }
        })();
        if (!saved) {
            setTimeout(function () { cookieBanner.classList.add('show'); }, 600);
        }
        const accept = document.getElementById('cookieAccept');
        const decline = document.getElementById('cookieDecline');
        if (accept) accept.addEventListener('click', function () { consent('accepted'); });
        if (decline) decline.addEventListener('click', function () { consent('declined'); });
    }

    const toasts = document.querySelectorAll('.toast');
    function dismissToast(t) {
        if (!t || t.classList.contains('hide')) return;
        t.classList.add('hide');
        setTimeout(function () { t.remove(); removeBackdropIfEmpty(); }, 250);
    }
    function removeBackdropIfEmpty() {
        if (!document.querySelector('.toast') && !document.querySelector('.modal')) {
            const b = document.querySelector('.popup-backdrop');
            if (b) b.remove();
        }
    }
    function showBackdrop() {
        if (!document.querySelector('.popup-backdrop')) {
            const b = document.createElement('div');
            b.className = 'popup-backdrop';
            document.body.appendChild(b);
        }
    }
    function openConfirmModal(message, onConfirm, labels) {
        labels = labels || {};
        showBackdrop();
        const esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML =
            '<div class="modal-head">' +
                '<span class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>' +
                '<h3>Are you sure?</h3>' +
            '</div>' +
            '<p class="modal-msg">' + esc(message) + '</p>' +
            '<div class="modal-actions">' +
                '<button type="button" class="btn btn-ghost" data-modal-cancel>' + esc(labels.cancelText || 'Cancel') + '</button>' +
                '<button type="button" class="btn btn-danger" data-modal-ok>' + esc(labels.okText || 'Yes, continue') + '</button>' +
            '</div>';
        document.body.appendChild(modal);

        function close() {
            modal.classList.add('hide');
            setTimeout(function () { modal.remove(); removeBackdropIfEmpty(); }, 220);
        }
        modal.querySelector('[data-modal-cancel]').addEventListener('click', close);
        modal.querySelector('[data-modal-ok]').addEventListener('click', function () {
            close();
            onConfirm();
        });
        modal.querySelector('[data-modal-cancel]').focus();
    }
    function openErrorModal(message, title) {
        showBackdrop();
        const esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        const modal = document.createElement('div');
        modal.className = 'modal modal-error';
        modal.innerHTML =
            '<button type="button" class="modal-x" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
            '<div class="modal-head">' +
                '<span class="modal-icon"><i class="fa-solid fa-circle-xmark"></i></span>' +
                '<h3>' + esc(title || 'Something went wrong') + '</h3>' +
            '</div>' +
            '<p class="modal-msg">' + esc(message) + '</p>';
        document.body.appendChild(modal);
        function close() {
            modal.classList.add('hide');
            setTimeout(function () { modal.remove(); removeBackdropIfEmpty(); }, 220);
        }
        modal.querySelector('[data-modal-close]').addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', handler); }
        });
        setTimeout(function () { modal.querySelector('[data-modal-close]').focus(); }, 10);
        return { close: close };
    }
    window.openErrorModal = openErrorModal;
    document.querySelectorAll('[data-error-modal-msg]').forEach(function (el) {
        const pws = document.querySelectorAll('.form-card input[type="password"]');
        if (pws.length) {
            pws.forEach(function (p) { p.classList.add('field-error'); });
            setTimeout(function () { if (pws[0]) pws[0].focus(); }, 150);
        }
        openErrorModal(el.getAttribute('data-error-modal-msg'), el.getAttribute('data-error-modal-title'));
    });
    toasts.forEach(function (t, i) {
        const close = t.querySelector('.toast-close');
        if (close) close.addEventListener('click', function () { dismissToast(t); });
        const isError = t.classList.contains('toast-error');
        const autoDismiss = t.classList.contains('toast-success') || t.classList.contains('toast-info');
        const isInline = t.classList.contains('toast-inline');
        if (isInline) {
            if (autoDismiss) setTimeout(function () { dismissToast(t); }, 2500);
        } else {
            showBackdrop();
            if (toasts.length > 1) {
                t.style.top = 'calc(50% + ' + ((i - (toasts.length - 1) / 2) * 64) + 'px)';
            }
            if (autoDismiss) setTimeout(function () { dismissToast(t); }, 2500);
        }
        if (isError) {
            highlightPasswordError(t);
        }
    });
    function highlightPasswordError(toast) {
        const card = toast.closest('.form-card');
        const scope = card || document;
        const pws = scope.querySelectorAll('input[type="password"]');
        if (!pws.length) return;
        pws.forEach(function (p) { p.classList.add('field-error'); });
        setTimeout(function () { pws[0].focus(); }, 120);
        pws.forEach(function (p) {
            p.addEventListener('input', function () { p.classList.remove('field-error'); }, { once: true });
        });
    }

    function clearRestoredPasswords() {
        document.querySelectorAll('input[type="password"]').forEach(function (p) {
            if (p.value) p.value = '';
        });
    }
    clearRestoredPasswords();
    window.addEventListener('load', clearRestoredPasswords);
    window.addEventListener('pageshow', clearRestoredPasswords);
    setTimeout(clearRestoredPasswords, 200);

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

    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId.length < 2) { return; }
            const el = document.getElementById(targetId.slice(1));
            if (el) {
                e.preventDefault();
                const y = el.getBoundingClientRect().top + window.pageYOffset - 90;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }
        });
    });

    /* Header search with debounced suggestions (desktop bar + mobile overlay) */
    const base = (document.body.getAttribute('data-base') || '').replace(/\/$/, '');

    function wireHeaderSearch(root) {
        if (!root) { return null; }
        const input = root.querySelector('input[type="search"]');
        const panel = root.querySelector('.hs-panel');
        if (!input || !panel) { return null; }
        let timer = null;
        let abort = null;
        let items = [];
        let active = -1;

        function escText(v) {
            const d = document.createElement('div');
            d.textContent = v;
            return d.innerHTML;
        }
        function show() { panel.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function hide() {
            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            items = []; active = -1;
        }

        function skeleton() {
            panel.innerHTML = '';
            for (let i = 0; i < 4; i++) {
                const sk = document.createElement('div');
                sk.className = 'hs-skel';
                sk.innerHTML = '<span class="hs-thumb"></span><span class="hs-meta"><span class="hs-bar"></span><span class="hs-bar short"></span></span>';
                panel.appendChild(sk);
            }
            show();
        }

        function itemEl(item) {
            const a = document.createElement('a');
            a.className = 'hs-item';
            a.href = base + '/pages/ground.php?id=' + item.id;
            const thumb = document.createElement('span');
            thumb.className = 'hs-thumb';
            if (item.img) {
                const im = document.createElement('img');
                im.src = base + '/uploads/grounds/' + encodeURIComponent(item.img);
                im.alt = '';
                im.referrerPolicy = 'no-referrer';
                thumb.appendChild(im);
            } else {
                thumb.innerHTML = '<i class="fa-solid fa-futbol"></i>';
            }
            a.appendChild(thumb);
            const meta = document.createElement('span');
            meta.className = 'hs-meta';
            const b = document.createElement('b');
            b.textContent = item.name;
            const s = document.createElement('span');
            s.textContent = item.location + ' · Rs ' + (Number(item.price) || 0).toLocaleString('en-NP');
            meta.appendChild(b);
            meta.appendChild(s);
            a.appendChild(meta);
            const go = document.createElement('i');
            go.className = 'fa-solid fa-angle-right hs-go';
            a.appendChild(go);
            return a;
        }

        function render(q) {
            if (items.length === 0) {
                panel.innerHTML = '<div class="hs-empty">No courts match "<b>' + escText(q) + '</b>"</div>';
            } else {
                const frag = document.createDocumentFragment();
                const showItems = items.slice(0, 5);
                showItems.forEach(function (it) { frag.appendChild(itemEl(it)); });
                const foot = document.createElement('a');
                foot.className = 'hs-foot';
                foot.href = base + '/pages/courts.php?q=' + encodeURIComponent(q);
                foot.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> View all matches for "' + escText(q) + '"';
                panel.innerHTML = '';
                panel.appendChild(frag);
                panel.appendChild(foot);
            }
            active = -1;
            show();
        }

        function fetchSuggest(q) {
            if (abort) { abort.abort(); }
            abort = new AbortController();
            fetch(base + '/ajax/search_suggest.php?q=' + encodeURIComponent(q), {
                signal: abort.signal
            }).then(function (r) {
                if (!r.ok) { throw new Error(r.status); }
                return r.json();
            }).then(function (data) {
                if (input.value.trim() !== q) { return; }
                items = (data && data.results) ? data.results : [];
                render(q);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') { return; }
                if (input.value.trim() === q) { hide(); }
            }).finally(function () { abort = null; });
        }

        function move(dir) {
            const rows = panel.querySelectorAll('.hs-item');
            if (!rows.length) { return; }
            active = (active + dir + rows.length) % rows.length;
            rows.forEach(function (r, i) { r.classList.toggle('active', i === active); });
            const el = rows[active];
            if (el) { el.scrollIntoView({ block: 'nearest' }); }
        }

        input.addEventListener('input', function () {
            const v = input.value.trim();
            clearTimeout(timer);
            if (!v) {
                if (abort) { abort.abort(); }
                panel.innerHTML = '';
                hide();
                return;
            }
            skeleton();
            timer = setTimeout(function () { fetchSuggest(v); }, 500);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { hide(); input.blur(); }
            else if (e.key === 'ArrowDown') { move(1); e.preventDefault(); }
            else if (e.key === 'ArrowUp') { move(-1); e.preventDefault(); }
            else if (e.key === 'Enter' && active >= 0 && items[active]) {
                e.preventDefault();
                window.location.href = base + '/pages/ground.php?id=' + items[active].id;
            }
        });

        document.addEventListener('pointerdown', function (e) {
            if (!root.contains(e.target)) { hide(); }
        });

        return { show, hide, clear: function () { panel.innerHTML = ''; hide(); } };
    }

    wireHeaderSearch(document.querySelector('.header-search-wrap'));

    const hsOverlay = document.getElementById('hsOverlay');
    const hsToggle = document.getElementById('headerSearchToggle');
    const hsScrim = document.getElementById('hsScrim');
    if (hsOverlay && hsToggle) {
        const overlay = wireHeaderSearch(hsOverlay);

        function overlayOpen() {
            hsOverlay.hidden = false;
            hsOverlay.classList.remove('closing');
            void hsOverlay.offsetWidth;
            hsOverlay.classList.add('open');
            hsToggle.setAttribute('aria-expanded', 'true');
            if (hsScrim) { hsScrim.classList.add('show'); hsScrim.classList.remove('closing'); }
            const inp = hsOverlay.querySelector('input[type="search"]');
            if (inp) { setTimeout(function () { inp.focus(); }, 30); }
        }
        function overlayClose() {
            if (!hsOverlay.classList.contains('open')) return;
            hsOverlay.classList.remove('open');
            hsOverlay.classList.add('closing');
            if (hsScrim) { hsScrim.classList.add('closing'); }
            hsToggle.setAttribute('aria-expanded', 'false');
            setTimeout(function () {
                hsOverlay.hidden = true;
                hsOverlay.classList.remove('closing');
                if (hsScrim) { hsScrim.classList.remove('show', 'closing'); }
                if (overlay) { overlay.clear(); }
            }, 270);
        }

        hsToggle.addEventListener('click', overlayOpen);
        const overlayCloseBtn = hsOverlay.querySelector('.hs-overlay-close');
        if (overlayCloseBtn) { overlayCloseBtn.addEventListener('click', overlayClose); }
        if (hsScrim) { hsScrim.addEventListener('click', overlayClose); }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !hsOverlay.hidden) { overlayClose(); }
        });
    }

    /* ---- Dark/light theme toggle ---- */
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const THEME_KEY = 'goalspace-theme';
    function themeSystemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    function themeFromStorage() {
        try { return localStorage.getItem(THEME_KEY); } catch (e) { return null; }
    }
    function currentTheme() {
        const saved = themeFromStorage();
        if (saved === 'dark' || saved === 'light') return saved;
        return themeSystemPrefersDark() ? 'dark' : 'light';
    }
    function syncThemeUI(theme) {
        const isDark = theme === 'dark';
        themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        themeToggle.setAttribute('aria-label', (isDark ? 'Disable' : 'Enable') + ' dark mode');
        if (themeIcon) {
            themeIcon.className = 'fa-solid ' + (isDark ? 'fa-sun' : 'fa-moon');
        }
    }
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        if (themeToggle) { syncThemeUI(theme); }
    }
    if (themeToggle) {
        applyTheme(currentTheme());
        themeToggle.addEventListener('click', function () {
            const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* storage unavailable */ }
        });
    }
});
