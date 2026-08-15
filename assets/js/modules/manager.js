/* GoalSpace module: manager/admin admin pages (settlement modal, repeat toggle,
   ground form live preview, photo upload). Loaded only for manager/admin roles. */
document.addEventListener('DOMContentLoaded', function () {
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

    const qrInput = document.getElementById('qrInput');
    const qrFileName = document.getElementById('qrFileName');
    if (qrInput && qrFileName) {
        qrInput.addEventListener('change', function () {
            qrFileName.textContent = qrInput.files && qrInput.files.length
                ? qrInput.files[0].name
                : 'No file selected';
        });
    }
    var qrInputEdit = document.getElementById('qrInputEdit');
    var qrFileNameEdit = document.getElementById('qrFileNameEdit');
    if (qrInputEdit && qrFileNameEdit) {
        qrInputEdit.addEventListener('change', function () {
            qrFileNameEdit.textContent = qrInputEdit.files && qrInputEdit.files.length
                ? qrInputEdit.files[0].name
                : 'No file selected';
        });
    }
});