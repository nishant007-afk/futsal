/* GoalSpace module: auth pages (role select, google sign-in, password helpers,
   avatar upload, account lockout countdown, terms check). Loaded only on auth/settings pages. */
document.addEventListener('DOMContentLoaded', function () {
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
                    if (window.openErrorModal) openErrorModal('Please accept the Terms of Service and Privacy Policy to continue.', 'Almost there', { button: false });
                }
            });
        }
    }

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

    const pwInputs = document.querySelectorAll('#password, #pwNew');
    const pwReqs = document.getElementById('pwRequirements');
    if (pwReqs && pwInputs.length) {
        pwInputs.forEach(function (input) {
            function openReqs() { pwReqs.classList.add('open'); }
            input.addEventListener('focus', openReqs);
            input.addEventListener('input', openReqs);
            function checkRequirements() {
                const v = input.value;
                var checks = {
                    length: v.length >= 8,
                    special: /[^A-Za-z0-9]/.test(v),
                    upper: /[A-Z]/.test(v),
                    number: /[0-9]/.test(v)
                };
                Object.keys(checks).forEach(function (key) {
                    var seg = pwReqs.querySelector('.pw-meter-seg[data-req="' + key + '"]');
                    var label = pwReqs.querySelector('.pw-meter-labels span[data-req="' + key + '"]');
                    if (seg) seg.classList.toggle('met', checks[key]);
                    if (label) label.classList.toggle('met', checks[key]);
                });
            }
            input.addEventListener('input', checkRequirements);
            checkRequirements();
        });
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
});