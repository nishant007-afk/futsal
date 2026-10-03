<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$days = (int)($_GET['days'] ?? 7);
if (!in_array($days, [7, 14, 30], true)) {
    $days = 7;
}

$myGrounds = $conn->query(
    'SELECT id, name FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id']
)->fetch_all(MYSQLI_ASSOC);

$ids = array_map(fn($g) => (int)$g['id'], $myGrounds);
$idList = implode(',', $ids ?: [0]);

$totalGrounds = count($myGrounds);
$todayBookings = $conn->query("SELECT COUNT(*) c FROM bookings WHERE ground_id IN ($idList) AND booking_date = CURDATE() AND status != 'cancelled'")->fetch_assoc()['c'];
$paidCount = $conn->query("SELECT COUNT(*) c FROM bookings WHERE ground_id IN ($idList) AND payment_status = 'paid' AND status != 'cancelled'")->fetch_assoc()['c'];
$revenue = $conn->query("SELECT COALESCE(SUM(total_price - COALESCE(discount, 0)), 0) s FROM bookings WHERE ground_id IN ($idList) AND status != 'cancelled'")->fetch_assoc()['s'];

// KEPT (removed per request): the chart window was computed inline here, which meant the
// server-rendered chart and the new JSON endpoint had to be kept in sync by hand. Both now
// call manager_chart_data() in includes/lib/dashboard_chart.php.
// $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
// $countsQuery = $conn->query("
//     SELECT booking_date, COUNT(*) c, COALESCE(SUM(total_price - COALESCE(discount, 0)), 0) rev
//     FROM bookings
//     WHERE ground_id IN ($idList)
//       AND booking_date >= '$startDate'
//       AND booking_date <= CURDATE()
//       AND status != 'cancelled'
//     GROUP BY booking_date
// ");
// $dateCounts = [];
// $dateRevs = [];
// if ($countsQuery) {
//     while ($row = $countsQuery->fetch_assoc()) {
//         $dateCounts[$row['booking_date']] = (int)$row['c'];
//         $dateRevs[$row['booking_date']] = (float)$row['rev'];
//     }
// }
// $weekDays = [];
// $weekMax = 1;
// for ($d = $days - 1; $d >= 0; $d--) {
//     $day = date('Y-m-d', strtotime("-$d days"));
//     $c = $dateCounts[$day] ?? 0;
//     $r = $dateRevs[$day] ?? 0.0;
//     $weekDays[] = ['day' => date('D', strtotime($day)), 'short' => date('M j', strtotime($day)), 'count' => $c, 'rev' => $r];
//     if ($c > $weekMax) { $weekMax = $c; }
// }
// $weekRevenue = (float)$conn->query("SELECT COALESCE(SUM(total_price - COALESCE(discount, 0)), 0) s FROM bookings WHERE ground_id IN ($idList) AND booking_date >= CURDATE() - INTERVAL " . ($days - 1) . " DAY AND booking_date <= CURDATE() AND status != 'cancelled'")->fetch_assoc()['s'];

$chart = manager_chart_data((int)$_SESSION['user_id'], $days);
$weekDays = $chart['points'];
$weekMax = (int)$chart['max'];
$weekRevenue = (float)$chart['total_rev'];
$subStatus = subscription_status((int)$_SESSION['user_id']);
$setupFee = manager_setup_fee();
$monthlyFee = manager_monthly_fee();

$todayList = $conn->query(
    "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.payment_method, b.created_at,
            g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.ground_id IN ($idList) AND b.booking_date = CURDATE() AND b.status != 'cancelled'
     ORDER BY b.start_time ASC
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

// Needs-attention: upcoming unpaid bookings
$attentionUnpaid = $conn->query(
    "SELECT b.id, b.booking_ref, b.booking_date, b.start_time, g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.ground_id IN ($idList) AND b.status = 'confirmed' AND b.payment_status = 'unpaid'
           AND b.booking_date >= CURDATE()
     ORDER BY b.booking_date ASC, b.start_time ASC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$page_title = 'Manager Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <div>
        <h1 class="page-title">Manager Dashboard</h1>
        <p class="muted" style="margin-top:4px; font-size:14px;">Court availability, booking activity, and earnings overview</p>
    </div>
    <div class="actions">
        <a href="<?php echo base_url('manager/grounds.php?add=1'); ?>" class="btn btn-primary btn-sm">+ Add Ground</a>
        <!-- KEPT (removed per request): this button pointed at #financialSummary, the anchor for
             the "Financial Summary" section that was removed from this page, so it was a dead link.
             It is kept here commented rather than deleted. If a financial summary is ever rebuilt,
             restore it and give the section that id back.
        <a href="<?php echo base_url('manager/dashboard.php#financialSummary'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-invoice-dollar"></i> Financial Summary</a>
        -->
        <a href="<?php echo base_url('manager/promos.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-tags"></i> Promo Codes</a>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <!-- KEPT (removed per request): the banner was reusing the fixed-position toast component,
         so its orange accent read as a detached bracket floating beside the card.
    <div class="toast toast-warning toast-inline reveal" role="status">
        <div class="toast-content">
            <div class="toast-msg"><strong>Subscription Alert:</strong> <?php echo e($subStatus['label']); ?> &ndash; your courts are hidden from players. Pay the setup fee or renew your monthly service charge to go live again.</div>
        </div>
    </div>
    -->
    <div class="notice-alert reveal" role="status">
        <i class="fa-solid fa-triangle-exclamation notice-alert-icon" aria-hidden="true"></i>
        <div class="notice-alert-body">
            <strong>Subscription Alert:</strong> <?php echo e($subStatus['label']); ?> &ndash; your courts are hidden from players. Pay the setup fee or renew your monthly service charge to go live again.
        </div>
        <a href="<?php echo base_url('manager/subscription.php'); ?>" class="btn btn-primary btn-sm notice-alert-cta">Fix it</a>
    </div>
<?php endif; ?>

<?php if ($attentionUnpaid): ?>
    <div class="attention-strip reveal">
        <?php if ($attentionUnpaid): ?>
            <a href="<?php echo base_url('manager/bookings.php?payment=unpaid&status=confirmed'); ?>" class="attention-item">
                <i class="fa-solid fa-wallet"></i>
                <span><strong><?php echo count($attentionUnpaid); ?> booking<?php echo count($attentionUnpaid) > 1 ? 's need' : ' needs'; ?> payment:</strong> <em>Latest: <?php echo e($attentionUnpaid[0]['user_name']); ?> &middot; <?php echo e($attentionUnpaid[0]['ground_name']); ?></em></span>
                <i class="fa-solid fa-arrow-right attention-go"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat reveal">
        <h3>My Grounds</h3>
        <p><?php echo $totalGrounds; ?></p>
        <span class="muted"><?php echo $totalGrounds === 1 ? '1 court listed' : $totalGrounds . ' courts listed'; ?></span>
    </div>
    <div class="stat reveal">
        <h3>Bookings Today</h3>
        <p><?php echo $todayBookings; ?></p>
        <span class="muted"><?php echo $paidCount; ?> paid across all time</span>
    </div>
    <div class="stat reveal">
        <h3>Net Revenue</h3>
        <p class="stat-amount"><?php echo format_price($revenue); ?></p>
        <span class="muted">All-time confirmed &middot; after discount &middot; 100% yours</span>
    </div>
    <div class="stat reveal">
        <h3>Subscription</h3>
        <p class="stat-amount" style="font-size:20px;"><?php echo e($subStatus['label']); ?></p>
        <span class="muted">
            <?php if ($subStatus['sub'] && $subStatus['sub']['period_end']): ?>
                Renewal: <?php echo e(date('M j, Y', strtotime($subStatus['sub']['period_end']))); ?>
            <?php else: ?>
                Rs <?php echo number_format($setupFee, 0); ?> setup &middot; Rs <?php echo number_format($monthlyFee, 0); ?>/mo
            <?php endif; ?>
        </span>
    </div>
</div>

<h3 class="reveal dash-section">Today's Bookings</h3>
<?php bookings_table_html($todayList, [
    'show_player' => true,
    'show_ref' => true,
    'manager_action' => true,
    'empty_icon' => 'fa-regular fa-calendar-check',
    'empty_title' => 'Nothing booked today',
    'empty_sub' => "Today's bookings on your grounds will appear here.",
]); ?>



<h3 class="reveal dash-section">Booking Activity &amp; Revenue</h3>
<div class="chart-wrap reveal" id="dashChart"
     data-endpoint="<?php echo base_url('manager/dashboard_chart.php'); ?>">
    <div class="chart-legend">
        <span id="dashChartRangeLabel">Last <?php echo $days; ?> days</span>
        <!-- KEPT (removed per request): this read "... gross &middot; N bookings", but the figure is
             SUM(total_price - discount), i.e. net of discount. Relabelled rather than recomputed. -->
        <span class="strong" id="dashChartTotals"><?php echo format_price($weekRevenue); ?> net &middot; <?php echo (int)array_sum(array_column($weekDays, 'count')); ?> bookings</span>
    </div>
    <div class="chart-range" role="group" aria-label="Chart date range">
        <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days'] as $rangeDays => $rangeLabel): ?>
            <!-- KEPT (removed per request): these were plain <a href> links, so changing range
                 reloaded the whole dashboard. Now buttons handled by the JSON endpoint. The href is
                 retained as a no-JS fallback so the page still works without JavaScript.
            <a href="<?php echo base_url('manager/dashboard.php?days=' . $rangeDays); ?>" class="cr-link<?php echo $days === $rangeDays ? ' active' : ''; ?>"><?php echo $rangeLabel; ?></a>
            -->
            <button type="button"
                    class="cr-link<?php echo $days === $rangeDays ? ' active' : ''; ?>"
                    data-chart-days="<?php echo $rangeDays; ?>"
                    data-chart-href="<?php echo base_url('manager/dashboard.php?days=' . $rangeDays); ?>"
                    aria-pressed="<?php echo $days === $rangeDays ? 'true' : 'false'; ?>"><?php echo $rangeLabel; ?></button>
        <?php endforeach; ?>
    </div>

    <!-- KEPT (removed per request): flat bar row with a native title tooltip and no scale.
    <div class="bar-chart" role="img" aria-label="Bookings per day for the last <?php echo $days; ?> days">
        <?php foreach ($weekDays as $wd): ?>
            <?php $pct = $weekMax > 0 ? (int)round(($wd['count'] / $weekMax) * 100) : 0; ?>
            <div class="bar-group" title="<?php echo (int)$wd['count']; ?> bookings &middot; <?php echo format_price($wd['rev']); ?>">
                <div class="bar-track"><div class="bar-fill" style="height:<?php echo max($pct, 6); ?>%;"></div></div>
                <div class="bar-value"><?php echo (int)$wd['count']; ?></div>
                <div class="bar-label"><?php echo e($wd['short']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    -->

    <div class="bar-chart-frame">
        <div class="bar-axis" id="dashChartAxis" aria-hidden="true">
            <?php foreach (array_reverse(manager_chart_axis($weekMax)) as $tick): ?>
                <span class="bar-axis-tick"><?php echo (int)$tick; ?></span>
            <?php endforeach; ?>
        </div>
        <div class="bar-chart" id="dashChartBars" role="img"
             aria-label="Bookings per day for the last <?php echo $days; ?> days">
            <?php foreach ($weekDays as $wd): ?>
                <?php $pct = $weekMax > 0 ? (int)round(($wd['count'] / $weekMax) * 100) : 0; ?>
                <div class="bar-group"
                     tabindex="0"
                     data-date="<?php echo e($wd['date']); ?>"
                     data-label="<?php echo e($wd['dow'] . ', ' . $wd['short']); ?>"
                     data-count="<?php echo (int)$wd['count']; ?>"
                     data-rev="<?php echo e(number_format($wd['rev'], 2, '.', '')); ?>"
                     aria-label="<?php echo e($wd['dow'] . ', ' . $wd['short'] . ': ' . (int)$wd['count'] . ' bookings, ' . format_price($wd['rev']) . ' net revenue'); ?>">
                    <div class="bar-track"><div class="bar-fill" style="height:<?php echo max($pct, 6); ?>%;"></div></div>
                    <div class="bar-value"><?php echo (int)$wd['count']; ?></div>
                    <div class="bar-label"><?php echo e($wd['short']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="bar-tip" id="dashChartTip" role="status" aria-live="polite" hidden></div>
    </div>
</div>

<script>
/* Chart range toggle: fetch JSON, re-render bars + axis + legend totals, no reload. */
(function () {
    var root = document.getElementById('dashChart');
    if (!root) return;

    var barsEl = document.getElementById('dashChartBars');
    var axisEl = document.getElementById('dashChartAxis');
    var tipEl = document.getElementById('dashChartTip');
    var rangeLabel = document.getElementById('dashChartRangeLabel');
    var totalsEl = document.getElementById('dashChartTotals');
    var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-chart-days]'));
    var busy = false;

    function money(n) {
        n = Number(n) || 0;
        return 'Rs ' + n.toLocaleString('en-NP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderAxis(axis) {
        axisEl.innerHTML = '';
        axis.slice().reverse().forEach(function (tick) {
            var s = document.createElement('span');
            s.className = 'bar-axis-tick';
            s.textContent = tick;
            axisEl.appendChild(s);
        });
    }

    function renderBars(data) {
        barsEl.innerHTML = '';
        data.points.forEach(function (p) {
            var pct = data.max > 0 ? Math.round((p.count / data.max) * 100) : 0;
            if (pct < 6) pct = 6;

            var group = document.createElement('div');
            group.className = 'bar-group';
            group.tabIndex = 0;
            group.setAttribute('data-label', p.label);
            group.setAttribute('data-count', p.count);
            group.setAttribute('data-rev', Number(p.rev).toFixed(2));
            group.setAttribute('aria-label',
                p.label + ': ' + p.count + ' bookings, ' + money(p.rev) + ' net revenue');

            var track = document.createElement('div');
            track.className = 'bar-track';
            var fill = document.createElement('div');
            fill.className = 'bar-fill';
            fill.style.height = pct + '%';
            track.appendChild(fill);

            var value = document.createElement('div');
            value.className = 'bar-value';
            value.textContent = p.count;

            var label = document.createElement('div');
            label.className = 'bar-label';
            label.textContent = p.short;

            group.appendChild(track);
            group.appendChild(value);
            group.appendChild(label);
            barsEl.appendChild(group);
        });
    }

    function load(days, btn) {
        if (busy) return;
        busy = true;
        root.classList.add('is-loading');
        buttons.forEach(function (b) { b.disabled = true; });

        fetch(root.dataset.endpoint + '?days=' + encodeURIComponent(days), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data || data.ok !== true || !data.points) throw new Error('bad payload');

                renderAxis(data.axis);
                renderBars(data);

                rangeLabel.textContent = 'Last ' + data.days + ' days';
                totalsEl.textContent = money(data.total_rev) + ' net · ' + data.total_count + ' bookings';

                buttons.forEach(function (b) {
                    var on = b === btn;
                    b.classList.toggle('active', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });

                if (btn && btn.dataset.chartHref) {
                    history.replaceState(null, '', btn.dataset.chartHref);
                }
            })
            .catch(function () {
                // Fall back to a normal navigation so the range still changes.
                if (btn && btn.dataset.chartHref) {
                    window.location.href = btn.dataset.chartHref;
                }
            })
            .then(function () {
                busy = false;
                root.classList.remove('is-loading');
                buttons.forEach(function (b) { b.disabled = false; });
            });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            load(btn.dataset.chartDays, btn);
        });
    });

    /* Custom tooltip: date, bookings and net revenue for the hovered day.
       KEPT (removed per request): the tooltip line read "... gross revenue"; the value is
       SUM(total_price - discount), so it is net of discount. */
    function showTip(group) {
        tipEl.innerHTML =
            '<strong>' + group.dataset.label + '</strong>' +
            '<span>' + group.dataset.count + (group.dataset.count === '1' ? ' booking' : ' bookings') + '</span>' +
            '<span>' + money(group.dataset.rev) + ' net revenue</span>';
        tipEl.hidden = false;
        var host = group.getBoundingClientRect();
        var wrap = root.querySelector('.bar-chart-frame').getBoundingClientRect();
        tipEl.style.left = (host.left - wrap.left + host.width / 2) + 'px';
        tipEl.style.top = (host.top - wrap.top - 8) + 'px';
    }

    barsEl.addEventListener('mouseover', function (ev) {
        var g = ev.target.closest('.bar-group');
        if (g) showTip(g);
    });
    barsEl.addEventListener('focusin', function (ev) {
        var g = ev.target.closest('.bar-group');
        if (g) showTip(g);
    });
    barsEl.addEventListener('mouseleave', function () { tipEl.hidden = true; });
    barsEl.addEventListener('focusout', function () { tipEl.hidden = true; });
})();
</script>



<?php require __DIR__ . '/../includes/footer.php'; ?>
