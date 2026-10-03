<?php

/**
 * Manager dashboard booking-activity chart.
 *
 * Shared by manager/dashboard.php (server-rendered first paint) and
 * manager/dashboard_chart.php (JSON endpoint used by the range toggle), so both
 * always agree on the numbers and the window.
 */

/** Whitelist the selectable ranges. Anything else falls back to 7 days. */
function manager_chart_days($raw): int
{
    $days = (int)$raw;
    return in_array($days, [7, 14, 30], true) ? $days : 7;
}

/**
 * Daily booking count + net revenue for a manager's own grounds.
 *
 * KEPT (removed per request): this used to be documented as "gross revenue" and the chart
 * labelled it "gross" in the legend, tooltips and aria-labels. That was wrong: the query sums
 * `total_price - discount`, which is the amount the customer actually paid, i.e. revenue net of
 * any discount. True gross would be SUM(total_price) with the discount added back. Since the
 * figure being shown is the correct one for a manager (what they earned), only the wording
 * changed. The old label was: "gross revenue".
 *
 * @return array{days:int,max:int,total_count:int,total_rev:float,points:array}
 */
function manager_chart_data(int $manager_id, int $days): array
{
    global $conn;

    $days = manager_chart_days($days);
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    // Ground ids are derived from the manager's own rows, so there is nothing
    // user-supplied to bind here; keep the cast anyway as a guard.
    $idRes = $conn->query('SELECT id FROM grounds WHERE manager_id = ' . $manager_id);
    $ids = [];
    if ($idRes) {
        while ($r = $idRes->fetch_assoc()) {
            $ids[] = (int)$r['id'];
        }
    }
    $idList = implode(',', $ids ?: [0]);

    $counts = [];
    $revs = [];
    $q = $conn->query(
        "SELECT booking_date, COUNT(*) c, COALESCE(SUM(total_price - COALESCE(discount, 0)), 0) rev
         FROM bookings
         WHERE ground_id IN ($idList)
           AND booking_date >= '$startDate'
           AND booking_date <= CURDATE()
           AND status != 'cancelled'
         GROUP BY booking_date"
    );
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $counts[$row['booking_date']] = (int)$row['c'];
            $revs[$row['booking_date']] = (float)$row['rev'];
        }
    }

    $points = [];
    $max = 1;
    $totalCount = 0;
    $totalRev = 0.0;
    for ($d = $days - 1; $d >= 0; $d--) {
        $day = date('Y-m-d', strtotime("-$d days"));
        $c = $counts[$day] ?? 0;
        $r = $revs[$day] ?? 0.0;
        $points[] = [
            'date' => $day,
            'dow' => date('D', strtotime($day)),
            'short' => date('M j', strtotime($day)),
            'label' => date('D, M j', strtotime($day)),
            'count' => $c,
            'rev' => $r,
        ];
        if ($c > $max) {
            $max = $c;
        }
        $totalCount += $c;
        $totalRev += $r;
    }

    return [
        'days' => $days,
        'max' => $max,
        'total_count' => $totalCount,
        'total_rev' => round($totalRev, 2),
        'points' => $points,
    ];
}

/**
 * Axis tick values for the chart scale: 0, 25%, 50%, 75% and the max, rounded
 * to whole bookings so the labels stay honest integers.
 *
 * Small maxima collapse into duplicate ticks (a max of 1 would otherwise render
 * "1 1 1 0 0"), so duplicates are dropped and the result stays ascending.
 *
 * @return array<int,int>
 */
function manager_chart_axis(int $max): array
{
    $max = max(1, $max);
    $ticks = [0, (int)round($max * 0.25), (int)round($max * 0.5), (int)round($max * 0.75), $max];
    return array_values(array_unique($ticks));
}
