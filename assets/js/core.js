/* GoalSpace core.js
   Shared runtime loaded on every page: app loader, panels, modals,
   toasts, top bar, cookie consent, scroll helpers. Page-specific modules
   live in assets/js/modules/ and are loaded only where needed. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.has-error input, .has-error textarea, .has-error select').forEach(function (el) {
        el.setAttribute('aria-invalid', 'true');
    });

    const appLoader = document.getElementById('appLoader');
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

    // Viewport-based lazy loading: only load images when the user scrolls near them
    function initLazyImages() {
        const lazyImgs = document.querySelectorAll('img.lazy-load[data-src]');
        if (!lazyImgs.length) return;
        if ('IntersectionObserver' in window) {
            const imgObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            img.classList.add('loaded');
                        }
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '200px 0px' });
            lazyImgs.forEach(function (img) { imgObserver.observe(img); });
        } else {
            lazyImgs.forEach(function (img) {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    img.classList.add('loaded');
                }
            });
        }
    }
    initLazyImages();
    window.initLazyImages = initLazyImages;

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
            navToggle.innerHTML = open ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars-staggered"></i>';
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

    /* ---- Desktop sidebar collapse (icon rail when collapsed) ---- */
    const sidebarToggle = document.getElementById('sidebarToggle');
    const SIDEBAR_KEY = 'goalspace-sidebar-collapsed';
    function sidebarIsDesktop() {
        return window.matchMedia ? matchMedia('(min-width: 821px)').matches : window.innerWidth >= 821;
    }
    function applySidebarCollapsed(collapsed) {
        document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            const icon = sidebarToggle.querySelector('i');
            if (icon) {
                icon.className = collapsed ? 'fa-solid fa-bars-staggered' : 'fa-solid fa-angles-left';
            }
        }
        const navLinks = mainNav ? mainNav.querySelectorAll('a[href]') : [];
        navLinks.forEach(function (a) {
            const label = (a.getAttribute('data-label') || '').trim() || a.textContent.trim();
            a.setAttribute('data-label', label);
            if (collapsed) { a.setAttribute('title', label); } else { a.removeAttribute('title'); }
        });
        try { localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); } catch (e) { /* ignore */ }
    }
    if (sidebarToggle) {
        let savedCollapsed = false;
        try { savedCollapsed = localStorage.getItem(SIDEBAR_KEY) === '1'; } catch (e) { /* ignore */ }
        if (sidebarIsDesktop()) { applySidebarCollapsed(savedCollapsed); }
        sidebarToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            applySidebarCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
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

    /* ---- Admin bottom-nav "More" overflow menu ---- */
    const bnMoreBtn = document.getElementById('bnMoreBtn');
    const bnMorePanel = document.getElementById('bnMorePanel');
    if (bnMoreBtn && bnMorePanel) {
        bnMoreBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = bnMorePanel.hidden;
            bnMorePanel.hidden = !open;
            bnMoreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!bnMorePanel.hidden && !bnMorePanel.contains(e.target) && e.target !== bnMoreBtn) {
                bnMorePanel.hidden = true;
                bnMoreBtn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !bnMorePanel.hidden) {
                bnMorePanel.hidden = true;
                bnMoreBtn.setAttribute('aria-expanded', 'false');
                bnMoreBtn.focus();
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
                // scrolling down -> hide the top navbar only; keep bottom nav visible on mobile
                siteHeader.classList.add('collapsed');
                // mobile only: dismiss the slide-in nav drawer if open
                if (isCompactHeader() && mainNav && mainNav.classList.contains('open') && typeof setNavOpen === 'function') {
                    setNavOpen(false);
                    document.body.classList.remove('nav-open');
                }
            } else if ((delta < -6 && isUserScrolling()) || y <= 8) {
                // scrolling up (or near the top) -> reveal the top navbar
                siteHeader.classList.remove('collapsed');
            }
            lastY = y;
        }
        window.addEventListener('scroll', onScroll, { passive: true });

        /* Mobile: tap/click on a blank area toggles the top nav.
           If it is hidden, one tap reveals it; if revealed, one tap hides it. */
        document.addEventListener('click', function (e) {
            if (!window.matchMedia('(max-width: 820px)').matches) return;
            if (!bottomNav) return;
            if (Date.now() - lastUserScroll < 500) return;
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.closest('.site-header') || t.closest('.bottom-nav')) return;
            if (t.closest('a, button, input, textarea, select, label, [data-confirm], .otp-box')) return;
            if (t.closest('.toast, .modal, .popup-backdrop, .msg-backdrop, .msg-card, .cookie-banner, .drawer')) return;
            const hidden = siteHeader.classList.contains('collapsed');
            if (hidden) {
                hideTopBar();
                siteHeader.classList.remove('collapsed');
            } else {
                siteHeader.classList.add('collapsed');
            }
        });
    }

    /* Reveal-on-scroll: above-the-fold content appears instantly, everything
       else fades up as it enters the viewport. A load-time safety net catches
       anything the observer misses, so content is never left invisible. */
    const reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });
        reveals.forEach(function (el) {
            const r = el.getBoundingClientRect();
            if (r.top < window.innerHeight && r.bottom > 0) {
                el.classList.add('visible');
            } else {
                revealObserver.observe(el);
            }
        });
        window.addEventListener('load', function () {
            reveals.forEach(function (el) {
                if (!el.classList.contains('visible')) {
                    const r = el.getBoundingClientRect();
                    if (r.top < window.innerHeight && r.bottom > 0) { el.classList.add('visible'); }
                }
            });
        });
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
                    const f = el.form || el.closest('form');
                    if (f) f.submit();
                }
            }, {
                okText: el.dataset.confirmOk || 'Yes, continue',
                cancelText: el.dataset.confirmCancel || 'Cancel'
            });
        });
    });

    // Back buttons: return to the previous page, or the href when there's no history.
    document.addEventListener('click', function (e) {
        const target = e.target.closest('[data-back]');
        if (!target) return;
        e.preventDefault();
        if (history.length > 1) {
            history.back();
        } else {
            const href = target.getAttribute('href');
            if (href && href !== '#') {
                window.location.href = href;
            }
        }
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const sub = e.submitter;
            const btn = sub && sub.matches('button[type="submit"]')
                ? sub
                : form.querySelector('button[type="submit"]');
            if (btn && !btn.classList.contains('btn-loading')) {
                btn.classList.add('btn-loading');
                setTimeout(function () {
                    btn.setAttribute('disabled', 'disabled');
                }, 0);
            }
        });
    });

    // ----- Floating labels + gated CTA buttons ---------------------------
    // Both react to typing AND to browser autofill (which often fires no
    // input/change event). Central here so every page behaves the same.
    function syncFloatingLabels() {
        document.querySelectorAll('.input-group.floating input').forEach(function (el) {
            if (!el.hasAttribute('placeholder')) {
                el.setAttribute('placeholder', ' ');
            }
            const on = (el.value && el.value.trim() !== '') || el === document.activeElement;
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

    autosync();

    // ----- Inline live field validation ------------------------------
    // Debounced 500ms after the last keystroke: shows a "checking" buffer,
    // then a tick (valid) or a red mark + box highlight (invalid). Password
    // fields and OTP boxes are left untouched per design.
    function wireLiveFieldChecks() {
        const EMAIL_RE = /^[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i;
        function valueValidEmail(t) {
            if (!EMAIL_RE.test(t)) return false;
            if (t.includes('..')) return false;
            if (t.startsWith('.') || t.endsWith('.')) return false;
            const domain = t.split('@')[1] || '';
            const labels = domain.split('.');
            const tld = labels[labels.length - 1] || '';
            return labels.length >= 2 && tld.length >= 2;
        }
        const fields = document.querySelectorAll(
            '.input-group input[type="email"], .input-group input[type="tel"], .input-group input[type="text"]'
        );
        fields.forEach(function (input) {
            if (input.disabled || input.readOnly) return;
            if (input.closest('.otp-boxes')) return;
            const group = input.closest('.input-group');
            if (!group) return;
            const formGroup = group.closest('.form-group');
            const required = input.hasAttribute('required');

            // Only fields with an actual rule get live feedback: password/skip
            // is already excluded by the selector. Email and phone always check.
            let hasRule = input.type === 'email' || input.type === 'tel';
            if (!hasRule && required) hasRule = true;
            if (!hasRule) return;

            const status = document.createElement('span');
            status.className = 'field-status';
            status.setAttribute('aria-hidden', 'true');
            group.appendChild(status);

            let timer = null;
            let checkSeq = 0;
            const ajaxBase = (document.body.getAttribute('data-base') || '').replace(/\/$/, '');
            const csrf = document.body.getAttribute('data-csrf') || (document.querySelector('input[name="csrf_token"]') || {}).value || '';
            const emailCheckMode = input.getAttribute('data-check-email'); // 'exists' | 'available'

            function runServerCheck(cb) {
                if (!emailCheckMode) { cb(null); return; }
                const seq = ++checkSeq;
                const query = new URLSearchParams({
                    email: input.value.trim(),
                    mode: emailCheckMode,
                    csrf: csrf
                });
                const exclude = input.getAttribute('data-exclude-id');
                if (exclude) query.set('exclude', exclude);
                fetch(ajaxBase + '/ajax/check_email.php?' + query.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (seq !== checkSeq) return;
                        cb(data && data.ok);
                    })
                    .catch(function () {
                        if (seq !== checkSeq) return;
                        cb(null);
                    });
            }

            function valueValid(v) {
                const t = v.trim();
                if (t === '') return null; // empty: unknown until blur for required
                if (input.type === 'email') return valueValidEmail(t);
                if (input.type === 'tel') {
                    const digits = t.replace(/[^0-9]/g, '');
                    return digits.length >= 7 && digits.length <= 15;
                }
                if (input.hasAttribute('pattern')) {
                    try { return new RegExp('^(?:' + input.getAttribute('pattern') + ')$').test(t); } catch (e) { return true; }
                }
                return t.length >= 2;
            }

            function setError(on) {
                if (formGroup) formGroup.classList.toggle('has-error', on);
                input.setAttribute('aria-invalid', on ? 'true' : 'false');
            }

            function show(state, valid) {
                status.className = 'field-status ' + state;
                if (state === 'checking') {
                    status.innerHTML = '<span class="spinner-thin"></span>';
                } else if (state === 'ok') {
                    status.innerHTML = '<i class="fa-solid fa-check"></i>';
                    setError(false);
                } else if (state === 'bad') {
                    status.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    setError(true);
                } else {
                    status.innerHTML = '';
                    setError(false);
                }
            }

            function finalizeCheck(res) {
                // Server check result: true => ok, false => bad, null => fall back to format-only
                if (res === true) { show('ok'); }
                else if (res === false) { show('bad'); }
                else {
                    const local = valueValid(input.value);
                    show(local === null ? '' : (local ? 'ok' : 'bad'), local);
                }
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                checkSeq++;
                const v = input.value;
                if (v.trim() === '' && !required) { show(''); return; }
                if (v.trim() === '') { show('checking'); return; }
                show('checking');
                timer = setTimeout(function () {
                    const res = valueValid(input.value);
                    if (res === null) { show(''); return; }
                    if (res === false) { show('bad'); return; }
                    runServerCheck(finalizeCheck);
                }, 500);
            });
            input.addEventListener('blur', function () {
                clearTimeout(timer);
                checkSeq++;
                const res = valueValid(input.value);
                if (res === null) {
                    if (required) show('bad'); else show('');
                } else if (res === false) {
                    show('bad');
                } else {
                    runServerCheck(finalizeCheck);
                }
            });
            // Reflect a server-side error already present on load.
            if (formGroup && formGroup.classList.contains('has-error') && input.value.trim() !== '') {
                const res = valueValid(input.value);
                if (res === false) show('bad');
            }
        });
    }
    wireLiveFieldChecks();

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
        setTimeout(function () {
            const wrap = t.closest('.top-flash-wrap');
            t.remove();
            if (wrap && !wrap.querySelector('.toast')) {
                wrap.remove();
            }
            removeBackdropIfEmpty();
        }, 260);
    }
    function dismissMsgCard(card) {
        if (!card || card.classList.contains('hide')) return;
        card.classList.add('hide');
        const back = card.closest('.msg-backdrop');
        setTimeout(function () {
            card.remove();
            if (back) back.remove();
        }, 220);
    }
    function removeBackdropIfEmpty() {
        if (!document.querySelector('.toast') && !document.querySelector('.modal') && !document.querySelector('.msg-card')) {
            const b = document.querySelector('.popup-backdrop');
            if (b) b.remove();
        }
    }
    function announceToScreenReader(text) {
        var region = document.getElementById('ariaLiveRegion');
        if (region) {
            region.textContent = '';
            setTimeout(function () { region.textContent = text; }, 100);
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
        const prevFocused = document.activeElement;
        showBackdrop();
        document.body.style.overflow = 'hidden';
        const esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        const modal = document.createElement('div');
        modal.className = 'modal open';
        modal.setAttribute('role', 'alertdialog');
        modal.setAttribute('aria-modal', 'true');
        modal.innerHTML =
            '<button type="button" class="modal-x" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
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

        const onEsc = function (e) {
            if (e.key === 'Escape') close();
            if (e.key === 'Tab') {
                const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    last.focus();
                    e.preventDefault();
                } else if (!e.shiftKey && document.activeElement === last) {
                    first.focus();
                    e.preventDefault();
                }
            }
        };
        document.addEventListener('keydown', onEsc);

        function close() {
            modal.classList.add('hide');
            document.removeEventListener('keydown', onEsc);
            document.body.style.overflow = '';
            setTimeout(function () {
                modal.remove();
                removeBackdropIfEmpty();
                if (prevFocused && typeof prevFocused.focus === 'function') prevFocused.focus();
            }, 220);
        }

        const backdrop = document.querySelector('.popup-backdrop');
        if (backdrop) {
            const onBackdrop = function () {
                close();
                backdrop.removeEventListener('click', onBackdrop);
            };
            backdrop.addEventListener('click', onBackdrop);
        }

        const closeBtn = modal.querySelector('.modal-x');
        if (closeBtn) closeBtn.addEventListener('click', close);
        const cancelBtn = modal.querySelector('[data-modal-cancel]');
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        const okBtn = modal.querySelector('[data-modal-ok]');
        if (okBtn) {
            okBtn.addEventListener('click', function () {
                close();
                if (typeof onConfirm === 'function') onConfirm();
            });
            setTimeout(function () { okBtn.focus(); }, 40);
        }
    }
    function showSheet(message, opts) {
        opts = opts || {};
        const type = opts.type || 'error';
        const esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        const icons = { error: 'fa-circle-xmark', info: 'fa-circle-info', success: 'fa-circle-check', warning: 'fa-triangle-exclamation' };

        // Reuse an open backdrop if there is one; otherwise create it.
        let backdrop = document.querySelector('.sheet-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'sheet-backdrop';
            document.body.appendChild(backdrop);
        }

        // Stack multiple sheets a touch apart so they don't fully overlap,
        // raised above the mobile bottom nav so they never appear hidden.
        function sheetNavOffset() {
            const nav = document.querySelector('.bottom-nav');
            if (nav && getComputedStyle(nav).display !== 'none') return nav.offsetHeight || 62;
            return 0;
        }
        const sheets = document.querySelectorAll('.sheet');
        const sheet = document.createElement('div');
        sheet.className = 'sheet' + (type === 'error' ? '' : ' sheet-' + type);
        sheet.style.bottom = 'calc(' + (sheetNavOffset() + 16 + sheets.length * 12) + 'px + env(safe-area-inset-bottom, 0px))';
        sheet.innerHTML =
            '<button type="button" class="sheet-x" data-sheet-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
            '<div class="sheet-head">' +
                '<span class="sheet-icon"><i class="fa-solid ' + icons[type] + '"></i></span>' +
                '<h3>' + esc(opts.title || (type === 'error' ? 'Something went wrong' : 'Heads up')) + '</h3>' +
            '</div>' +
            '<p class="sheet-msg">' + esc(message) + '</p>' +
            (opts.detail ? '<p class="sheet-detail">' + esc(opts.detail) + '</p>' : '');
        document.body.appendChild(sheet);

        function close() {
            if (sheet.classList.contains('hide')) return;
            sheet.classList.add('hide');
            setTimeout(function () {
                sheet.remove();
                if (!document.querySelector('.sheet')) {
                    const b = document.querySelector('.sheet-backdrop');
                    if (b) b.remove();
                }
            }, 300);
        }
        sheet.querySelector('[data-sheet-close]').addEventListener('click', close);
        sheet.addEventListener('click', function (e) { if (e.target === sheet) close(); });
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', handler); }
        });
        setTimeout(function () { sheet.querySelector('[data-sheet-close]').focus(); }, 10);

        const autoDismiss = (type === 'success' || type === 'info'); // errors/warnings stay until dismissed
        if (autoDismiss) setTimeout(close, 3200);
        return { close: close };
    }
    window.showSheet = showSheet;

    function openErrorModal(message, title, opts) {
        opts = opts || {};
        const prevFocused = document.activeElement;
        document.body.style.overflow = 'hidden';
        const esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        const showButton = opts.button !== false;
        const btnLabel = opts.button === true ? 'Try again' : (opts.button || 'Try again');
        const backdrop = document.createElement('div');
        backdrop.className = 'msg-backdrop';
        backdrop.setAttribute('role', 'alertdialog');
        backdrop.setAttribute('aria-modal', 'true');
        const card = document.createElement('div');
        card.className = 'msg-card msg-error';
        card.innerHTML =
            '<button type="button" class="msg-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
            '<div class="msg-icon"><i class="fa-solid fa-xmark"></i></div>' +
            '<h3 class="msg-title">' + esc(title || 'Error') + '</h3>' +
            '<p class="msg-text">' + esc(message) + '</p>' +
            (showButton ? '<button type="button" class="msg-btn msg-btn-error">' + esc(btnLabel) + '</button>' : '');
        backdrop.appendChild(card);
        document.body.appendChild(backdrop);

        function close() {
            if (card.classList.contains('hide')) return;
            card.classList.add('hide');
            document.removeEventListener('keydown', keyHandler);
            document.body.style.overflow = '';
            setTimeout(function () {
                backdrop.remove();
                if (prevFocused && typeof prevFocused.focus === 'function') prevFocused.focus();
            }, 220);
        }
        const closeBtn = card.querySelector('.msg-close');
        if (closeBtn) closeBtn.addEventListener('click', close);
        const btn = card.querySelector('.msg-btn');
        if (btn) btn.addEventListener('click', close);
        backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
        function keyHandler(e) {
            if (e.key === 'Escape') { close(); }
            if (e.key === 'Tab') {
                const focusables = card.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    last.focus();
                    e.preventDefault();
                } else if (!e.shiftKey && document.activeElement === last) {
                    first.focus();
                    e.preventDefault();
                }
            }
        }
        document.addEventListener('keydown', keyHandler);
        setTimeout(function () { (btn || closeBtn).focus(); }, 40);
    }
    window.openErrorModal = openErrorModal;
    document.querySelectorAll('[data-error-modal-msg]').forEach(function (el) {
        const pws = document.querySelectorAll('.form-card input[type="password"]');
        if (pws.length) {
            pws.forEach(function (p) {
                const group = p.closest('.form-group');
                if (group) group.classList.add('has-error');
            });
            setTimeout(function () { if (pws[0]) pws[0].focus(); }, 150);
        }
        openErrorModal(el.getAttribute('data-error-modal-msg'), el.getAttribute('data-error-modal-title'));
    });
    const msgCards = document.querySelectorAll('.msg-card');
    msgCards.forEach(function (card) {
        const btn = card.querySelector('.msg-btn');
        if (btn) {
            btn.addEventListener('click', function () {
                const back = btn.getAttribute('data-msg-back');
                dismissMsgCard(card);
                if (back) setTimeout(function () { window.location.href = back; }, 200);
            });
        }
        const closeBtn = card.querySelector('.msg-close');
        if (closeBtn) closeBtn.addEventListener('click', function () { dismissMsgCard(card); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !card.classList.contains('hide')) dismissMsgCard(card);
        });
    });

    function initToast(t, i) {
        if (!t || t.__toast_init) return;
        t.__toast_init = true;

        const close = t.querySelector('.toast-close');
        if (close) close.addEventListener('click', function () { dismissToast(t); });
        const isError = t.classList.contains('toast-error');
        const isInline = t.classList.contains('toast-inline');
        const isTop = t.classList.contains('toast-top') || !!t.closest('.top-flash-wrap');
        const hasRichDetail = !!t.querySelector('.toast-why, .toast-how, .toast-fix');
        const delay = isError ? (hasRichDetail ? 5200 : 4200) : (isInline ? 3000 : 3500);

        t.style.setProperty('--toast-duration', delay + 'ms');

        let timer = null;
        const startTimer = function () {
            if (!timer) {
                t.classList.remove('is-paused');
                timer = setTimeout(function () { dismissToast(t); }, delay);
            }
        };
        const stopTimer = function () {
            if (timer) {
                t.classList.add('is-paused');
                clearTimeout(timer);
                timer = null;
            }
        };

        startTimer();

        // Mouse hover pause
        t.addEventListener('mouseenter', stopTimer);
        t.addEventListener('mouseleave', startTimer);

        // Touch gestures: hold to pause + swipe up/flick to dismiss immediately
        let touchStartY = 0;
        let touchDeltaY = 0;
        let isTouching = false;

        t.addEventListener('touchstart', function (e) {
            if (e.touches && e.touches.length === 1) {
                touchStartY = e.touches[0].clientY;
                touchDeltaY = 0;
                isTouching = true;
                stopTimer();
            }
        }, { passive: true });

        t.addEventListener('touchmove', function (e) {
            if (!isTouching || !e.touches || e.touches.length !== 1) return;
            touchDeltaY = e.touches[0].clientY - touchStartY;
            if (touchDeltaY < 0) {
                // Swiping up towards top edge: smoothly follow finger
                t.style.transform = 'translateY(' + (touchDeltaY * 0.85) + 'px)';
                t.style.opacity = Math.max(0.15, 1 + touchDeltaY / 140);
            }
        }, { passive: true });

        t.addEventListener('touchend', function () {
            if (!isTouching) return;
            isTouching = false;
            if (touchDeltaY < -32) {
                // User swiped up with intention: dismiss immediately
                dismissToast(t);
            } else {
                // Snap back smoothly
                t.style.transform = '';
                t.style.opacity = '';
                startTimer();
            }
        }, { passive: true });

        t.addEventListener('touchcancel', function () {
            isTouching = false;
            t.style.transform = '';
            t.style.opacity = '';
            startTimer();
        }, { passive: true });

        if (!isTop && !isInline && toasts.length > 1) {
            t.style.bottom = 'calc(' + (i * 62) + 'px + env(safe-area-inset-bottom, 0px))';
        }

        // Tactile haptic feedback for errors on mobile devices
        if (isError && typeof navigator !== 'undefined' && navigator.vibrate) {
            try { navigator.vibrate([15, 35, 15]); } catch (_) {}
        }

        // Announce toast to screen readers
        var toastMsg = t.querySelector('.toast-msg');
        if (toastMsg) announceToScreenReader(toastMsg.textContent);
        if (isError) {
            highlightPasswordError(t);
        }
    }

    toasts.forEach(initToast);

    // Global client-side GoalSpace.toast API
    window.GoalSpace = window.GoalSpace || {};
    window.GoalSpace.toast = function (opts) {
        if (!opts) return null;
        const type = opts.type || 'error';
        const message = opts.message || opts.text || '';
        if (!message) return null;

        let wrap = document.querySelector('.top-flash-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'top-flash-wrap';
            document.body.appendChild(wrap);
        }

        const esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };

        const t = document.createElement('div');
        const isSuccess = type === 'success';
        const isInfo = type === 'info';
        t.className = 'toast toast-' + (isSuccess ? 'success' : (isInfo ? 'info' : 'error')) + ' toast-top';
        t.setAttribute('role', isSuccess || isInfo ? 'status' : 'alert');

        const iconClass = isSuccess ? 'fa-circle-check' : (isInfo ? 'fa-circle-info' : 'fa-circle-exclamation');

        let html = '<div class="toast-icon"><i class="fa-solid ' + iconClass + '"></i></div>';
        html += '<div class="toast-content"><div class="toast-msg">';
        html += '<span>' + esc(message) + '</span>';
        if (opts.why) {
            html += '<span class="toast-why">' + esc(opts.why) + '</span>';
        }
        if (opts.how) {
            html += '<span class="toast-how">' + esc(opts.how) + '</span>';
        }
        if (opts.actionLabel && opts.actionUrl) {
            html += '<a href="' + esc(opts.actionUrl) + '" class="toast-fix">' + esc(opts.actionLabel) + '</a>';
        }
        html += '</div></div>';
        html += '<button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
        html += '<div class="toast-progress" aria-hidden="true"></div>';

        t.innerHTML = html;
        wrap.appendChild(t);
        initToast(t, wrap.querySelectorAll('.toast').length - 1);
        return t;
    };
    function highlightPasswordError(toast) {
        const card = toast.closest('.form-card');
        const scope = card || document;
        const pws = scope.querySelectorAll('input[type="password"]');
        if (!pws.length) return;
        pws.forEach(function (p) {
            const group = p.closest('.form-group');
            if (group) group.classList.add('has-error');
        });
        setTimeout(function () { pws[0].focus(); }, 120);
        pws.forEach(function (p) {
            const group = p.closest('.form-group');
            p.addEventListener('input', function () { if (group) group.classList.remove('has-error'); }, { once: true });
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

    /* Mobile: expandable table rows (≤720px) — tap summary to reveal detail cells */
    function syncTableAria() {
        var isMobile = window.matchMedia('(max-width: 720px)').matches;
        document.querySelectorAll('.table-wrap tbody tr:not(.table-empty):not(.table-total)').forEach(function (tr) {
            if (isMobile) {
                if (!tr.hasAttribute('aria-expanded')) tr.setAttribute('aria-expanded', 'false');
                tr.setAttribute('tabindex', '0');
            } else {
                tr.removeAttribute('aria-expanded');
                tr.removeAttribute('tabindex');
            }
        });
    }
    syncTableAria();
    window.addEventListener('resize', syncTableAria);

    document.addEventListener('click', function (e) {
        if (window.matchMedia('(min-width: 721px)').matches) return;
        const tr = e.target.closest('.table-wrap tbody tr');
        if (!tr) return;
        if (tr.classList.contains('table-empty') || tr.classList.contains('table-total')) return;
        if (e.target.closest('a, button, input, select, textarea, label, form, [role="button"]')) return;
        const open = !tr.classList.contains('is-open');
        tr.classList.toggle('is-open', open);
        tr.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) {
        if (window.matchMedia('(min-width: 721px)').matches) return;
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const tr = e.target.closest && e.target.closest('.table-wrap tbody tr');
        if (!tr || tr.classList.contains('table-empty') || tr.classList.contains('table-total')) return;
        if (e.target !== tr) return;
        e.preventDefault();
        const open = !tr.classList.contains('is-open');
        tr.classList.toggle('is-open', open);
        tr.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

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
        function show() { panel.hidden = false; input.setAttribute('aria-expanded', 'true'); panel.setAttribute('role', 'listbox'); }
        function hide() {
            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            items = []; active = -1;
        }

        function itemEl(item) {
            const a = document.createElement('a');
            a.className = 'hs-item';
            a.setAttribute('role', 'option');
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
            timer = setTimeout(function () { fetchSuggest(v); }, 220);
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
        if (saved === 'dark') return 'dark';
        if (saved === 'light') return 'light';
        if (saved === 'system') return themeSystemPrefersDark() ? 'dark' : 'light';
        return 'light'; // default theme when nothing is saved
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
            // keep any open settings picker in sync
            const themeOptions = document.getElementById('themeOptions');
            if (themeOptions) {
                const opts = themeOptions.querySelectorAll('.theme-option');
                for (let i = 0; i < opts.length; i++) {
                    const active = opts[i].getAttribute('data-theme') === next;
                    opts[i].classList.toggle('active', active);
                    opts[i].setAttribute('aria-checked', active ? 'true' : 'false');
                }
            }
        });
    }

    /* ---- Favorites (saved courts) toggle (optimistic UI) ---- */
    document.querySelectorAll('.fav-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            const id = btn.getAttribute('data-ground-id');
            const url = btn.getAttribute('data-url') || (document.body.getAttribute('data-base') || '') + 'ajax/favorite.php';
            const csrf = btn.getAttribute('data-csrf') || document.body.getAttribute('data-csrf') || (document.querySelector('input[name="csrf_token"]') || {}).value;
            const prevSaved = btn.getAttribute('data-saved') === '1' ? 1 : 0;
            const nextSaved = prevSaved ? 0 : 1;
            const icon = btn.querySelector('i');
            btn.disabled = true;
            btn.setAttribute('data-saved', String(nextSaved));
            btn.classList.toggle('saved', nextSaved === 1);
            if (icon) {
                icon.classList.toggle('fa-solid', nextSaved === 1);
                icon.classList.toggle('fa-regular', nextSaved !== 1);
            }
            const fd = new URLSearchParams();
            fd.append('id', id);
            fd.append('csrf_token', csrf || '');
            fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    btn.disabled = false;
                    if (json && json.ok) {
                        const saved = json.saved === 1 ? 1 : 0;
                        btn.setAttribute('data-saved', String(saved));
                        btn.classList.toggle('saved', saved === 1);
                        if (icon) {
                            icon.classList.toggle('fa-solid', saved === 1);
                            icon.classList.toggle('fa-regular', saved !== 1);
                        }
                        if (window.GoalSpace && window.GoalSpace.toast) {
                            window.GoalSpace.toast({
                                type: 'success',
                                message: saved ? 'Added to your saved courts.' : 'Removed from saved courts.'
                            });
                        }
                    } else {
                        btn.setAttribute('data-saved', String(prevSaved));
                        btn.classList.toggle('saved', prevSaved === 1);
                        if (icon) {
                            icon.classList.toggle('fa-solid', prevSaved === 1);
                            icon.classList.toggle('fa-regular', prevSaved !== 1);
                        }
                        const errMsg = (json && json.error) ? json.error : 'Could not update saved courts.';
                        if (window.GoalSpace && window.GoalSpace.toast) {
                            window.GoalSpace.toast({ type: 'error', message: errMsg });
                        } else if (typeof openErrorModal === 'function') {
                            openErrorModal('Could not save', errMsg);
                        }
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.setAttribute('data-saved', String(prevSaved));
                    btn.classList.toggle('saved', prevSaved === 1);
                    if (icon) {
                        icon.classList.toggle('fa-solid', prevSaved === 1);
                        icon.classList.toggle('fa-regular', prevSaved !== 1);
                    }
                    const netMsg = 'Network connection issue. Please try again.';
                    if (window.GoalSpace && window.GoalSpace.toast) {
                        window.GoalSpace.toast({ type: 'error', message: netMsg });
                    } else if (typeof openErrorModal === 'function') {
                        openErrorModal('Could not save', netMsg);
                    }
                });
        });
    });

    /* ---- Ground photo gallery (ground.php) ---- */
    function initGallery() {
        const wrap = document.getElementById('galleryWrap');
        if (!wrap) return;

        const mainImg = document.getElementById('galleryMain');
        const thumbs = Array.from(wrap.querySelectorAll('.gallery-thumb'));
        const prevBtn = wrap.querySelector('.gallery-prev');
        const nextBtn = wrap.querySelector('.gallery-next');
        const zoomBtn = wrap.querySelector('.gallery-zoom');
        let current = 0;

        function show(idx) {
            if (!thumbs[idx]) return;
            current = idx;
            mainImg.src = thumbs[idx].dataset.src;
            mainImg.alt = thumbs[idx].getAttribute('aria-label') || '';
            thumbs.forEach(function (t, i) {
                t.classList.toggle('active', i === idx);
            });
            if (prevBtn) prevBtn.hidden = idx === 0;
            if (nextBtn) nextBtn.hidden = idx === thumbs.length - 1;
        }

        thumbs.forEach(function (thumb, i) {
            thumb.addEventListener('click', function () { show(i); });
        });

        if (prevBtn) prevBtn.addEventListener('click', function () { if (current > 0) show(current - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { if (current < thumbs.length - 1) show(current + 1); });

        if (zoomBtn && thumbs.length) {
            zoomBtn.addEventListener('click', function () {
                const overlay = document.createElement('div');
                overlay.className = 'gallery-lightbox';
                overlay.innerHTML =
                    '<button class="gl-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
                    '<button class="gl-prev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>' +
                    '<img src="' + mainImg.src + '" alt="">' +
                    '<button class="gl-next" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>';
                document.body.appendChild(overlay);
                let lbIdx = current;
                const lbImg = overlay.querySelector('img');
                function lbShow(idx) {
                    lbIdx = idx;
                    lbImg.src = thumbs[idx].dataset.src;
                    overlay.querySelector('.gl-prev').hidden = idx === 0;
                    overlay.querySelector('.gl-next').hidden = idx === thumbs.length - 1;
                }
                overlay.querySelector('.gl-close').addEventListener('click', function () { overlay.remove(); });
                overlay.querySelector('.gl-prev').addEventListener('click', function () { if (lbIdx > 0) lbShow(lbIdx - 1); });
                overlay.querySelector('.gl-next').addEventListener('click', function () { if (lbIdx < thumbs.length - 1) lbShow(lbIdx + 1); });
                overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
                document.addEventListener('keydown', function esc(e) {
                    if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', esc); }
                });
            });
        }

        if (thumbs.length) show(0);
    }
    initGallery();
    /* end ground gallery */



    /* ---- Install as app (PWA) ---- */
    (function () {
        var base = document.body.getAttribute('data-base') || '';
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
        var deferredPrompt = null;
        var installShown = false;

        function dismissedRecently() {
            try {
                var t = parseInt(localStorage.getItem('gs_install_dismissed_at') || '0', 10);
                return t > 0 && (Date.now() - t < 7 * 24 * 60 * 60 * 1000);
            } catch (e) { return false; }
        }
        function markDismissed() {
            try { localStorage.setItem('gs_install_dismissed_at', String(Date.now())); } catch (e) {}
        }

        function showInstallPrompt() {
            if (installShown || dismissedRecently() || isStandalone) return;
            if (document.querySelector('.install-prompt')) return;

            installShown = true;
            var card = document.createElement('div');
            card.className = 'install-prompt';
            card.setAttribute('role', 'dialog');
            card.setAttribute('aria-label', 'Install GoalSpace');
            card.innerHTML =
                '<div class="install-card">' +
                    '<img class="install-icon" src="' + base + '/assets/img/icon-192.png" alt="" width="46" height="46">' +
                    '<div class="install-text">' +
                        '<strong>Install GoalSpace</strong>' +
                        '<span>' + (isIOS
                            ? 'Tap the Share button, then \'Add to Home Screen\' to get GoalSpace on your phone.'
                            : 'Get quick access right from your home screen, just like an app.') + '</span>' +
                    '</div>' +
                    '<button type="button" class="install-x" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>' +
                '</div>' +
                '<div class="install-actions">' +
                    '<button type="button" class="btn btn-ghost btn-sm" data-install-later>Not now</button>' +
                    (isIOS
                        ? '<a class="btn btn-primary btn-sm" href="' + base + '/pages/faq.php#cat-tech">How to install</a>'
                        : '<button type="button" class="btn btn-primary btn-sm" data-install-ok>Install</button>') +
                '</div>';
            document.body.appendChild(card);

            function hide() {
                if (card.classList.contains('hide')) return;
                card.classList.add('hide');
                setTimeout(function () { card.remove(); }, 300);
            }
            card.querySelector('.install-x').addEventListener('click', function () { hide(); markDismissed(); });
            card.querySelector('[data-install-later]').addEventListener('click', function () { hide(); markDismissed(); });
            var okBtn = card.querySelector('[data-install-ok]');
            if (okBtn) {
                okBtn.addEventListener('click', function () {
                    if (deferredPrompt && !isIOS) {
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function (choice) {
                            if (choice.outcome !== 'accepted') markDismissed();
                            deferredPrompt = null;
                            hide();
                        }).catch(function () { deferredPrompt = null; hide(); });
                    } else {
                        hide();
                    }
                });
            }
            requestAnimationFrame(function () { card.classList.add('show'); });
        }

        // The browser tells us the site is installable; we ask, it decides.
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredPrompt = e;
            setTimeout(showInstallPrompt, 12000);
        });
        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            try { localStorage.removeItem('gs_install_dismissed_at'); } catch (e) {}
            var p = document.querySelector('.install-prompt');
            if (p) p.remove();
        });

        // iOS Safari has no beforeinstallprompt: offer manual instructions once.
        if (isIOS && !isStandalone) {
            setTimeout(showInstallPrompt, 12000);
        }

        // Register the service worker so the site can be installed as an app.
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(base + '/sw.js').catch(function () {});
        }
    })();
});
