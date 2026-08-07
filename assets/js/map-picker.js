/* Location picker for admin/manager ground forms (Leaflet + free OSM geocoders).
   Google-Maps style: type to see matching places & venues (futsal, restaurants…),
   pick one to drop the pin and fill the Location text field. */
(function () {
    'use strict';
    var group = document.getElementById('locationMap');
    if (!group) { return; }

    var latInput = document.getElementById('gmLatitude');
    var lngInput = document.getElementById('gmLongitude');
    var coordText = document.getElementById('mapCoords');
    var searchInput = document.getElementById('mapSearch');
    var resultsBox = document.getElementById('mapResults');
    var locationField = document.getElementById('location');

    var startLat = parseFloat(document.getElementById('gmMapLat').value || '27.7172');
    var startLng = parseFloat(document.getElementById('gmMapLng').value || '85.3240');
    var hasPin = latInput.value !== '' && lngInput.value !== '';

    var map = L.map(group, {
        center: [startLat, startLng],
        zoom: hasPin ? 16 : 12,
        scrollWheelZoom: false,
        zoomControl: true
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }).addTo(map);

    var icon = L.divIcon({
        className: 'gm-pin',
        html: '<span class="gm-pin-pin"></span><span class="gm-pin-shadow"></span>',
        iconSize: [28, 44],
        iconAnchor: [14, 42],
        popupAnchor: [0, -40]
    });

    var marker = null;
    function setPin(lat, lng, zoomIn) {
        if (marker) { map.removeLayer(marker); }
        marker = L.marker([lat, lng], { icon: icon }).addTo(map);
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        coordText.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        if (zoomIn) { map.setView([lat, lng], 16); }
    }

    function reverseGeocode(lat, lng) {
        var base = document.body.getAttribute('data-base') || '';
        fetch(base + '/ajax/place_search.php?lat=' + lat + '&lng=' + lng)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.address && locationField && locationField.value.trim() === '') {
                    locationField.value = data.address;
                }
            })
            .catch(function () { /* non-blocking */ });
    }

    map.on('click', function (e) {
        setPin(e.latlng.lat, e.latlng.lng, false);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    /* --- Autocomplete results dropdown --- */
    var pendingTag = null;

    function highlight(el) {
        var items = resultsBox.querySelectorAll('.map-result-item');
        for (var i = 0; i < items.length; i++) { items[i].classList.remove('active'); }
        if (el) { el.classList.add('active'); }
    }

    function selectResult(item) {
        var lat = parseFloat(item.getAttribute('data-lat'));
        var lng = parseFloat(item.getAttribute('data-lng'));
        var name = item.getAttribute('data-name') || '';
        setPin(lat, lng, true);
        map.setView([lat, lng], 16);
        if (baseField && name) {
            baseField.value = name;
            if (locationField) { locationField.value = name; }
        }
        searchInput.value = name;
        hideResults();
    }

    var baseField = document.getElementById('address');
    var activeIndex = -1;

    function renderResults(list) {
        resultsBox.innerHTML = '';
        if (!list || !list.length) {
            resultsBox.hidden = false;
            var none = document.createElement('div');
            none.className = 'map-result-empty';
            none.textContent = 'No places found. Click the map to pin it manually.';
            resultsBox.appendChild(none);
            return;
        }
        resultsBox.hidden = false;
        list.forEach(function (r, i) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'map-result-item';
            item.setAttribute('role', 'option');
            item.setAttribute('data-lat', r.lat);
            item.setAttribute('data-lng', r.lng);
            item.setAttribute('data-name', r.name);
            var first = document.createElement('span');
            first.className = 'map-result-first';
            var name = document.createElement('span');
            name.className = 'map-result-name';
            name.textContent = r.name || '';
            var badge = document.createElement('span');
            badge.className = 'map-result-src';
            badge.textContent = r.src || '';
            first.appendChild(name);
            if (r.src) { first.appendChild(badge); }
            item.appendChild(first);
            var sub = document.createElement('span');
            sub.className = 'map-result-sub';
            sub.textContent = r.sub || '';
            item.appendChild(sub);
            item.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                selectResult(item);
            });
            resultsBox.appendChild(item);
        });
        activeIndex = list.length ? 0 : -1;
        highlightIndex();
    }

    function highlightIndex() {
        var items = resultsBox.querySelectorAll('.map-result-item');
        if (!items.length) { return; }
        if (activeIndex < 0) { activeIndex = 0; }
        if (activeIndex >= items.length) { activeIndex = items.length - 1; }
        for (var i = 0; i < items.length; i++) { items[i].classList.remove('active'); }
        items[activeIndex].classList.add('active');
    }

    function runSearch() {
        var q = searchInput.value.trim();
        if (q.length < 3) { resultsBox.hidden = true; return; }
        if (hideTag) { clearTimeout(hideTag); hideTag = null; }
        var base = document.body.getAttribute('data-base') || '';

        fetch(base + '/ajax/place_search.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var list = (data && data.results) || [];
                list.forEach(function (r) {
                    r.lat = String(r.lat);
                    r.lng = String(r.lng);
                });
                renderResults(list);
            })
            .catch(function () {
                resultsBox.hidden = false;
                resultsBox.innerHTML = '<div class="map-result-empty">Search unavailable. Click the map to pin it manually.</div>';
            });
    }

    var searchTimer = null;
    var hideTag = null;
    function scheduleSearch() {
        if (searchTimer) { clearTimeout(searchTimer); }
        searchTimer = setTimeout(runSearch, 300);
    }

    searchInput.addEventListener('input', scheduleSearch);
    searchInput.addEventListener('focus', function () {
        if (resultsBox.querySelector('.map-result-item')) { resultsBox.hidden = false; }
    });
    searchInput.addEventListener('keydown', function (e) {
        var items = resultsBox.querySelectorAll('.map-result-item');
        if (resultsBox.hidden || !items.length) { return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, items.length - 1); highlightIndex(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlightIndex(); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            var active = resultsBox.querySelector('.map-result-item.active');
            if (active) { selectResult(active); }
        } else if (e.key === 'Escape') { resultsBox.hidden = true; }
    });

    document.addEventListener('click', function (e) {
        if (!resultsBox.contains(e.target) && e.target !== searchInput) {
            resultsBox.hidden = true;
        }
    });

    function hideResults() {
        resultsBox.hidden = true;
        if (hideTag) { clearTimeout(hideTag); hideTag = null; }
        activeIndex = -1;
    }

    if (hasPin) {
        coordText.textContent = latInput.value + ', ' + lngInput.value;
        setTimeout(function () {
            if (map) { map.invalidateSize(); }
        }, 250);
    }
})();