/* GoalSpace module: booking pages (slot grid, gallery, payment options, date picker).
   Loaded only on ground/book/payment pages. */
document.addEventListener('DOMContentLoaded', function () {
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
});