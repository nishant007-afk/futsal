document.addEventListener('DOMContentLoaded', function () {
    const pageSkeleton = document.getElementById('pageSkeleton');
    if (pageSkeleton) {
        let shown = false;
        const showTimer = setTimeout(function () {
            pageSkeleton.classList.add('visible');
            shown = true;
        }, 120);
        function hideSkeleton() {
            clearTimeout(showTimer);
            if (shown) {
                pageSkeleton.classList.add('hide');
                setTimeout(function () { pageSkeleton.remove(); }, 400);
            } else {
                pageSkeleton.remove();
            }
        }
        if (document.readyState === 'complete') {
            hideSkeleton();
        } else {
            window.addEventListener('load', hideSkeleton);
            setTimeout(hideSkeleton, 7000);
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
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            mainNav.classList.toggle('open');
            navToggle.innerHTML = mainNav.classList.contains('open')
                ? '<i class="fa-solid fa-xmark"></i>'
                : '<i class="fa-solid fa-bars"></i>';
        });
        document.addEventListener('click', function (e) {
            if (mainNav.classList.contains('open') && !mainNav.contains(e.target) && e.target !== navToggle) {
                mainNav.classList.remove('open');
                navToggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
            }
        });
    }

    const bellBtn = document.getElementById('bellBtn');
    const bellWrap = document.getElementById('bellWrap');
    if (bellBtn && bellWrap) {
        bellBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (profileWrap && profileWrap.classList.contains('open')) {
                profileWrap.classList.remove('open');
                if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
            }
            bellWrap.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!bellWrap.contains(e.target)) bellWrap.classList.remove('open');
        });
    }

    const profileBtn = document.getElementById('profileBtn');
    const profileWrap = document.getElementById('profileWrap');
    if (profileBtn && profileWrap) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (bellWrap && bellWrap.classList.contains('open')) bellWrap.classList.remove('open');
            profileWrap.classList.toggle('open');
            profileBtn.setAttribute('aria-expanded', profileWrap.classList.contains('open') ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!profileWrap.contains(e.target)) {
                profileWrap.classList.remove('open');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                profileWrap.classList.remove('open');
                profileBtn.setAttribute('aria-expanded', 'false');
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
        function onScroll() {
            const y = window.scrollY;
            if (y > lastY + 1) {
                siteHeader.classList.add('collapsed');
                if (mainNav) mainNav.classList.remove('open');
            } else if (y < lastY - 1) {
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
        galleryThumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                galleryMain.src = thumb.dataset.src;
                galleryThumbs.forEach(function (t) { t.classList.remove('active'); });
                thumb.classList.add('active');
            });
        });
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
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.hasAttribute('data-no-loader') || form.classList.contains('no-loader')) return;
        setTimeout(function () {
            if (!e.defaultPrevented) showLoader();
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
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML =
            '<div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
            '<h3>Are you sure?</h3>' +
            '<p>' + message.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' +
            '<div class="modal-actions">' +
            '<button type="button" class="btn btn-ghost" data-modal-cancel>' + (labels.cancelText || 'Cancel') + '</button>' +
            '<button type="button" class="btn btn-danger" data-modal-ok>' + (labels.okText || 'Yes, continue') + '</button>' +
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
    toasts.forEach(function (t, i) {
        const close = t.querySelector('.toast-close');
        if (close) close.addEventListener('click', function () { dismissToast(t); });
        if (t.classList.contains('toast-inline')) {
            setTimeout(function () { dismissToast(t); }, 2500);
            return;
        }
        showBackdrop();
        t.style.top = 'calc(50% + ' + ((i - (toasts.length - 1) / 2) * 64) + 'px)';
        setTimeout(function () { dismissToast(t); }, 2500);
        if (t.classList.contains('toast-error')) {
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
});
