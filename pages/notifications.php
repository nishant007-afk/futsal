<?php
require_once __DIR__ . '/../config/db.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['mark_read'])) {
        mark_notifications_read((int)$_SESSION['user_id']);
        set_flash('success', 'All notifications marked as read.');
        redirect('pages/notifications.php');
    }
    if (isset($_POST['delete_all'])) {
        $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ?');
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        set_flash('success', 'All notifications cleared.');
        redirect('pages/notifications.php');
    }
    if (isset($_POST['delete_notification'])) {
        $nid = (int)$_POST['delete_notification'];
        $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $nid, $_SESSION['user_id']);
        $stmt->execute();
        set_flash('success', 'Notification deleted.');
        redirect('pages/notifications.php');
    }
}

$filter = $_GET['filter'] ?? 'all';
$allNotifications = user_notifications((int)$_SESSION['user_id'], 100);
$unreadTotal = unread_notification_count((int)$_SESSION['user_id']);

$linkedBookings = [];
$linkedIds = [];
foreach ($allNotifications as $n) {
    if (preg_match('/booking_details\.php\?id=(\d+)/', (string)$n['link'], $m)) {
        $linkedIds[(int)$m[1]] = true;
    }
}
if ($linkedIds) {
    $ids = array_map('intval', array_keys($linkedIds));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.status, b.payment_status, b.total_price,
                g.name AS ground_name, g.location
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id IN ($ph)"
    );
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $linkedBookings[(int)$row['id']] = $row;
    }
}

$page_title = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="notif-page-head reveal" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Notifications</h1>
    </div>
</div>

<?php
// Group all notifications by recency
$todayStart = strtotime(date('Y-m-d'));
$yesterdayStart = strtotime('-1 day', $todayStart);
$groups = ['today' => [], 'yesterday' => [], 'earlier' => []];
foreach ($allNotifications as $n) {
    $ts = strtotime((string)$n['created_at']);
    if ($ts >= $todayStart) {
        $groups['today'][] = $n;
    } elseif ($ts >= $yesterdayStart) {
        $groups['yesterday'][] = $n;
    } else {
        $groups['earlier'][] = $n;
    }
}
?>

<div class="notif-toolbar reveal" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
    <div class="notif-tabs" id="notifFilterTabs" style="margin-top:0; margin-bottom:0;">
        <button type="button" data-filter="all" class="notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All (<?php echo count($allNotifications); ?>)</button>
        <button type="button" data-filter="unread" class="notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread <?php echo $unreadTotal > 0 ? '<span class="notif-tab-count">' . $unreadTotal . '</span>' : ''; ?></button>
        <button type="button" data-filter="read" class="notif-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">Read</button>
    </div>
    <div class="notif-actions" style="display:flex; gap:8px; align-items:center; margin-left:auto;">
        <?php if ($allNotifications): ?>
            <?php if ($unreadTotal > 0): ?>
                <form method="post" action="" novalidate style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="mark_read" value="1" class="btn btn-outline btn-sm"><i class="fa-solid fa-check-double"></i> Mark all read</button>
                </form>
            <?php endif; ?>
            <form method="post" action="" novalidate style="margin:0;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_all" value="1">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete all notifications?" data-confirm-title="Clear notifications"><i class="fa-solid fa-trash-can"></i> Clear all</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$allNotifications): ?>
    <?php empty_state('fa-regular fa-bell', "You're all caught up", 'When something happens with your bookings, updates land here.', grounds_list_url(), 'Browse courts'); ?>
<?php else: ?>
    <div class="notif-list reveal" id="notifListContainer">
        <?php foreach ($groups as $groupKey => $groupItems): ?>
            <?php if (!$groupItems) continue; ?>
            <div class="notif-group" data-group="<?php echo $groupKey; ?>">
                <div class="notif-group-head">
                    <?php echo $groupKey === 'today' ? 'Today' : ($groupKey === 'yesterday' ? 'Yesterday' : 'Earlier'); ?>
                </div>
                <?php foreach ($groupItems as $n): ?>
                    <?php
                    $relB = null;
                    if (preg_match('/booking_details\.php\?id=(\d+)/', (string)$n['link'], $bm)) {
                        $relB = $linkedBookings[(int)$bm[1]] ?? null;
                    }
                    ?>
                    <div class="notif-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>" data-status="<?php echo $n['is_read'] ? 'read' : 'unread'; ?>">
                        <div class="notif-thumb c-<?php echo notification_icon_color($n['icon']); ?>">
                            <i class="fa-solid <?php echo e($n['icon']); ?>"></i>
                        </div>
                        <div class="notif-body">
                            <div class="notif-head-line">
                                <h3 class="notif-title-text"><?php echo e($n['title']); ?></h3>
                                <?php if (!$n['is_read']): ?>
                                    <span class="notif-badge unread">New</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($n['body'] !== ''): ?>
                                <div class="notif-desc"><?php echo e($n['body']); ?></div>
                            <?php endif; ?>

                            <?php if ($relB): ?>
                                <div class="notif-booking-chip">
                                    <span class="nbc-court"><i class="fa-solid fa-futbol"></i> <?php echo e($relB['ground_name']); ?></span>
                                    <span class="nbc-dot">&middot;</span>
                                    <span class="nbc-date"><i class="fa-regular fa-calendar"></i> <?php echo date('D, M j', strtotime($relB['booking_date'])); ?></span>
                                    <span class="nbc-dot">&middot;</span>
                                    <span class="nbc-time"><i class="fa-regular fa-clock"></i> <?php echo substr($relB['start_time'], 0, 5); ?></span>
                                    <span class="nbc-dot">&middot;</span>
                                    <span class="nbc-ref">#<?php echo e($relB['booking_ref']); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="notif-meta">
                                <span class="notif-time-str"><i class="fa-regular fa-clock"></i> <?php echo e(notification_time($n['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="notif-side">
                            <?php
                            $targetUrl = !empty($n['link']) ? base_url('pages/notification_details.php?id=' . (int)$n['id']) : base_url('pages/notification_details.php?id=' . (int)$n['id'] . '&view=1');
                            $shortTitle = (mb_strlen($n['title']) > 50) ? (mb_substr($n['title'], 0, 47) . '…') : $n['title'];
                            ?>
                            <a href="<?php echo $targetUrl; ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-right"></i> View</a>
                            <form method="post" action="" class="d-inline" novalidate>
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="delete_notification" value="<?php echo (int)$n['id']; ?>">
                                <button type="submit" class="btn-icon" data-confirm="Delete &ldquo;<?php echo e($shortTitle); ?>&rdquo;?" data-confirm-title="Delete notification" title="Delete" aria-label="Delete notification"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="notifEmptyFilter" style="display:none;">
        <?php empty_state('fa-regular fa-bell', 'Nothing in this view', 'There are no notifications matching this filter.', grounds_list_url(), 'Browse courts'); ?>
    </div>

    <script>
    (function() {
        const tabs = document.querySelectorAll('#notifFilterTabs .notif-tab');
        const container = document.getElementById('notifListContainer');
        const emptyView = document.getElementById('notifEmptyFilter');
        if (!tabs.length || !container) return;

        function applyFilter(filter, pushState) {
            tabs.forEach(t => {
                if (t.dataset.filter === filter) {
                    t.classList.add('active');
                } else {
                    t.classList.remove('active');
                }
            });

            const items = container.querySelectorAll('.notif-item');
            let visibleCount = 0;
            items.forEach(item => {
                const status = item.dataset.status;
                const match = (filter === 'all') || (status === filter);
                item.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            // Toggle date group headings if all items in that group are hidden
            const groups = container.querySelectorAll('.notif-group');
            groups.forEach(group => {
                const groupItems = group.querySelectorAll('.notif-item');
                let hasVisible = false;
                groupItems.forEach(gi => {
                    if (gi.style.display !== 'none') hasVisible = true;
                });
                group.style.display = hasVisible ? '' : 'none';
            });

            if (emptyView) {
                emptyView.style.display = (visibleCount === 0) ? 'block' : 'none';
            }

            if (pushState) {
                const url = new URL(window.location);
                if (filter === 'all') {
                    url.searchParams.delete('filter');
                } else {
                    url.searchParams.set('filter', filter);
                }
                window.history.pushState({ filter }, '', url);
            }
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const filter = this.dataset.filter;
                applyFilter(filter, true);
            });
        });

        window.addEventListener('popstate', function(e) {
            const filter = (e.state && e.state.filter) || new URL(window.location).searchParams.get('filter') || 'all';
            applyFilter(filter, false);
        });

        // Initialize state on load based on initial URL
        const initialFilter = new URL(window.location).searchParams.get('filter') || 'all';
        if (initialFilter !== 'all') {
            applyFilter(initialFilter, false);
        }
    })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
