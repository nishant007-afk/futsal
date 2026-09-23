/* GoalSpace module: booking pages (free time pickers / reschedule slots, gallery, payment).
   Loaded only on ground/book/payment/reschedule pages. */
document.addEventListener('DOMContentLoaded', function () {
    const slotGrid = document.getElementById('slotGrid');
    const selectedSlot = document.getElementById('selectedSlot');
    const bookBtn = document.getElementById('bookBtn');
    const startTime = document.getElementById('startTime');
    const endTime = document.getElementById('endTime');
    const priceHint = document.getElementById('priceHint');
    const hourlyPrice = priceHint && priceHint.dataset.hourly ? Number(priceHint.dataset.hourly) : null;

    function toHMS(hm) {
        if (!hm) return '';
        return hm.length === 5 ? hm + ':00' : hm;
    }

    function minutesOf(hm) {
        if (!hm) return NaN;
        const p = hm.split(':');
        return Number(p[0]) * 60 + Number(p[1]);
    }

    function syncFreeTime() {
        if (!startTime || !endTime || !selectedSlot) return;
        const s = startTime.value;
        const e = endTime.value;
        if (!s || !e) {
            selectedSlot.value = '';
            if (bookBtn) bookBtn.disabled = true;
            if (priceHint && !priceHint.dataset.hourly) priceHint.textContent = 'Choose any open hours';
            return;
        }
        if (minutesOf(e) <= minutesOf(s)) {
            selectedSlot.value = '';
            if (bookBtn) bookBtn.disabled = true;
            if (priceHint) priceHint.textContent = 'End must be after start';
            return;
        }
        selectedSlot.value = toHMS(s) + '|' + toHMS(e);
        if (bookBtn) {
            bookBtn.disabled = false;
            bookBtn.removeAttribute('aria-disabled');
        }
        if (priceHint) {
            const mins = minutesOf(e) - minutesOf(s);
            const hrs = mins / 60;
            if (hourlyPrice && Number.isFinite(hourlyPrice)) {
                const total = Math.round(hourlyPrice * hrs);
                priceHint.textContent = hrs + ' h · Rs ' + total.toLocaleString();
            } else {
                priceHint.textContent = s + ' – ' + e;
            }
        }
    }

    if (startTime && endTime) {
        startTime.addEventListener('change', syncFreeTime);
        endTime.addEventListener('change', syncFreeTime);
        startTime.addEventListener('input', syncFreeTime);
        endTime.addEventListener('input', syncFreeTime);
        if (bookBtn) bookBtn.disabled = true;
        if (!slotGrid) {
            syncFreeTime();
        }
    }

    if (slotGrid && selectedSlot) {
        if (bookBtn) bookBtn.disabled = true;

        slotGrid.addEventListener('click', function (e) {
            const slot = e.target.closest('.slot');
            if (!slot || slot.classList.contains('taken')) return;

            document.querySelectorAll('.slot.selected').forEach(function (s) {
                s.classList.remove('selected');
                s.setAttribute('aria-pressed', 'false');
            });
            slot.classList.add('selected');
            slot.setAttribute('aria-pressed', 'true');

            selectedSlot.value = slot.dataset.start + '|' + slot.dataset.end;
            if (startTime && slot.dataset.start) startTime.value = slot.dataset.start.slice(0, 5);
            if (endTime && slot.dataset.end) endTime.value = slot.dataset.end.slice(0, 5);

            const priceEl = document.getElementById('priceHint');
            if (priceEl && slot.dataset.label) {
                priceEl.textContent = 'Selected: ' + slot.dataset.label + (slot.dataset.price ? ' · ' + slot.dataset.price : '');
            }
            if (bookBtn) {
                bookBtn.disabled = false;
                bookBtn.removeAttribute('aria-disabled');
            }
        });
    }

    // Guard: never POST without a chosen window (Enter key / stale disabled state).
    const bookForm = document.getElementById('bookBtn') && document.getElementById('bookBtn').form;
    if (bookForm) {
        bookForm.addEventListener('submit', function (e) {
            const slotVal = selectedSlot ? selectedSlot.value : '';
            const hasFree = startTime && endTime && startTime.value && endTime.value;
            if (!hasFree && (!slotVal || slotVal.indexOf('|') === -1)) {
                e.preventDefault();
                if (window.openErrorModal) {
                    openErrorModal('Pick a From and To time in the booking panel, then press Reserve again.', 'No time selected');
                } else {
                    alert('Please choose a start and end time.');
                }
                return false;
            }
            if (hasFree && selectedSlot && selectedSlot.value.indexOf('|') === -1) {
                e.preventDefault();
                if (window.openErrorModal) {
                    openErrorModal('End time must be after start time.', 'Check times');
                } else {
                    alert('End time must be after start time.');
                }
                return false;
            }
            if (bookBtn) {
                bookBtn.disabled = true;
                bookBtn.classList.add('btn-loading');
            }
            return true;
        });
    }

    const repeatToggle = document.getElementById('repeatToggle');
    const repeatWeeksWrap = document.getElementById('repeatWeeksWrap');
    if (repeatToggle && repeatWeeksWrap) {
        repeatToggle.addEventListener('change', function () {
            repeatWeeksWrap.style.display = repeatToggle.checked ? 'flex' : 'none';
        });
    }

    const dateInput = document.getElementById('bookingDate');
    if (dateInput && dateInput.closest('form')) {
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

    /* Checkout: payment method cards + advance/full split pills (payment.php) */
    window.selectPayMethod = function (method) {
        var cardQr = document.getElementById('cardMethodQr');
        var cardCourt = document.getElementById('cardMethodCourt');
        var courtBox = document.getElementById('courtPayBox');
        var headQr = cardQr ? cardQr.querySelector('.pm-head') : null;
        var headCourt = cardCourt ? cardCourt.querySelector('.pm-head') : null;
        if (method === 'court') {
            if (cardQr) cardQr.classList.remove('active');
            if (cardCourt) cardCourt.classList.add('active');
            if (courtBox) courtBox.style.display = 'block';
            if (headQr) headQr.setAttribute('aria-checked', 'false');
            if (headCourt) headCourt.setAttribute('aria-checked', 'true');
        } else {
            if (cardCourt) cardCourt.classList.remove('active');
            if (cardQr) cardQr.classList.add('active');
            if (courtBox) courtBox.style.display = 'none';
            if (headQr) headQr.setAttribute('aria-checked', 'true');
            if (headCourt) headCourt.setAttribute('aria-checked', 'false');
        }
    };

    window.setSplitOption = function (choice, amount) {
        var pillAdv = document.getElementById('pillAdvance');
        var pillFull = document.getElementById('pillFull');
        var input = document.getElementById('splitChoiceInput');
        var btnLabel = document.getElementById('btnSubmitQrLabel');
        if (input) input.value = choice;
        if (choice === 'full') {
            if (pillAdv) {
                pillAdv.classList.remove('active');
                pillAdv.setAttribute('aria-checked', 'false');
            }
            if (pillFull) {
                pillFull.classList.add('active');
                pillFull.setAttribute('aria-checked', 'true');
            }
        } else {
            if (pillFull) {
                pillFull.classList.remove('active');
                pillFull.setAttribute('aria-checked', 'false');
            }
            if (pillAdv) {
                pillAdv.classList.add('active');
                pillAdv.setAttribute('aria-checked', 'true');
            }
        }
        var formatted = 'Rs ' + Number(amount).toLocaleString();
        if (btnLabel) btnLabel.textContent = 'I paid — submit ' + formatted;
    };

    var pmHeads = document.querySelectorAll('.pay-method-card .pm-head');
    if (pmHeads.length) {
        pmHeads.forEach(function (head) {
            function activate() {
                var method = head.getAttribute('data-method');
                if (method) selectPayMethod(method);
            }
            head.addEventListener('click', activate);
            head.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    activate();
                }
            });
        });
    }

    var splitPills = document.querySelectorAll('.split-pill');
    if (splitPills.length) {
        splitPills.forEach(function (pill) {
            function activate() {
                var split = pill.getAttribute('data-split');
                var amount = pill.getAttribute('data-amount');
                if (split) setSplitOption(split, amount);
            }
            pill.addEventListener('click', activate);
            pill.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    activate();
                } else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    e.preventDefault();
                    var sibling = e.key === 'ArrowRight' ? pill.nextElementSibling : pill.previousElementSibling;
                    if (sibling && sibling.classList.contains('split-pill')) {
                        sibling.focus();
                        sibling.click();
                    }
                }
            });
        });
    }
});