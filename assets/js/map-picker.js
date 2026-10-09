/* Location picker for admin/manager ground forms.
   High-quality maps API: Leaflet + Multiple Tile Providers (Streets, Satellite, OSM),
   GPS Geolocation ("Locate Me"), Draggable Marker, and Fast Nepal Place Autocomplete. */
(function () {
    'use strict';
    var group = document.getElementById('locationMap');
    if (!group) { return; }

    var latInput = document.getElementById('gmLatitude');
    var lngInput = document.getElementById('gmLongitude');
    var coordText = document.getElementById('mapCoords');
    var searchInput = document.getElementById('mapSearch');
    var searchIcon = document.getElementById('mapSearchIcon');
    var searchClear = document.getElementById('mapSearchClear');
    var locateBtn = document.getElementById('mapLocateBtn');
    var resultsBox = document.getElementById('mapResults');
    var locationField = document.getElementById('location');
    var addressField = document.getElementById('address');

    var startLat = parseFloat(document.getElementById('gmMapLat').value || '27.7172');
    var startLng = parseFloat(document.getElementById('gmMapLng').value || '85.3240');
    var hasPin = latInput.value !== '' && lngInput.value !== '';

    // 1. High-Resolution Tile Layers
    var streetsLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
    });

    var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });

    var osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    // 2. Map Initialization
    var map = L.map(group, {
        center: [startLat, startLng],
        zoom: hasPin ? 17 : 13,
        scrollWheelZoom: true,
        zoomControl: true,
        layers: [streetsLayer]
    });
    window.groundMap = map;

    // Layer Switcher Control
    var baseLayers = {
        "<i class='fa-solid fa-road'></i> Streets": streetsLayer,
        "<i class='fa-solid fa-satellite'></i> Satellite": satelliteLayer,
        "<i class='fa-solid fa-map'></i> OpenStreetMap": osmLayer
    };
    L.control.layers(baseLayers, null, { position: 'topright' }).addTo(map);

    // 3. Custom Draggable Marker
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
        marker = L.marker([lat, lng], { icon: icon, draggable: true }).addTo(map);
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        if (coordText) {
            coordText.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        }
        if (zoomIn) { map.setView([lat, lng], 17); }

        marker.on('dragend', function () {
            var pos = marker.getLatLng();
            latInput.value = pos.lat.toFixed(6);
            lngInput.value = pos.lng.toFixed(6);
            if (coordText) {
                coordText.textContent = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
            }
            reverseGeocode(pos.lat, pos.lng);
        });
    }

    if (hasPin) {
        setPin(startLat, startLng, false);
    }

    // 4. Reverse Geocoding
    function reverseGeocode(lat, lng) {
        var base = document.body.getAttribute('data-base') || '';
        fetch(base + '/ajax/place_search.php?lat=' + lat + '&lng=' + lng)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.address) {
                    if (addressField && addressField.value.trim() === '') {
                        addressField.value = data.address;
                    }
                    if (locationField && locationField.value.trim() === '') {
                        var parts = data.address.split(',');
                        locationField.value = parts.length > 1 ? parts[0].trim() + ', ' + parts[1].trim() : data.address;
                    }
                }
            })
            .catch(function () { /* non-blocking */ });
    }

    map.on('click', function (e) {
        setPin(e.latlng.lat, e.latlng.lng, false);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    // 5. GPS "Locate Me" Button
    if (locateBtn && navigator.geolocation) {
        locateBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var origHtml = locateBtn.innerHTML;
            locateBtn.disabled = true;
            locateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Locating...';

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    locateBtn.disabled = false;
                    locateBtn.innerHTML = origHtml;
                    var lat = pos.coords.latitude;
                    var lng = pos.coords.longitude;
                    setPin(lat, lng, true);
                    reverseGeocode(lat, lng);
                },
                function (err) {
                    locateBtn.disabled = false;
                    locateBtn.innerHTML = origHtml;
                    alert('Could not detect your GPS location: ' + err.message + '. You can click the map or search above.');
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        });
    }

    // 6. Search Bar & Clear Button
    if (searchClear && searchInput) {
        searchClear.addEventListener('click', function () {
            searchInput.value = '';
            searchClear.style.display = 'none';
            hideResults();
            searchInput.focus();
        });

        searchInput.addEventListener('input', function () {
            searchClear.style.display = this.value.trim().length > 0 ? 'inline-flex' : 'none';
        });
    }

    // 7. Place Autocomplete Results
    function selectResult(item) {
        var lat = parseFloat(item.getAttribute('data-lat'));
        var lng = parseFloat(item.getAttribute('data-lng'));
        var name = item.getAttribute('data-name') || '';
        setPin(lat, lng, true);

        if (addressField && (!addressField.value || addressField.value.trim() === '')) {
            addressField.value = name;
        }
        if (locationField && (!locationField.value || locationField.value.trim() === '')) {
            locationField.value = name;
        }
        searchInput.value = name;
        if (searchClear) { searchClear.style.display = 'inline-flex'; }
        hideResults();
    }

    var activeIndex = -1;
    function highlightIndex() {
        var items = resultsBox.querySelectorAll('.map-result-item');
        if (!items.length) { return; }
        if (activeIndex < 0) { activeIndex = 0; }
        if (activeIndex >= items.length) { activeIndex = items.length - 1; }
        for (var i = 0; i < items.length; i++) { items[i].classList.remove('active'); }
        items[activeIndex].classList.add('active');
    }

    function renderResults(list) {
        resultsBox.innerHTML = '';
        if (!list || !list.length) {
            resultsBox.hidden = false;
            var none = document.createElement('div');
            none.className = 'map-result-empty';
            none.textContent = 'No places found. Click the map to pin your court manually.';
            resultsBox.appendChild(none);
            return;
        }
        resultsBox.hidden = false;
        list.forEach(function (r) {
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
            badge.textContent = r.src || 'Place';
            first.appendChild(name);
            first.appendChild(badge);
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

    function setLoading(isLoading) {
        if (!searchIcon) { return; }
        if (isLoading) {
            searchIcon.className = 'fa-solid fa-spinner fa-spin map-search-ico';
        } else {
            searchIcon.className = 'fa-solid fa-magnifying-glass map-search-ico';
        }
    }

    function runSearch() {
        var q = searchInput.value.trim();
        if (q.length < 2) { resultsBox.hidden = true; return; }
        var base = document.body.getAttribute('data-base') || '';

        setLoading(true);

        fetch(base + '/ajax/place_search.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var list = (data && data.results) || [];
                if (list.length > 0) {
                    setLoading(false);
                    renderResults(list);
                } else {
                    // Client-side fallback to direct Photon API with Nepal coordinates bias
                    fetch('https://photon.komoot.io/api/?q=' + encodeURIComponent(q) + '&lat=27.7172&lon=85.3240&limit=10&countrycode=NP')
                        .then(function (res) { return res.json(); })
                        .then(function (pdata) {
                            setLoading(false);
                            var fallbackList = [];
                            if (pdata && pdata.features) {
                                pdata.features.forEach(function (f) {
                                    var c = f.geometry && f.geometry.coordinates;
                                    var p = f.properties || {};
                                    if (c && c.length >= 2) {
                                        fallbackList.push({
                                            lat: String(c[1]),
                                            lng: String(c[0]),
                                            name: p.name || p.street || 'Place',
                                            sub: [p.street, p.city, p.country].filter(Boolean).join(', '),
                                            src: p.osm_value || 'Place'
                                        });
                                    }
                                });
                            }
                            renderResults(fallbackList);
                        })
                        .catch(function () {
                            setLoading(false);
                            renderResults([]);
                        });
                }
            })
            .catch(function () {
                setLoading(false);
                resultsBox.hidden = false;
                resultsBox.innerHTML = '<div class="map-result-empty">Search unavailable. Click the map to drop the pin.</div>';
            });
    }

    var searchTimer = null;
    function scheduleSearch() {
        if (searchTimer) { clearTimeout(searchTimer); }
        searchTimer = setTimeout(runSearch, 280);
    }

    if (searchInput) {
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
    }

    document.addEventListener('click', function (e) {
        if (resultsBox && !resultsBox.contains(e.target) && e.target !== searchInput) {
            resultsBox.hidden = true;
        }
    });

    function hideResults() {
        if (resultsBox) { resultsBox.hidden = true; }
        activeIndex = -1;
    }

    // Refresh Leaflet container dimensions when tab becomes visible
    var mapTabBtn = document.querySelector('.editor-tab-btn[data-tab="location"]');
    if (mapTabBtn) {
        mapTabBtn.addEventListener('click', function () {
            setTimeout(function () {
                if (map) { map.invalidateSize(); }
            }, 120);
        });
    }
})();