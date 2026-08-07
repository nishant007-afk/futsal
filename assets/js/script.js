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
    if (siteHeader) {
        let lastY = window.scrollY;
        function isCompactHeader() {
            return !window.matchMedia('(min-width: 821px)').matches;
        }
        function onScroll() {
            const y = window.scrollY;
            const delta = y - lastY;
            if (delta > 6) {
                // scrolling down -> hide the top navbar (sidebar stays fixed on the left)
                siteHeader.classList.add('collapsed');
                // mobile only: dismiss the slide-in nav drawer if open
                if (isCompactHeader() && mainNav && mainNav.classList.contains('open') && typeof setNavOpen === 'function') {
                    setNavOpen(false);
                    document.body.classList.remove('nav-open');
                }
            } else if (delta < -6 || y <= 8) {
                // scrolling up (or near the top) -> reveal the top navbar
                siteHeader.classList.remove('collapsed');
            }
            lastY = y;
        }
        window.addEventListener('scroll', onScroll, { passive: true });
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

    const slotGrid = document.getElementById('slotGrid');
    const selectedSlot = document.getElementById('selectedSlot');
    const bookBtn = document.getElementById('bookBtn');

    if (slotGrid && selectedSlot) {
        if (bookBtn) bookBtn.disabled = true;

        slotGrid.addEventListener('click', function (e) {
            const slot = e.target.closest('.slot');
            if (!slot || slot.classList.contains('taken')) return;

            document.querySelectorAll('.slot.selected').forEach(function (s) {
                s.classList.remove('selected');
            });
            slot.classList.add('selected');

            selectedSlot.value = slot.dataset.start + '|' + slot.dataset.end;

            const priceEl = document.getElementById('priceHint');
            if (priceEl && slot.dataset.label) {
                priceEl.textContent = 'Selected: ' + slot.dataset.label + ' - ' + (slot.dataset.price || '');
            }
            if (bookBtn) bookBtn.disabled = false;
        });
    }

    const settleButtons = document.querySelectorAll('[data-settle]');
    const settleModal = document.getElementById('settleModal');
    const settleClose = document.getElementById('settleClose');
    if (settleButtons.length && settleModal) {
        settleButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('settleName').textContent = btn.dataset.name;
                document.getElementById('settleManagerId').value = btn.dataset.settle;
                document.getElementById('settleGross').value = btn.dataset.gross;
                document.getElementById('settleFee').value = btn.dataset.fee;
                document.getElementById('settlePayout').value = btn.dataset.payout;
                settleModal.hidden = false;
            });
        });
        if (settleClose) {
            settleClose.addEventListener('click', function () { settleModal.hidden = true; });
        }
        settleModal.addEventListener('click', function (e) {
            if (e.target === settleModal) settleModal.hidden = true;
        });
    }

    const repeatToggle = document.getElementById('repeatToggle');
    const repeatWeeksWrap = document.getElementById('repeatWeeksWrap');
    if (repeatToggle && repeatWeeksWrap) {
        repeatToggle.addEventListener('change', function () {
            repeatWeeksWrap.style.display = repeatToggle.checked ? 'flex' : 'none';
        });
    }

    const dateInput = document.getElementById('bookingDate');    if (dateInput && dateInput.closest('form')) {
        const today = new Date();
        const iso = today.getFullYear() + '-' +
            String(today.getMonth() + 1).padStart(2, '0') + '-' +
            String(today.getDate()).padStart(2, '0');
        dateInput.min = iso;
        if (!dateInput.value) dateInput.value = iso;
        dateInput.addEventListener('change', function () {
            const id = new URLSearchParams(window.location.search).get('id');
            if (id) {
                window.location.search = '?id=' + id + '&date=' + dateInput.value;
            }
        });
    }

    const galleryMain = document.getElementById('galleryMain');
    const galleryThumbs = document.querySelectorAll('.gallery-thumb');
    if (galleryMain && galleryThumbs.length) {
        const srcs = Array.prototype.map.call(galleryThumbs, function (t) { return t.dataset.src; });
        let current = 0;
        const prevBtn = document.querySelector('.gallery-prev');
        const nextBtn = document.querySelector('.gallery-next');
        const zoomBtn = document.querySelector('.gallery-zoom');
        function updateNav() {
            const showPrev = current > 0;
            const showNext = current < srcs.length - 1;
            if (prevBtn) prevBtn.hidden = !showPrev;
            if (nextBtn) nextBtn.hidden = !showNext;
            galleryThumbs.forEach(function (t, i) { t.classList.toggle('active', i === current); });
        }
        function animateSwap() {
            galleryMain.classList.remove('gallery-swap');
            void galleryMain.offsetWidth;
            galleryMain.classList.add('gallery-swap');
        }
        function show(src) {
            galleryMain.src = src;
            animateSwap();
            if (zoomBtn) zoomBtn.dataset.src = src;
            updateNav();
        }
        function goTo(i) {
            if (i < 0) i = srcs.length - 1;
            if (i > srcs.length - 1) i = 0;
            current = i;
            show(srcs[current]);
        }
        galleryThumbs.forEach(function (thumb, i) {
            thumb.addEventListener('click', function () {
                current = i;
                show(thumb.dataset.src);
            });
        });
        if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });
        if (zoomBtn) zoomBtn.addEventListener('click', function () {
            openImageZoom(current);
        });
        // initial state (active first thumb, hide prev arrow)
        galleryThumbs.forEach(function (t, i) { if (i === 0) t.classList.add('active'); });
        if (zoomBtn) zoomBtn.dataset.src = srcs[0];
        updateNav();

        var openImageZoom = function (index) {
            const existing = document.querySelector('.image-zoom');
            if (existing) existing.remove();
            const overlay = document.createElement('div');
            overlay.className = 'image-zoom';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML =
                '<button type="button" class="iz-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
                '<div class="iz-stage"><img class="iz-img" src="' + srcs[index] + '" alt=""></div>' +
                (srcs.length > 1
                    ? '<button type="button" class="iz-prev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>' +
                      '<button type="button" class="iz-next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>'
                    : '');
            document.body.appendChild(overlay);
            document.body.classList.add('modal-open');
            let i = index;
            const img = overlay.querySelector('.iz-img');
            function render(next) {
                i = (next + srcs.length) % srcs.length;
                current = i;
                img.src = srcs[i];
                galleryMain.src = srcs[i];
                updateNav();
            }
            overlay.addEventListener('click', function (e) {
                const t = e.target;
                if (t.closest('.iz-close')) { closeZoom(); return; }
                if (t.closest('.iz-prev')) { render(i - 1); return; }
                if (t.closest('.iz-next')) { render(i + 1); return; }
                if (t === overlay) closeZoom();
            });
            function closeZoom() {
                overlay.remove();
                document.body.classList.remove('modal-open');
                document.removeEventListener('keydown', onKey);
            }
            function onKey(e) {
                if (e.key === 'Escape') closeZoom();
                else if (e.key === 'ArrowLeft') render(i - 1);
                else if (e.key === 'ArrowRight') render(i + 1);
            }
            document.addEventListener('keydown', onKey);
        };
    }

    const groundForm = document.getElementById('groundForm');
    if (groundForm) {
        const pn = document.getElementById('previewName');
        const pl = document.getElementById('previewLoc');
        const pd = document.getElementById('previewDesc');
        const pf = groundForm.querySelector('#price_per_hour');
        const priceEl = document.querySelector('.preview-price');
        function refreshPreview() {
            const name = groundForm.querySelector('#name').value.trim();
            const loc = groundForm.querySelector('#location').value.trim();
            const desc = groundForm.querySelector('#description').value.trim();
            if (pn) pn.textContent = name || 'Your court name';
            if (pl) pl.textContent = loc || 'Kathmandu, Nepal';
            if (pd) pd.textContent = desc || 'A short description of your court.';
            if (pf && priceEl) {
                const v = parseFloat(pf.value) || 0;
                priceEl.innerHTML = 'Rs ' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' <small>/ hour</small>';
            }
        }
        ['#name', '#location', '#description', '#price_per_hour'].forEach(function (sel) {
            const el = groundForm.querySelector(sel);
            if (el) el.addEventListener('input', refreshPreview);
        });
        refreshPreview();
    }

    const photoInput = document.getElementById('photoInput');
    const fileNames = document.getElementById('fileNames');
    const photoPreviewGrid = document.getElementById('photoPreviewGrid');
    const previewCardImg = document.querySelector('.ground-preview .card-img');
    if (photoInput && fileNames) {
        let selectedFiles = [];
        function syncInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(function (f) { dt.items.add(f); });
            photoInput.files = dt.files;
        }
        function ensureCoverImg() {
            if (!previewCardImg) return null;
            let img = previewCardImg.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = 'Court preview';
                previewCardImg.appendChild(img);
            }
            const pitch = previewCardImg.querySelector('.pitch');
            if (pitch) pitch.style.display = 'none';
            return img;
        }
        function updateLiveCover(files) {
            if (!previewCardImg) return;
            const img = ensureCoverImg();
            if (!img) return;
            if (files.length) {
                img.src = URL.createObjectURL(files[0]);
                img.style.display = 'block';
            } else {
                img.style.display = '';
                img.src = '';
                const pitch = previewCardImg.querySelector('.pitch');
                if (pitch) pitch.style.display = '';
                img.remove();
            }
        }
        function renderPreviews() {
            fileNames.textContent = selectedFiles.length ? selectedFiles.length + ' file(s) selected' : 'No files selected';
            if (!photoPreviewGrid) return;
            photoPreviewGrid.style.display = selectedFiles.length ? 'grid' : 'none';
            photoPreviewGrid.innerHTML = '';
            selectedFiles.forEach(function (file, idx) {
                const url = URL.createObjectURL(file);
                const item = document.createElement('div');
                item.className = 'photo-preview-item';
                item.innerHTML =
                    '<img src="' + url + '" alt="">' +
                    '<button type="button" class="photo-remove" data-idx="' + idx + '" title="Remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>';
                photoPreviewGrid.appendChild(item);
            });
            photoPreviewGrid.querySelectorAll('.photo-remove').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const idx = parseInt(btn.dataset.idx, 10);
                    selectedFiles.splice(idx, 1);
                    syncInput();
                    renderPreviews();
                });
            });
            updateLiveCover(selectedFiles);
        }
        function mergeFiles(newList) {
            newList.forEach(function (nf) {
                const dup = selectedFiles.some(function (sf) {
                    return sf.name === nf.name && sf.size === nf.size && sf.lastModified === nf.lastModified;
                });
                if (!dup) selectedFiles.push(nf);
            });
            syncInput();
            renderPreviews();
        }
        photoInput.addEventListener('change', function () {
            mergeFiles(Array.from(photoInput.files || []));
        });
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

    document.querySelectorAll('button[data-autogate]').forEach(function (btn) {
        const form = btn.closest('form');
        if (!form) return;
        function gate() {
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
        }
        form.addEventListener('input', gate);
        form.addEventListener('change', gate);
        form.addEventListener('animationstart', function (e) {
            if (e.animationName === 'autofill') gate();
        });
        window.addEventListener('load', gate);
        window.addEventListener('pageshow', gate);
        document.addEventListener('focusin', gate);
        var autofillChecks = 0;
        var recheck = setInterval(function () {
            autofillChecks++;
            gate();
            if (autofillChecks >= 5) clearInterval(recheck);
        }, 100);
        gate();
    });

    const termsCheck = document.getElementById('termsCheck');
    if (termsCheck) {
        const termsForm = termsCheck.closest('form');
        if (termsForm) {
            termsForm.addEventListener('submit', function (e) {
                if (!termsCheck.checked) {
                    e.preventDefault();
                    const wrap = termsCheck.closest('.check-line');
                    if (wrap) {
                        wrap.classList.add('field-error');
                        wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(function () { wrap.classList.remove('field-error'); }, 2500);
                    }
                    if (window.openErrorModal) openErrorModal('Please accept the Terms of Service and Privacy Policy to continue.', 'Almost there');
                }
            });
        }
    }

    const markAllBtn = document.getElementById('bellMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = markAllBtn.closest('form');
            if (!form) return;
            markAllBtn.disabled = true;
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
        });
    internalLinks.forEach(function (a) {
        a.addEventListener('click', function () { showTopBar(); });
    });
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

    const roleSelect = document.querySelector('.role-select');
    const roleHint = document.getElementById('roleHint');
    if (roleSelect) {
        roleSelect.addEventListener('click', function (e) {
            const option = e.target.closest('.role-option');
            if (!option) return;
            roleSelect.querySelectorAll('.role-option').forEach(function (o) {
                o.classList.remove('checked');
            });
            option.classList.add('checked');
            if (roleHint && option.dataset.hint) {
                roleHint.textContent = option.dataset.hint;
            }
        });
    }

    const googleLink = document.getElementById('googleLink');
    if (googleLink && roleSelect) {
        function syncGoogleRole() {
            const checked = roleSelect.querySelector('input[name="role"]:checked');
            const role = checked ? checked.value : 'user';
            googleLink.href = googleLink.href.split('?')[0] + '?intent=signup&role=' + role;
        }
        syncGoogleRole();
        roleSelect.addEventListener('click', syncGoogleRole);
    }

    const signupIntroToggle = document.getElementById('signupIntroToggle');
    const signupIntroDetails = document.getElementById('signupIntroDetails');
    if (signupIntroToggle && signupIntroDetails) {
        signupIntroToggle.addEventListener('click', function () {
            const open = signupIntroDetails.classList.toggle('open');
            signupIntroToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.querySelectorAll('a.btn-google[href*="login_google"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            let url = btn.getAttribute('href');
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'popup=1';
            const w = 560, h = 620;
            const left = Math.max(0, (window.screen.width - w) / 2);
            const top = Math.max(0, (window.screen.height - h) / 2);
            window.open(url, 'googleSignIn', 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes,popup=yes');
        });
    });

    document.querySelectorAll('.role-switch select[data-role-select]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const form = sel.closest('.role-switch');
            if (form) form.setAttribute('data-role', sel.value);
            sel.closest('form').submit();
        });
    });

    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show
                ? '<i class="fa-regular fa-eye-slash"></i>'
                : '<i class="fa-regular fa-eye"></i>';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    const pwInput = document.getElementById('password');
    const pwReqs = document.getElementById('pwRequirements');
    if (pwInput && pwReqs) {
        function checkRequirements() {
            const v = pwInput.value;
            pwReqs.querySelector('[data-req="length"]').classList.toggle('met', v.length >= 8);
            pwReqs.querySelector('[data-req="letter"]').classList.toggle('met', /[A-Za-z]/.test(v));
            pwReqs.querySelector('[data-req="number"]').classList.toggle('met', /[0-9]/.test(v));
            pwReqs.querySelector('[data-req="special"]').classList.toggle('met', /[^A-Za-z0-9]/.test(v));
        }
        pwInput.addEventListener('input', checkRequirements);
        checkRequirements();
    }

    const avatarInput = document.getElementById('avatar');
    const avatarForm = document.getElementById('avatarForm');
    if (avatarInput && avatarForm) {
        avatarInput.addEventListener('change', function () {
            if (avatarInput.files && avatarInput.files.length > 0) {
                const status = document.getElementById('avatarStatus');
                if (status) status.textContent = 'Uploading…';
                avatarForm.submit();
            }
        });
    }

    const payOptions = document.querySelector('.pay-options');
    const payBtn = document.getElementById('payBtn');
    if (payOptions && payBtn) {
        payOptions.addEventListener('click', function (e) {
            const option = e.target.closest('.pay-option');
            if (!option) return;
            payOptions.querySelectorAll('.pay-option').forEach(function (o) {
                o.classList.remove('checked');
            });
            option.classList.add('checked');
            option.querySelector('input').checked = true;
            payBtn.disabled = false;
        });
    }

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

    const lockNotice = document.getElementById('lockNotice');
    if (lockNotice) {
        const ends = Date.parse(lockNotice.dataset.lockEnds);
        const timerEl = document.getElementById('lockTimer');
        const barEl = document.getElementById('lockBar');
        const form = lockNotice.closest('.form-card');
        const inputs = form ? form.querySelectorAll('input, button') : [];
        function fmtLock(ms) {
            const total = Math.max(0, Math.floor(ms / 1000));
            const m = String(Math.floor(total / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            return m + ':' + s;
        }
        function tickLock() {
            const remain = ends - Date.now();
            if (timerEl) timerEl.textContent = fmtLock(remain);
            if (barEl && lockNotice.dataset.lockTotal) {
                const total = parseInt(lockNotice.dataset.lockTotal, 10) * 1000;
                const pct = Math.max(0, Math.min(100, (remain / total) * 100));
                barEl.style.width = pct + '%';
            }
            if (remain <= 0) {
                clearInterval(lockTimerId);
                if (lockNotice) lockNotice.style.display = 'none';
                inputs.forEach(function (el) { el.disabled = false; });
            }
        }
        const lockTimerId = setInterval(tickLock, 1000);
        tickLock();
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

    document.addEventListener('keydown', function (e) {
        if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) return;
        const btn = e.target.closest && e.target.closest('.slot-grid .slot');
        if (!btn || btn.disabled) return;
        const grid = btn.parentElement;
        if (!grid) return;
        const slots = Array.prototype.filter.call(grid.querySelectorAll('.slot'), function (s) {
            return !s.disabled;
        });
        if (slots.length < 2) return;
        const col = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
        const idx = slots.indexOf(btn);
        if (idx < 0) return;
        let next = idx;
        if (e.key === 'ArrowRight') next = idx + 1;
        else if (e.key === 'ArrowLeft') next = idx - 1;
        else if (e.key === 'ArrowDown') next = idx + col;
        else if (e.key === 'ArrowUp') next = idx - col;
        if (next < 0 || next >= slots.length) return;
        e.preventDefault();
        slots[next].focus();
        slots[next].click();
    });

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
});
