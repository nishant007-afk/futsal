<?php
if (!isset($conn)) {
    require_once __DIR__ . '/../../config/db.php';
}
$lp = $editing ?: [];
$lpLat = isset($lp['latitude']) && $lp['latitude'] !== null ? (float)$lp['latitude'] : null;
$lpLng = isset($lp['longitude']) && $lp['longitude'] !== null ? (float)$lp['longitude'] : null;
$mapLat = $lpLat !== null ? $lpLat : 27.7172;
$mapLng = $lpLng !== null ? $lpLng : 85.3240;
?>
<div class="form-group map-picker-group">
    <label for="mapSearch"><i class="fa-solid fa-map-location-dot"></i> Location pin <span class="muted">(search a place, pick a result or click the map)</span></label>
    <div class="map-search-row">
        <i class="fa-solid fa-magnifying-glass map-search-ico"></i>
        <input type="text" id="mapSearch" class="map-search-input" placeholder="Search for a place, address or landmark in Nepal…" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="mapResults">
        <div class="map-results" id="mapResults" role="listbox" hidden></div>
    </div>
    <div class="location-map" id="locationMap"></div>
    <div class="map-picker-foot">
        <span class="map-picker-hint"><i class="fa-solid fa-hand-pointer"></i> Drag the map and click to drop the pin on your exact court.</span>
        <span class="map-coords" id="mapCoords"><?php echo $lpLat !== null ? e(number_format($lpLat, 6) . ', ' . number_format($lpLng, 6)) : 'No pin set yet'; ?></span>
    </div>
    <input type="hidden" id="gmLatitude" name="latitude" value="<?php echo $lpLat !== null ? e($lpLat) : ''; ?>">
    <input type="hidden" id="gmLongitude" name="longitude" value="<?php echo $lpLng !== null ? e($lpLng) : ''; ?>">
    <input type="hidden" id="gmMapLat" value="<?php echo e($mapLat); ?>">
    <input type="hidden" id="gmMapLng" value="<?php echo e($mapLng); ?>">
</div>
