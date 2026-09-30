/* GoalSpace module: booking pages (free time pickers / reschedule slots, gallery, payment).
   Loaded only on ground/book/payment/reschedule pages. */
document.addEventListener('DOMContentLoaded', function () {
    const slotGrid = document.getElementById('slotGrid');
    const selectedSlot = document.getElementById('selectedSlot');
    const bookBtn = document.getElementById('bookBtn');
    const startTime = document.getElementById('startTime');
    const endTime = document.getElementById('endTime');
    const priceHint = document.getElementById('priceHint');
    const hourlyPrice = (priceHint && priceHint.dataset.hourly) ? Number(priceHint.dataset.hourly)
        : (slotGrid && slotGrid.dataset.hourly ? Number(slotGrid.dataset.hourly) : null);

    function toHMS(hm) {
        if (!hm) return '';
        return hm.length === 5 ? hm + ':00' : hm;
    }

    function minutesOf(hm) {
        if (!hm) return NaN;
        const p = hm.split(':');
        return Number(p[0]) * 60 + Number(p[1]);
    }

    /* Selection summary above the CTA (ground page booking panel) */
    const bpSummary = document.getElementById('bpSummary');
    const sumWhen = document.getElementById('sumWhen');
    const sumNote = document.getElementById('sumNote');
    const sumPrice = document.getElementById('sumPrice');

    function fmtDayLabel(iso) {
        if (!iso) return '';
        const p = iso.split('-');
        const d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
        if (isNaN(d.getTime())) return iso;
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const mons = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return days[d.getDay()] + ', ' + d.getDate() + ' ' + mons[d.getMonth()];
    }

    function fmtHM12(hm) {
        if (!hm) return '';
        const p = hm.split(':');
        let h = Number(p[0]);
        const min = p[1] || '00';
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return h + ':' + min + ' ' + ampm;
    }

    function updateSummary(startHM, endHM) {
        if (!bpSummary) return;
        if (!startHM || !endHM || minutesOf(endHM) <= minutesOf(startHM)) {
            bpSummary.hidden = true;
            return;
        }
        const gridDate = slotGrid && slotGrid.dataset.date ? slotGrid.dataset.date : '';
        const hrs = (minutesOf(endHM) - minutesOf(startHM)) / 60;
        bpSummary.hidden = false;
        if (sumWhen) sumWhen.textContent = 'Selected: ' + fmtDayLabel(gridDate) + ' • ' + fmtHM12(startHM) + ' - ' + fmtHM12(endHM);
        if (sumNote) {
            const hrsN = Math.round(hrs * 100) / 100;
            sumNote.textContent = '(' + (hrsN % 1 === 0 ? hrsN : hrsN.toFixed(1)) + ' hr' + (hrsN > 1 ? 's' : '') + ')';
        }
        if (sumPrice) {
            sumPrice.textContent = (hourlyPrice && Number.isFinite(hourlyPrice))
                ? 'Rs ' + Math.round(hourlyPrice * hrs).toLocaleString()
                : '';
        }
        syncStickyBar();
    }

    /* Mobile sticky bar: mirror the selection, route the CTA. */
    const bsbSlot = document.getElementById('bsbSlot');
    const bsbBook = document.getElementById('bsbBook');

    function syncStickyBar() {
        if (!bsbSlot || !bsbBook) return;
        const sel = selectedSlot ? selectedSlot.value : '';
        if (sel) {
            const parts = sel.split('|');
            bsbSlot.textContent = fmtHM12(parts[0].slice(0, 5)) + ' – ' + fmtHM12(parts[1].slice(0, 5));
            bsbBook.textContent = 'Book Slot';
        } else {
            bsbSlot.textContent = 'Select a time';
            bsbBook.textContent = 'Select Time';
        }
    }

    if (bsbBook) {
        bsbBook.addEventListener('click', function () {
            if (selectedSlot && selectedSlot.value) {
                if (bookBtn) bookBtn.click();
                return;
            }
            const grid = document.getElementById('slotGrid');
            if (grid) {
                grid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                grid.classList.add('bp-flash');
                setTimeout(function () { grid.classList.remove('bp-flash'); }, 1200);
            }
        });
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
        updateSummary(s, e);
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

    /* Slot period filter tabs (All / Morning / Afternoon / Evening) */
    const slotTabs = document.querySelectorAll('.slot-tab');
    slotTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            slotTabs.forEach(function (t) {
                t.classList.toggle('active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });
            const f = tab.dataset.filter;
            document.querySelectorAll('#slotGrid .slot').forEach(function (s) {
                s.classList.toggle('is-hidden', f !== 'all' && s.dataset.period !== f);
            });
        });
    });

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

            if (priceHint && slot.dataset.label) {
                priceHint.textContent = 'Selected: ' + slot.dataset.label + (slot.dataset.price ? ' · ' + slot.dataset.price : '');
            }
            updateSummary(slot.dataset.start.slice(0, 5), slot.dataset.end.slice(0, 5));
            if (bookBtn) {
                bookBtn.disabled = false;
                bookBtn.removeAttribute('aria-disabled');
                if (bpSummary) bookBtn.textContent = 'Reserve this slot';
            }
        });
    }

    /* Compact calendar: chips first, native date picker behind the icon */
    const calToggle = document.getElementById('calToggle');
    const bpDateField = document.getElementById('bpDateField');
    if (calToggle && bpDateField) {
        calToggle.addEventListener('click', function () {
            const show = bpDateField.hasAttribute('hidden');
            if (show) {
                bpDateField.removeAttribute('hidden');
                calToggle.setAttribute('aria-expanded', 'true');
                const inp = bpDateField.querySelector('input[type="date"]');
                if (inp) {
                    const r = calToggle.getBoundingClientRect();
                    let cb = null;
                    let el = inp.parentElement;
                    while (el && el !== document.body) {
                        const cs = getComputedStyle(el);
                        if (cs.transform !== 'none' || cs.filter !== 'none' || cs.perspective !== 'none' || cs.contain !== 'none' || cs.willChange === 'transform' || cs.backdropFilter !== 'none') { cb = el; break; }
                        el = el.parentElement;
                    }
                    const cbr = cb ? cb.getBoundingClientRect() : { left: 0, top: 0 };
                    inp.style.position = 'fixed';
                    inp.style.left = Math.max(8, r.left - cbr.left) + 'px';
                    inp.style.top = (r.bottom + 6 - cbr.top) + 'px';
                    inp.style.width = '1px';
                    inp.style.height = '1px';
                    inp.style.opacity = '0';
                    inp.style.zIndex = '9999';
                    inp.style.pointerEvents = 'none';
                    if (typeof inp.showPicker === 'function') {
                        try { inp.showPicker(); } catch (err) { inp.focus(); }
                    } else {
                        inp.focus();
                    }
                }
            } else {
                bpDateField.setAttribute('hidden', '');
                calToggle.setAttribute('aria-expanded', 'false');
            }
        });
        const dateInput = bpDateField.querySelector('input[type="date"]');
        if (dateInput) {
            dateInput.addEventListener('change', function () {
                if (dateInput.value) bpDateField.submit();
            });
        }
    }

    /* Date chips: scroll affordance */
    const dateChips = document.getElementById('dateChips');
    if (dateChips) {
        const syncChips = function () {
            const scrollable = dateChips.scrollWidth > dateChips.clientWidth + 4;
            dateChips.classList.toggle('is-scrollable', scrollable);
        };
        dateChips.addEventListener('scroll', syncChips);
        window.addEventListener('resize', syncChips);
        syncChips();
    }

    // Guard: never POST without a chosen window (Enter key / stale disabled state).
    const bookForm = document.getElementById('bookBtn') && document.getElementById('bookBtn').form;
    if (bookForm) {
        bookForm.addEventListener('submit', function (e) {
            const slotVal = selectedSlot ? selectedSlot.value : '';
            const hasFree = startTime && endTime && startTime.value && endTime.value;
            if (!hasFree && (!slotVal || slotVal.indexOf('|') === -1)) {
                e.preventDefault();
                if (window.GoalSpace && window.GoalSpace.toast) {
                    window.GoalSpace.toast({
                        type: 'error',
                        message: 'No time slot selected.',
                        why: 'You tried to reserve without picking an open slot.',
                        how: 'Tap an available hour above, then press Reserve.'
                    });
                } else if (window.openErrorModal) {
                    openErrorModal('Pick a From and To time in the booking panel, then press Reserve again.', 'No time selected');
                }
                return false;
            }
            if (hasFree && selectedSlot && selectedSlot.value.indexOf('|') === -1) {
                e.preventDefault();
                if (window.GoalSpace && window.GoalSpace.toast) {
                    window.GoalSpace.toast({
                        type: 'error',
                        message: 'Invalid time selection.',
                        why: 'The end time must be after the start time.',
                        how: 'Choose an end time that gives at least 1 hour of play.'
                    });
                } else if (window.openErrorModal) {
                    openErrorModal('End time must be after start time.', 'Check times');
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
    const galleryWrap = document.getElementById('galleryWrap');
    const galleryDots = document.querySelectorAll('.gallery-dot');
    if (galleryMain) {
        let srcs = [];
        try { srcs = JSON.parse((galleryWrap && galleryWrap.getAttribute('data-images')) || '[]'); } catch (err) { srcs = []; }
        if (!srcs.length) srcs = [galleryMain.currentSrc || galleryMain.src];
        let current = 0;
        const prevBtn = document.querySelector('.gallery-prev');
        const nextBtn = document.querySelector('.gallery-next');
        const zoomBtn = document.querySelector('.gallery-zoom');
        function updateNav() {
            const showPrev = current > 0;
            const showNext = current < srcs.length - 1;
            if (prevBtn) prevBtn.hidden = !showPrev;
            if (nextBtn) nextBtn.hidden = !showNext;
            galleryDots.forEach(function (d, i) {
                const on = i === current;
                d.classList.toggle('active', on);
                if (on) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
            });
        }
        let galleryAnim = false;
        let activeGhost = null;
        function finishSlide() {
            if (activeGhost && activeGhost.parentNode) activeGhost.parentNode.removeChild(activeGhost);
            activeGhost = null;
            galleryMain.style.transition = '';
            galleryMain.style.transform = '';
            galleryMain.style.zIndex = '';
            galleryAnim = false;
        }
        function show(src) {
            if (galleryAnim) finishSlide();
            galleryMain.loading = 'eager';
            galleryMain.src = src;
            galleryMain.setAttribute('src', src);
            if (zoomBtn) zoomBtn.dataset.src = src;
            updateNav();
        }
        /* Arrows: gallery swipe - the old photo slides out while the new one
           slides in from the opposite edge, both on the same eased curve.
           Dots use show() and stay instant. */
        function slideShow(i, dir) {
            if (galleryAnim) finishSlide();
            galleryAnim = true;
            const host = galleryMain.parentNode;
            const W = galleryMain.offsetWidth || host.offsetWidth;
            const enterX = dir > 0 ? W : -W;
            const exitX = dir > 0 ? -W : W;
            const ghost = galleryMain.cloneNode(false);
            ghost.removeAttribute('id');
            ghost.alt = '';
            ghost.style.position = 'absolute';
            ghost.style.left = '0';
            ghost.style.top = '0';
            ghost.style.width = W + 'px';
            ghost.style.height = '100%';
            ghost.style.zIndex = '1';
            ghost.style.transform = '';
            ghost.style.transition = '';
            host.insertBefore(ghost, galleryMain);
            galleryMain.loading = 'eager';
            galleryMain.src = srcs[i];
            galleryMain.setAttribute('src', srcs[i]);
            if (zoomBtn) zoomBtn.dataset.src = srcs[i];
            galleryMain.style.transition = 'none';
            galleryMain.style.transform = 'translateX(' + enterX + 'px)';
            galleryMain.style.zIndex = '2';
            void galleryMain.offsetWidth;
            const ease = 'transform .34s cubic-bezier(.2,.7,.3,1)';
            galleryMain.style.transition = ease;
            galleryMain.style.transform = 'translateX(0)';
            ghost.style.transition = ease;
            ghost.style.transform = 'translateX(' + exitX + 'px)';
            current = i;
            updateNav();
            activeGhost = ghost;
            setTimeout(finishSlide, 360);
        }
        function goTo(i, dir) {
            if (i < 0) i = srcs.length - 1;
            if (i > srcs.length - 1) i = 0;
            if (dir) { slideShow(i, dir); } else { current = i; show(srcs[i]); }
        }
        galleryDots.forEach(function (dot, i) {
            dot.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                current = i;
                show(srcs[i]);
            });
        });
        if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1, -1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1, 1); });
        if (zoomBtn) zoomBtn.addEventListener('click', function () {
            openImageZoom(current);
        });
        if (zoomBtn) zoomBtn.dataset.src = srcs[0];
        updateNav();
        // warm the full set so a swipe never waits on an image decode
        srcs.forEach(function (s) { const pre = new Image(); pre.src = s; });

        /* Shared swipe engine (inline card + zoom overlay): axis is locked on the
           first real move, the photo follows the finger, and ONE decisive release
           flips the image - a full drag (40px+) or a quick short flick. */
        function attachSwipe(el, getImg, onSwipe, getNeighborSrc) {
            let x0 = 0, y0 = 0, t0 = 0, axis = '';
            let ghost = null, preview = null, previewDir = 0, previewW = 0, rafId = 0;
            function removePreview() {
                if (preview && preview.parentNode) preview.parentNode.removeChild(preview);
                preview = null;
                previewDir = 0;
            }
            function cleanup() {
                if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
                if (ghost && ghost.parentNode) ghost.parentNode.removeChild(ghost);
                ghost = null;
                removePreview();
                const img = getImg();
                if (img) {
                    img.style.position = '';
                    img.style.zIndex = '';
                    img.style.transition = '';
                    img.style.transform = '';
                }
            }
            el.addEventListener('touchstart', function (e) {
                if (e.touches.length !== 1) return;
                cleanup(); // interrupt any slide still running, never two at once
                x0 = e.touches[0].clientX;
                y0 = e.touches[0].clientY;
                t0 = Date.now();
                axis = '';
                const img = getImg();
                if (img) img.style.transition = 'none';
            }, { passive: true });
            el.addEventListener('touchmove', function (e) {
                if (e.touches.length !== 1) return;
                const dx = e.touches[0].clientX - x0;
                const dy = e.touches[0].clientY - y0;
                if (!axis) {
                    if (Math.abs(dx) < 10 && Math.abs(dy) < 10) return;
                    axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                }
                if (axis !== 'x') return;
                const img = getImg();
                if (!img) return;
                img.style.transform = 'translateX(' + dx + 'px)';

                /* Trace: the neighbor photo sits glued to the dragged edge, so the
                   image you're heading towards is already visible while you drag -
                   exactly like the system photo gallery. Rebuilds if you reverse. */
                if (!img.parentNode || dx === 0) return;
                const dir = dx < 0 ? 1 : -1;
                if (previewDir !== dir) {
                    removePreview();
                    const src = typeof getNeighborSrc === 'function' ? getNeighborSrc(dir) : null;
                    if (!src) return;
                    previewW = Math.round(img.getBoundingClientRect().width) ||
                        Math.round(el.getBoundingClientRect().width);
                    preview = img.cloneNode(false);
                    preview.removeAttribute('id');
                    preview.src = src;
                    preview.style.transition = 'none';
                    preview.style.position = 'absolute';
                    preview.style.left = '0';
                    preview.style.top = '0';
                    preview.style.width = previewW + 'px';
                    preview.style.height = '100%';
                    preview.style.zIndex = '1';
                    preview.style.pointerEvents = 'none';
                    img.parentNode.appendChild(preview);
                    previewDir = dir;
                    img.style.position = 'relative';
                    img.style.zIndex = '2';
                }
                if (preview) preview.style.transform = 'translateX(' + (dx + previewDir * previewW) + 'px)';
            }, { passive: true });
            el.addEventListener('touchend', function (e) {
                const usedAxis = axis;
                axis = '';
                const img = getImg();
                const dx = e.changedTouches[0].clientX - x0;
                const dt = Date.now() - t0;
                const passed = Math.abs(dx) >= 40 ||
                    (Math.abs(dx) >= 26 && dt >= 80 && Math.abs(dx) / dt >= 0.25);
                if (usedAxis !== 'x' || !passed) {
                    removePreview();
                    if (img) {
                        img.style.transition = 'transform .22s ease';
                        img.style.transform = '';
                        img.style.position = '';
                        img.style.zIndex = '';
                    }
                    return;
                }
                const dir = dx < 0 ? 1 : -1;
                removePreview(); // the real <img> takes the neighbor's spot this same frame
                if (!img || !img.parentNode) { onSwipe(dir); return; }

                const W = previewW || Math.round(img.getBoundingClientRect().width) ||
                    Math.round(el.getBoundingClientRect().width);
                const from = dx;         // where the finger left the photo
                const imgFrom = dir > 0 ? W + dx : dx - W; // neighbor starts glued to the dragged edge

                /* Seamless hand-off: a clone of the CURRENT photo keeps covering the
                   frame where the finger dropped it, while the real <img> is swapped
                   to the NEXT src (preloaded) and parked just off the far edge. Both
                   travel the same distance on the same eased curve, so their edges
                   stay glued together - the frame is covered at every single step,
                   no white/blank frame anywhere. */
                ghost = img.cloneNode(false);
                ghost.removeAttribute('id');
                ghost.style.position = 'absolute';
                ghost.style.left = '0';
                ghost.style.top = '0';
                ghost.style.width = W + 'px';
                ghost.style.height = '100%';
                ghost.style.zIndex = '1';
                ghost.style.pointerEvents = 'none';
                img.parentNode.appendChild(ghost);

                img.style.position = 'relative';
                img.style.zIndex = '2';
                img.style.transition = 'none';
                img.style.transform = 'translateX(' + imgFrom + 'px)';
                onSwipe(dir); // src swap happens now, while the next photo sits off-frame

                const dur = 240;
                const started = performance.now();
                rafId = requestAnimationFrame(function step(now) {
                    const p = Math.min(1, (now - started) / dur);
                    const e = 1 - Math.pow(1 - p, 3); // shared easeOutCubic keeps the seam glued
                    ghost.style.transform = 'translateX(' + (from + (dir > 0 ? -W - from : W - from) * e) + 'px)';
                    img.style.transform = 'translateX(' + (imgFrom * (1 - e)) + 'px)';
                    if (p < 1) {
                        rafId = requestAnimationFrame(step);
                    } else {
                        cleanup();
                    }
                });
            }, { passive: true });
        }

        /* Swipe on the photo card itself (no zoom needed) */
        if (galleryWrap) {
            attachSwipe(galleryWrap, function () { return galleryMain; }, function (dir) {
                goTo(current + dir);
            }, function (dir) {
                const n = srcs.length;
                if (n < 2) return null;
                return srcs[(current + dir + n) % n];
            });
        }

        var openImageZoom = function (index) {
            const existing = document.querySelector('.image-zoom');
            if (existing) existing.remove();
            const overlay = document.createElement('div');
            overlay.className = 'image-zoom';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML =
                '<button type="button" class="iz-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>' +
                '<div class="iz-stage"><img class="iz-img" src="' + srcs[index] + '" alt="">' +
                (srcs.length > 1
                    ? '<button type="button" class="iz-prev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>' +
                      '<button type="button" class="iz-next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>'
                    : '') +
                '</div>' +
                (srcs.length > 1
                    ? '<div class="iz-dots">' + srcs.map(function (_, di) {
                        return '<button type="button" class="iz-dot' + (di === index ? ' active' : '') + '" aria-label="Photo ' + (di + 1) + ' of ' + srcs.length + '"' + (di === index ? ' aria-current="true"' : '') + '></button>';
                    }).join('') + '</div>'
                    : '');
            document.body.appendChild(overlay);
            document.body.classList.add('modal-open');
            let i = index;
            const img = overlay.querySelector('.iz-img');
            function syncDots() {
                overlay.querySelectorAll('.iz-dot').forEach(function (d, di) {
                    const on = di === i;
                    d.classList.toggle('active', on);
                    if (on) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
                });
            }
            let zoomAnim = false;
            let zoomGhost = null;
            function finishZoomSlide() {
                if (zoomGhost && zoomGhost.parentNode) zoomGhost.parentNode.removeChild(zoomGhost);
                zoomGhost = null;
                img.style.transition = '';
                img.style.transform = '';
                img.style.zIndex = '';
                zoomAnim = false;
            }
            function render(next, dir) {
                i = (next + srcs.length) % srcs.length;
                current = i;
                if (dir && !zoomAnim) {
                    zoomAnim = true;
                    const stage = img.parentNode;
                    const W = stage.offsetWidth || img.offsetWidth;
                    const enterX = dir > 0 ? W : -W;
                    const exitX = dir > 0 ? -W : W;
                    const ghost = img.cloneNode(false);
                    ghost.removeAttribute('id');
                    ghost.alt = '';
                    ghost.style.position = 'absolute';
                    ghost.style.left = '0';
                    ghost.style.top = '0';
                    ghost.style.width = W + 'px';
                    ghost.style.height = '100%';
                    ghost.style.zIndex = '1';
                    ghost.style.transform = '';
                    ghost.style.transition = '';
                    stage.insertBefore(ghost, img);
                    img.src = srcs[i];
                    galleryMain.src = srcs[i];
                    img.style.transition = 'none';
                    img.style.transform = 'translateX(' + enterX + 'px)';
                    img.style.zIndex = '2';
                    void img.offsetWidth;
                    const ease = 'transform .34s cubic-bezier(.2,.7,.3,1)';
                    img.style.transition = ease;
                    img.style.transform = 'translateX(0)';
                    ghost.style.transition = ease;
                    ghost.style.transform = 'translateX(' + exitX + 'px)';
                    zoomGhost = ghost;
                    setTimeout(finishZoomSlide, 360);
                } else {
                    if (zoomAnim) finishZoomSlide();
                    img.src = srcs[i];
                    galleryMain.src = srcs[i];
                }
                updateNav();
                syncDots();
            }
            overlay.addEventListener('click', function (e) {
                const t = e.target;
                if (t.closest('.iz-close')) { closeZoom(); return; }
                if (t.closest('.iz-prev')) { render(i - 1, -1); return; }
                if (t.closest('.iz-next')) { render(i + 1, 1); return; }
                const dot = t.closest('.iz-dot');
                if (dot) {
                    const dots = Array.prototype.slice.call(overlay.querySelectorAll('.iz-dot'));
                    const di = dots.indexOf(dot);
                    if (di > -1) render(di);
                    return;
                }
                if (t === overlay) closeZoom();
            });
            function closeZoom() {
                overlay.remove();
                document.body.classList.remove('modal-open');
                document.removeEventListener('keydown', onKey);
            }
            function onKey(e) {
                if (e.key === 'Escape') closeZoom();
                else if (e.key === 'ArrowLeft') render(i - 1, -1);
                else if (e.key === 'ArrowRight') render(i + 1, 1);
            }
            document.addEventListener('keydown', onKey);

            attachSwipe(overlay, function () { return img; }, function (dir) {
                render(i + dir);
            }, function (dir) {
                const n = srcs.length;
                if (n < 2) return null;
                return srcs[(i + dir + n) % n];
            });
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
        if (btnLabel) btnLabel.textContent = 'I paid - submit ' + formatted;
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