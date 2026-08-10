<?php
require_once __DIR__ . '/../config/db.php';

$page_title = 'Find a Court on the Map';
$page_description = 'All futsal courts in the Kathmandu Valley on an interactive map. Pick a pin to see prices, then book your slot on GoalSpace.';
require __DIR__ . '/../includes/header.php';

$grounds = $conn->prepare('SELECT id, name, location, price_per_hour, discount_price, latitude, longitude FROM grounds WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id');
$grounds->execute();
$items = $grounds->get_result()->fetch_all(MYSQLI_ASSOC);
$json = json_encode($items);
?>

<div class="page-head reveal">
    <div>
        <h2>Courts on the Map</h2>
        <p class="page-sub muted">Pins show active courts. Click a pin to open the court and book a slot.</p>
    </div>
</div>
<div class="map-browse-wrap reveal">
    <div id="browseMap" style="width:100%;height:640px;border-radius:12px;overflow:hidden;border:1px solid var(--line);"></div>
</div>

<script src="<?php echo base_url('assets/js/leaflet/leaflet.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var data = <?php echo $json; ?>;
    var map = L.map('browseMap').setView([27.7172, 85.3240], 12);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 18,
        subdomains: 'abcd',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }).addTo(map);

    var base = document.body.getAttribute('data-base') || '';
    data.forEach(function (g) {
        var price = g.discount_price && parseFloat(g.discount_price) > 0 && parseFloat(g.discount_price) < parseFloat(g.price_per_hour)
            ? 'Rs ' + parseFloat(g.discount_price).toFixed(0)
            : 'Rs ' + parseFloat(g.price_per_hour).toFixed(0);
        var marker = L.marker([parseFloat(g.latitude), parseFloat(g.longitude)]).addTo(map);
        var html = '<div style="font-size:13px;">'
            + '<strong style="font-size:15px;">' + g.name + '</strong><br>'
            + g.location + '<br>'
            + price + ' / hour'
            + '<br><a href="' + (base + '/pages/ground.php?id=' + g.id) + '" style="color:#059669;font-weight:600;">View and book</a>'
            + '</div>';
        marker.bindPopup(html, { minWidth: 260 });
    });

    if (data.length > 0) {
        var group = L.featureGroup(data.map(function (g) {
            return L.marker([parseFloat(g.latitude), parseFloat(g.longitude)]);
        }));
        map.fitBounds(group.getBounds().padding([40, 40]));
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>